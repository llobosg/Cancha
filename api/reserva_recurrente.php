<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
// Limpieza de buffer para evitar JSON roto
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bitacora.php'; 

// Cargar BrevoMailer si existe
if (file_exists(__DIR__ . '/../includes/brevo_mailer.php')) {
    require_once __DIR__ . '/../includes/brevo_mailer.php';
}

$input = json_decode(file_get_contents('php://input'), true);

// === 1. DETECTAR TIPO DE USUARIO (SOCIO O ADMIN) ===
$id_socio_session = $_SESSION['id_socio'] ?? null;
$id_recinto_admin = $_SESSION['id_recinto'] ?? null;

if (!$id_socio_session && !$id_recinto_admin) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // === VALIDACIÓN DE DATOS ===
    $required = ['id_cancha', 'hora_inicio', 'repeat_day', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) throw new Exception("Campo requerido: $field");
    }

    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $repeat_day = (int)$input['repeat_day']; 
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    
    // Duración en minutos (nuevo estándar)
    $duracion_minutos = intval($input['duracion_bloque'] ?? 60);
    
    // El ID de socio puede venir explícito (si es admin creando para otro) o de la sesión
    $id_socio_input = $input['id_socio'] ?? $id_socio_session; 
    
    // Monto unitario (calculado en frontend como Total / Cantidad)
    $monto_unitario = floatval($input['monto_total'] ?? 0);
    
    // Datos opcionales de nuevo socio
    $nombre_nuevo = trim($input['nombreNuevoSocio'] ?? '');
    $email_nuevo = trim($input['emailNuevoSocio'] ?? '');
    $tel_nuevo = trim($input['telNuevoSocio'] ?? '');

    // === 2. VERIFICAR CANCHA Y OBTENER DATOS ===
    $stmt = $pdo->prepare("SELECT id_recinto, nombre_cancha, valor_arriendo, capacidad_jugadores FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    $cancha_data = $stmt->fetch();

    if (!$cancha_data) {
        throw new Exception('La cancha seleccionada no existe');
    }

    $id_recinto_cancha = $cancha_data['id_recinto'];
    $jugadores_esperados = intval($cancha_data['capacidad_jugadores'] ?? 4);

    // Si es admin, validar que la cancha sea de SU recinto
    if ($id_recinto_admin && $id_recinto_cancha != $id_recinto_admin) {
        throw new Exception('No tienes permiso para reservar en esta cancha');
    }

    // === 3. RESOLVER SOCIO (EXISTENTE O NUEVO) ===
    $id_socio_final = null;
    $nombre_cliente = '';
    $email_cliente = '';
    $telefono_cliente = '';
    $id_club_reserva = null;

    if ($id_socio_input) {
        // SOCIO EXISTENTE
        $stmt_s = $pdo->prepare("SELECT id_socio, nombre, email, celular, id_club FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio_input]);
        $socio_data = $stmt_s->fetch(PDO::FETCH_ASSOC);
        
        if ($socio_data) {
            $id_socio_final = $socio_data['id_socio'];
            $nombre_cliente = $socio_data['nombre'];
            $email_cliente = $socio_data['email'];
            $telefono_cliente = $socio_data['celular'];
            $id_club_reserva = $socio_data['id_club']; // Obtener club del socio
        }
    } elseif (!empty($email_nuevo) && !empty($nombre_nuevo)) {
        // CREAR NUEVO SOCIO EXPRESS
        $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt_check->execute([$email_nuevo]);
        $existente = $stmt_check->fetch();
        
        if ($existente) {
            $id_socio_final = $existente['id_socio'];
            // Actualizar datos locales con los de la BD si ya existía
            $stmt_upd = $pdo->prepare("SELECT nombre, celular FROM socios WHERE id_socio = ?");
            $stmt_upd->execute([$id_socio_final]);
            $data_upd = $stmt_upd->fetch();
            $nombre_cliente = $data_upd['nombre'];
            $telefono_cliente = $data_upd['celular'];
            $email_cliente = $email_nuevo;
        } else {
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $stmt_new = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at, email_verified) VALUES (?, ?, ?, ?, NOW(), 1)");
            $stmt_new->execute([$email_nuevo, $nombre_nuevo, $alias, $tel_nuevo]);
            
            $id_socio_final = $pdo->lastInsertId();
            $nombre_cliente = $nombre_nuevo;
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
        }
    } else {
        // Si no hay socio ni datos para crear uno, usamos datos genéricos o lanzamos error si es obligatorio
        // Para reservas de admin sin socio específico, podemos dejarlos null o usar un "Socio Casual"
        if (!$id_recinto_admin) {
             throw new Exception('Debe seleccionar un socio o registrar uno nuevo');
        }
    }

    // === 4. GENERAR FECHAS VÁLIDAS ===
    $fechas_disponibles = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    $day_names = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    
    while ($current <= $end) {
        // PHP: format('N') devuelve 1(Lun)-7(Dom). Ajustamos a 0-6 para coincidir con JS getDay()
        $php_day = ((int)$current->format('N') % 7);
        
        if ($php_day === $repeat_day) {
            $fecha_str = $current->format('Y-m-d');
            
            // Verificar disponibilidad rápida
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt_chk->execute([$id_cancha, $fecha_str, $hora_inicio]);
            
            if ($stmt_chk->fetchColumn() == 0) {
                $fechas_disponibles[] = [
                    'fecha' => $fecha_str,
                    'dia_nombre' => $day_names[$current->format('w')],
                    'dia_num' => $current->format('d')
                ];
            }
        }
        $current->modify('+1 day');
    }

    if (empty($fechas_disponibles)) {
        echo json_encode(['success' => false, 'message' => 'No hay fechas disponibles en el rango seleccionado']);
        exit;
    }

    // === 5. CREAR RESERVAS EN TRANSACCIÓN ===
    $reservas_creadas = [];
    $created = 0;
    $skipped = 0;
    $total_a_generar = count($fechas_disponibles);

    $pdo->beginTransaction();

    foreach ($fechas_disponibles as $index => $item) {
        $fecha = $item['fecha'];
        $dia_nombre = $item['dia_nombre'];
        $dia_num = $item['dia_num'];
        
        try {
            // Doble-check de disponibilidad inmediata (race condition protection)
            $stmt_chk2 = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt_chk2->execute([$id_cancha, $fecha, $hora_inicio]);
            if ($stmt_chk2->fetchColumn() > 0) { 
                $skipped++; 
                continue; 
            }

            // Calcular hora fin basada en duración
            $h_ini_parts = explode(':', $hora_inicio);
            $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
            $minutos_fin = $minutos_ini + $duracion_minutos;
            $h_fin = floor($minutos_fin / 60);
            $m_fin = $minutos_fin % 60;
            $hora_fin_calc = sprintf("%02d:%02d", $h_fin, $m_fin);
            error_log("DEBUG MONTO UNITARIO: " . $monto_unitario);
            if ($monto_unitario <= 0) {
                throw new Exception("Monto unitario inválido: $monto_unitario");
            }

            // INSERTAR RESERVA
            $stmt_ins = $pdo->prepare("
                INSERT INTO reservas (
                    id_cancha, id_club, id_socio, nombre_cliente, email_cliente, telefono_cliente,
                    fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, 
                    estado_pago, estado, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())
            ");
            
            $stmt_ins->execute([
                $id_cancha,
                $id_club_reserva,
                $id_socio_final,
                $nombre_cliente,
                $email_cliente,
                $telefono_cliente,
                $fecha,
                $hora_inicio,
                $hora_fin_calc,
                $monto_unitario, // Usamos el monto unitario calculado
                $jugadores_esperados
            ]);
            
            $id_reserva_creada = $pdo->lastInsertId();
            $created++;

            if ($stmt_ins->rowCount() === 0) {
                throw new Exception("Insert no afectó filas");
            }

            // REGISTRAR BITÁCORA
            if (function_exists('registrarLogReserva')) {
                $usuario_log = $_SESSION['recinto_usuario'] ?? ($_SESSION['nombre_completo'] ?? 'Sistema');
                
                $descripcion = "🔄 Reserva Recurrente ($created/$total_a_generar)\n";
                $descripcion .= "📅 Fecha: $fecha ($dia_nombre $dia_num)\n";
                $descripcion .= "⏰ Hora: $hora_inicio - $hora_fin_calc\n";
                $descripcion .= "💰 Monto: $" . number_format($monto_unitario, 0, ',', '.');
                
                $metadata = json_encode([
                    'tipo' => 'recurrente_item',
                    'serie_total' => $total_a_generar,
                    'indice_serie' => $created,
                    'patron_dia' => $repeat_day,
                    'rango_desde' => $start_date,
                    'rango_hasta' => $end_date
                ]);

                registrarLogReserva(
                    $pdo,
                    $id_reserva_creada,
                    'creada_recurrente',
                    $descripcion,
                    $usuario_log,
                    null,
                    $monto_unitario,
                    $metadata
                );
            }

            $reservas_creadas[] = [
                'fecha' => $fecha,
                'dia_nombre' => $dia_nombre,
                'dia_num' => $dia_num,
                'hora' => substr($hora_inicio,0,5) . '-' . substr($hora_fin_calc,0,5),
                'id_reserva' => $id_reserva_creada
            ];

        } catch (Exception $e) {
            $skipped++;
            error_log("[Recurrente] Error en $fecha: " . $e->getMessage());
        }
    }

    $pdo->commit();

    // === 6. ENVIAR EMAIL RESUMEN ===
    if ($created > 0 && $email_cliente) {
        try {
            // Construir tabla HTML
            $tabla_reservas = '';
            foreach ($reservas_creadas as $r) {
                $tabla_reservas .= "
                <tr style='border-bottom:1px solid #eee;'>
                    <td style='padding:10px; text-align:center; font-weight:600; color:#AB47BC;'>{$r['dia_nombre']}</td>
                    <td style='padding:10px; text-align:center;'>{$r['dia_num']}/" . date('m', strtotime($r['fecha'])) . "</td>
                    <td style='padding:10px; text-align:center; background:#F3E5F5; font-weight:600;'>{$r['hora']}</td>
                </tr>";
            }
            
            $total_a_pagar = $monto_unitario * $created;
            
            $email_html = "
            <div style='font-family:Arial,sans-serif;max-width:650px;margin:0 auto;background:#f9f9f9;padding:25px;border-radius:16px;'>
                <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:20px;border-radius:12px 12px 0 0;'>
                    <h2 style='margin:0;font-size:1.4rem;'>🎾 Reservas Recurrentes Confirmadas</h2>
                </div>
                <div style='background:white;padding:25px;border-radius:0 0 12px 12px;'>
                    <p style='font-size:1.1rem;margin:0 0 15px 0;'>Hola <strong>" . htmlspecialchars($nombre_cliente ?: 'Usuario') . "</strong>,</p>
                    <p style='color:#555;line-height:1.6;'>Se han creado exitosamente <strong>$created reservas recurrentes</strong>:</p>
                    
                    <div style='background:#F7FAFC;padding:15px;border-radius:10px;margin:20px 0;border-left:4px solid #AB47BC;'>
                        <p style='margin:5px 0'><strong>🏟️ Cancha:</strong> " . htmlspecialchars($cancha_data['nombre_cancha']) . "</p>
                        <p style='margin:5px 0'><strong>🔄 Patrón:</strong> Todos los " . strtolower($day_names[$repeat_day]) . ", " . substr($hora_inicio,0,5) . "-" . substr($hora_fin_calc,0,5) . " hs</p>
                        <p style='margin:5px 0'><strong>📅 Período:</strong> " . date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date)) . "</p>
                    </div>

                    <table style='width:100%;border-collapse:collapse;margin:20px 0;background:white;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
                        <thead>
                            <tr style='background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;'>
                                <th style='padding:12px;text-align:center;font-weight:600;'>Día</th>
                                <th style='padding:12px;text-align:center;font-weight:600;'>Fecha</th>
                                <th style='padding:12px;text-align:center;font-weight:600;'>Hora</th>
                            </tr>
                        </thead>
                        <tbody>$tabla_reservas</tbody>
                    </table>

                    <div style='text-align:center;padding:15px;background:#E8F5E9;border-radius:10px;margin:20px 0;'>
                        <p style='margin:0;font-size:1.1rem;color:#2E7D32;'><strong>💰 Total a pagar: $" . number_format($total_a_pagar, 0, ',', '.') . "</strong></p>
                        <p style='margin:5px 0 0 0;font-size:0.9rem;color:#555;'>$created reservas × $" . number_format($monto_unitario, 0, ',', '.') . " c/u</p>
                    </div>

                    <div style='text-align:center;margin:25px 0;'>
                        <a href='https://canchasport.com/index.php' style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:10px;display:inline-block;font-weight:600;font-size:1rem;'>📋 Ir a CanchaSport</a>
                    </div>
                </div>
            </div>";

            if (class_exists('BrevoMailer')) {
                $mail = new BrevoMailer();
                $mail->setTo($email_cliente, $nombre_cliente)
                    ->setSubject("✅ $created reservas recurrentes confirmadas - CanchaSport")
                    ->setHtmlBody($email_html)
                    ->send();
                error_log("✅ [Recurrente] Email enviado a $email_cliente");
            }
        } catch (Exception $e) {
            error_log("❌ [Recurrente] Error email: " . $e->getMessage());
        }
    }

    // === RESPUESTA JSON ===
    echo json_encode([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'mensaje' => "$created reservas creadas correctamente"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Recurrente] ERROR CRÍTICO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>