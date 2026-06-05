<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // ← BrevoMailer
require_once __DIR__ . '/../includes/bitacora.php'; // ← Bitácora

$input = json_decode(file_get_contents('php://input'), true);

// === 1. DETECTAR TIPO DE USUARIO (SOCIO O ADMIN) ===
$id_socio = $_SESSION['id_socio'] ?? null;
$id_recinto_admin = $_SESSION['id_recinto'] ?? null;

if (!$id_socio && !$id_recinto_admin) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // === VALIDACIÓN DE DATOS ===
    $required = ['id_cancha', 'hora_inicio', 'hora_fin', 'repeat_day', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) throw new Exception("Campo requerido: $field");
    }

    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $hora_fin = $input['hora_fin'];
    $repeat_day = (int)$input['repeat_day']; 
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    
    // El ID de socio puede venir explícito (si es admin creando para otro) o de la sesión
    $id_socio_reserva = $input['id_socio'] ?? $id_socio; 
    
    $monto_total = floatval($input['monto_total'] ?? 0);
    $jugadores = intval($input['jugadores_esperados'] ?? 4);

    // === 2. VERIFICAR CANCHA Y OBTENER SU RECINTO ===
    $stmt = $pdo->prepare("SELECT id_recinto, nombre_cancha, valor_arriendo FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    $cancha_data = $stmt->fetch();

    if (!$cancha_data) {
        throw new Exception('La cancha seleccionada no existe');
    }

    $id_recinto_cancha = $cancha_data['id_recinto'];

    // Si es admin, validar que la cancha sea de SU recinto
    if ($id_recinto_admin && $id_recinto_cancha != $id_recinto_admin) {
        throw new Exception('No tienes permiso para reservar en esta cancha');
    }

    // Si es socio, usamos el recinto de la cancha para lógica interna si fuera necesario
    // (Por ahora, solo validamos que la cancha exista y esté activa)

    // ... [RESTO DEL CÓDIGO DE GENERACIÓN DE FECHAS Y CREACIÓN DE RESERVAS SE MANTIENE IGUAL] ...
    
    // === GENERAR FECHAS VÁLIDAS ===
    $fechas_disponibles = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    $day_names = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    
    while ($current <= $end) {
        // PHP: format('N') devuelve 1(Lun)-7(Dom), ajustamos a 0-6
        $php_day = ((int)$current->format('N') % 7);
        
        if ($php_day === $repeat_day) {
            $fecha_str = $current->format('Y-m-d');
            
            // Verificar disponibilidad
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt->execute([$id_cancha, $fecha_str, $hora_inicio]);
            
            if ($stmt->fetchColumn() == 0) {
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

    // === CREAR RESERVAS + RECOPILAR DATOS PARA EMAIL ===
    // ... código anterior de generación de fechas ...

    $reservas_creadas = [];
    $created = 0; // Contador de reservas exitosas
    $skipped = 0;
    $total_a_generar = count($fechas_disponibles);

    $pdo->beginTransaction();

    foreach ($fechas_disponibles as $index => $item) {
        $fecha = $item['fecha'];
        $dia_nombre = $item['dia_nombre'];
        $dia_num = $item['dia_num'];
        
        // === 1. MANEJAR CREACIÓN DE SOCIO NUEVO (Solo una vez al inicio si es necesario) ===
        // Si no hay ID de socio y hay email nuevo, creamos el socio antes del loop o aquí si es dinámico.
        // Asumimos que $id_socio ya está resuelto o se crea aquí si es nuevo.
        $id_socio_final = $id_socio; 
        if (!$id_socio_final && !empty($input['emailNuevoSocio'])) {
            // Lógica de creación de socio (igual a la que ya tienes)
            $email_nuevo = trim($input['emailNuevoSocio']);
            $nombre_nuevo = trim($input['nombreNuevoSocio'] ?? 'Nuevo Socio');
            $tel_nuevo = trim($input['telNuevoSocio'] ?? '');
            
            if ($email_nuevo) {
                $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
                $stmt->execute([$email_nuevo]);
                $existente = $stmt->fetch();
                
                if ($existente) {
                    $id_socio_final = $existente['id_socio'];
                } else {
                    $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
                    $stmt = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$email_nuevo, $nombre_nuevo, $alias, $tel_nuevo]);
                    $id_socio_final = $pdo->lastInsertId();
                }
            }
        }

        try {
            // Doble-check de disponibilidad inmediata antes de insertar
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
            if ($stmt->fetchColumn() > 0) { 
                $skipped++; 
                continue; 
            }

            // Calcular hora fin basada en duración si no viene explícita o para asegurar consistencia
            // Asumimos que $hora_fin ya viene calculada desde el frontend o la calculamos aquí
            $duracion_minutos = intval($input['duracion_bloque'] ?? 60);
            $h_ini_parts = explode(':', $hora_inicio);
            $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
            $minutos_fin = $minutos_ini + $duracion_minutos;
            $h_fin = floor($minutos_fin / 60);
            $m_fin = $minutos_fin % 60;
            $hora_fin_calc = sprintf("%02d:%02d", $h_fin, $m_fin);

            // === 2. INSERTAR RESERVA INDIVIDUAL ===
            $stmt = $pdo->prepare("INSERT INTO reservas (id_cancha, id_socio, fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, estado_pago, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())");
            $stmt->execute([
                $id_cancha,
                $id_socio_final,
                $fecha,
                $hora_inicio,
                $hora_fin_calc, // Usamos la calculada para precisión
                $monto_total,
                $jugadores
            ]);
            
            $id_reserva_creada = $pdo->lastInsertId();
            $created++; // Incrementamos contador ÉXITOSO

            // === 3. REGISTRAR LOG INDIVIDUAL EN BITÁCORA ===
            if (function_exists('registrarLogReserva')) {
                $usuario_log = $_SESSION['recinto_usuario'] ?? ($_SESSION['nombre_completo'] ?? 'Sistema');
                
                // Descripción detallada para este ítem específico
                $descripcion = "🔄 Reserva Recurrente (Ítem $created/$total_a_generar)\n";
                $descripcion .= "📅 Fecha: $fecha ($dia_nombre $dia_num)\n";
                $descripcion .= "⏰ Hora: $hora_inicio - $hora_fin_calc\n";
                $descripcion .= "💰 Monto: $" . number_format($monto_total, 0, ',', '.');
                
                // Metadata útil para filtrado futuro
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
                    'creada_recurrente', // Acción específica
                    $descripcion,
                    $usuario_log,
                    null, // monto_anterior
                    $monto_total, // monto_nuevo
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
            // Opcional: Rollback parcial si falla uno, pero usualmente continuamos con los demás
        }
    }

    $pdo->commit();

    // === ENVIAR EMAIL RESUMEN (SOLO SI HAY SOCIO Y SE CREÓ AL MENOS 1) ===
    if ($created > 0 && $id_socio_final) {
        // Obtener datos del socio y cancha
        $stmt_socio = $pdo->prepare("SELECT email, nombre, alias FROM socios WHERE id_socio = ?");
        $stmt_socio->execute([$id_socio_final]);
        $socio = $stmt_socio->fetch();
        
        if ($socio && $socio['email'] && $cancha_data) {
            // Construir tabla HTML de reservas
            $tabla_reservas = '';
            foreach ($reservas_creadas as $r) {
                $tabla_reservas .= "
                <tr style='border-bottom:1px solid #eee;'>
                    <td style='padding:10px; text-align:center; font-weight:600; color:#AB47BC;'>{$r['dia_nombre']}</td>
                    <td style='padding:10px; text-align:center;'>{$r['dia_num']}/" . date('m', strtotime($r['fecha'])) . "</td>
                    <td style='padding:10px; text-align:center; background:#F3E5F5; font-weight:600;'>{$r['hora']}</td>
                </tr>";
            }
            
            $iconos = ['futbol'=>'⚽','futbolito'=>'⚽','futsal'=>'⚽','tenis'=>'🎾','padel'=>'🎾','voleyball'=>'🏐','default'=>'🏟️'];
            $icono = $iconos['padel'] ?? $iconos['default']; // Asumimos padel por defecto o podrías pasar id_deporte
            
            $total_a_pagar = $monto_total * $created;
            
            $email_html = "
            <div style='font-family:Arial,sans-serif;max-width:650px;margin:0 auto;background:#f9f9f9;padding:25px;border-radius:16px;'>
                <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:20px;border-radius:12px 12px 0 0;'>
                    <h2 style='margin:0;font-size:1.4rem;'>$icono Reservas Recurrentes Confirmadas</h2>
                </div>
                <div style='background:white;padding:25px;border-radius:0 0 12px 12px;'>
                    <p style='font-size:1.1rem;margin:0 0 15px 0;'>Hola <strong>" . htmlspecialchars($socio['alias'] ?: $socio['nombre']) . "</strong>,</p>
                    <p style='color:#555;line-height:1.6;'>Se han creado exitosamente <strong>$created reservas recurrentes</strong> con el siguiente detalle:</p>
                    
                    <div style='background:#F7FAFC;padding:15px;border-radius:10px;margin:20px 0;border-left:4px solid #AB47BC;'>
                        <p style='margin:5px 0'><strong>🏟️ Cancha:</strong> " . htmlspecialchars($cancha_data['nombre_cancha']) . "</p>
                        <p style='margin:5px 0'><strong>🔄 Patrón:</strong> Todos los " . strtolower($day_names[$repeat_day]) . ", " . substr($hora_inicio,0,5) . "-" . substr($hora_fin,0,5) . " hs</p>
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
                        <p style='margin:5px 0 0 0;font-size:0.9rem;color:#555;'>$created reservas × $" . number_format($monto_total, 0, ',', '.') . " c/u</p>
                    </div>

                    <div style='text-align:center;margin:25px 0;'>
                        <a href='https://canchasport.com/pages/dashboard_socio.php' style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:10px;display:inline-block;font-weight:600;font-size:1rem;'>📋 Ver mis reservas</a>
                    </div>

                    <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
                    <p style='text-align:center;font-size:0.85rem;color:#888;margin:0;'>
                        ¿Necesitas modificar o cancelar alguna reserva? Contáctanos en <a href='mailto:contacto@canchasport.com' style='color:#AB47BC;'>contacto@canchasport.com</a>
                    </p>
                </div>
            </div>";

            // Enviar con BrevoMailer
            $mail = new BrevoMailer();
            $sent = $mail
                ->setTo($socio['email'], $socio['alias'] ?: $socio['nombre'])
                ->setSubject("✅ $created reservas recurrentes confirmadas - CanchaSport")
                ->setReplyTo('contacto@canchasport.com', 'Soporte CanchaSport')
                ->setHtmlBody($email_html)
                ->send();
                
            if ($sent) {
                error_log("✅ [Recurrente] Email resumen enviado a {$socio['email']} | $created reservas");
            } else {
                error_log("❌ [Recurrente] BrevoMailer falló al enviar resumen a {$socio['email']}");
            }
        }
    }

    // === RESPUESTA JSON ===
    echo json_encode([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'fechas' => array_map(fn($r) => $r['fecha'], array_slice($reservas_creadas, 0, 5)),
        'mensaje' => $created > 0 ? "$created reservas creadas" : "No se pudo crear ninguna reserva"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Recurrente] ERROR CRÍTICO: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>