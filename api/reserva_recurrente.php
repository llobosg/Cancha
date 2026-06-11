<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/bitacora.php')) {
    require_once __DIR__ . '/../includes/bitacora.php';
} else {
    error_log("❌ ERROR CRÍTICO: No se encuentra includes/bitacora.php");
}
if (file_exists(__DIR__ . '/../includes/brevo_mailer.php')) require_once __DIR__ . '/../includes/brevo_mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
error_log("[Recurrente] Input recibido: " . json_encode($input));

$id_recinto_admin = $_SESSION['id_recinto'] ?? null;
if (!$id_recinto_admin && !isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    // Validaciones
    if (empty($input['id_cancha']) || empty($input['start_date']) || empty($input['end_date'])) {
        throw new Exception("Datos incompletos");
    }

    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $duracion_minutos = intval($input['duracion_bloque'] ?? 60);
    $repeat_day = (int)$input['repeat_day']; 
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $monto_unitario = floatval($input['monto_total'] ?? 0);
    
    // Resolver Socio
    $id_socio_final = $input['id_socio'] ?? $_SESSION['id_socio'] ?? null;
    $nombre_cliente = ''; $email_cliente = ''; $telefono_cliente = '';

    if ($id_socio_final) {
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio_final]);
        $s = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nombre_cliente = $s['nombre']; $email_cliente = $s['email']; 
            $telefono_cliente = $s['celular'];
        }
    } elseif (!empty($input['emailNuevoSocio'])) {
        $email_nuevo = $input['emailNuevoSocio'];
        $nombre_nuevo = $input['nombreNuevoSocio'] ?? 'Nuevo Socio';
        $stmt_chk = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt_chk->execute([$email_nuevo]);
        $ex = $stmt_chk->fetch();
        if ($ex) {
            $id_socio_final = $ex['id_socio'];
            $email_cliente = $email_nuevo;
        } else {
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $stmt_ins = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_ins->execute([$email_nuevo, $nombre_nuevo, $alias, $input['telNuevoSocio'] ?? '']);
            $id_socio_final = $pdo->lastInsertId();
            $email_cliente = $email_nuevo; $nombre_cliente = $nombre_nuevo;
        }
    }

    // Generar Fechas
    $fechas_disponibles = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    $day_names = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    
    while ($current <= $end) {
        $php_day = ((int)$current->format('N') % 7); // 0=Dom, 1=Lun...
        if ($php_day === $repeat_day) {
            $fecha_str = $current->format('Y-m-d');
            // Check disponibilidad
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt_chk->execute([$id_cancha, $fecha_str, $hora_inicio]);
            if ($stmt_chk->fetchColumn() == 0) {
                $fechas_disponibles[] = ['fecha' => $fecha_str, 'dia_nombre' => $day_names[$current->format('w')], 'dia_num' => $current->format('d')];
            }
        }
        $current->modify('+1 day');
    }

    if (empty($fechas_disponibles)) {
        throw new Exception("No hay fechas disponibles");
    }

    error_log("[Recurrente] Fechas a procesar: " . count($fechas_disponibles));

    // Transacción
    $pdo->beginTransaction();
    $created = 0;
    $tabla_html = '';
    $hora_fin_calc = '';

    // 1. Leer contexto del club desde el payload JSON
    $tipo_reserva = $data['tipo_reserva'] ?? 'individual';
    $id_club_reserva = $data['id_club_reserva'] ?? null;

    // 2. Validar si el socio es responsable (Misma lógica que reserva_unica)
    $id_club_final = null;
    if ($id_socio && $tipo_reserva === 'club' && !empty($id_club_reserva)) {
        $stmt_rol = $pdo->prepare("SELECT COUNT(*) FROM socio_club WHERE id_socio = ? AND id_club = ? AND es_responsable = 1");
        $stmt_rol->execute([$id_socio, (int)$id_club_reserva]);
        
        if ($stmt_rol->fetchColumn() > 0) {
            $id_club_final = (int)$id_club_reserva;
            error_log("[RECURRENTE] Socio $id_socio reservando como RESPONSABLE del Club $id_club_final");
        } else {
            error_log("[RECURRENTE] ⚠️ Socio $id_socio intentó reservar para Club $id_club_reserva sin ser responsable.");
        }
    }

    foreach ($fechas_disponibles as $item) {
        $fecha = $item['fecha'];
        $dia_nombre = $item['dia_nombre'];
        $dia_num = $item['dia_num'];
        
        // Calcular Hora Fin
        $h_ini_parts = explode(':', $hora_inicio);
        $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
        $minutos_fin = $minutos_ini + $duracion_minutos;
        $hora_fin_calc = sprintf("%02d:%02d", floor($minutos_fin / 60), $minutos_fin % 60);

        $stmt = $pdo->prepare("
            INSERT INTO reservas (
                id_cancha, 
                id_club,
                id_socio, 
                nombre_cliente, email_cliente, telefono_cliente,
                fecha, hora_inicio, hora_fin,
                monto_total, jugadores_esperados, estado_pago, estado, tipo_reserva
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4, 'pendiente', 'confirmada', ?)
        ");

        $stmt->execute([
            $data['id_cancha'],
            $id_club_final,
            $id_socio,
            $socio['nombre'],
            $socio['email'],
            $socio['celular'],
            $fecha,
            $data['hora_inicio'],
            $hora_fin,
            $monto,
            $jugadores_esperados,
            'semanal'
        ]);
        
        $id_res = $pdo->lastInsertId();
        $created++;
        
        // Construir fila de tabla para email
        $mes_fecha = date('m', strtotime($fecha));
        $tabla_html .= "
        <tr style='border-bottom:1px solid #eee;'>
            <td style='padding:10px; text-align:center; font-weight:600; color:#AB47BC;'>{$dia_nombre}</td>
            <td style='padding:10px; text-align:center;'>{$dia_num}/{$mes_fecha}</td>
            <td style='padding:10px; text-align:center; background:#F3E5F5; font-weight:600;'>{$hora_inicio}-{$hora_fin_calc}</td>
        </tr>";

        // ✅ REGISTRAR EN BITÁCORA INDIVIDUAL
        if (function_exists('registrarLogReserva')) {
            $usuario_log = $_SESSION['recinto_usuario'] ?? ($_SESSION['nombre_completo'] ?? 'Admin');
            
            $descripcion = "🔄 Reserva Recurrente Creada\n";
            $descripcion .= "📅 Fecha: $fecha ($dia_nombre $dia_num)\n";
            $descripcion .= "⏰ Hora: $hora_inicio - $hora_fin_calc\n";
            $descripcion .= "💰 Monto: $" . number_format($monto_unitario, 0, ',', '.');
            
            $metadata = [
                'tipo' => 'recurrente',
                'rango_desde' => $start_date,
                'rango_hasta' => $end_date,
                'dia_semana' => $repeat_day
            ];

            // Llamada a la función
            $log_result = registrarLogReserva(
                $pdo,
                $id_res,
                'creada_recurrente',
                $descripcion,
                $usuario_log,
                null, // monto_anterior
                $monto_unitario, // monto_nuevo
                $metadata
            );

            if ($log_result === false) {
                error_log("⚠️ Bitácora falló para reserva ID: $id_res");
            }
        } else {
            error_log("❌ ERROR: Función registrarLogReserva NO existe. Revisa include_path.");
        }
    }

    $pdo->commit();
    error_log("[Recurrente] Commit exitoso. Creadas: $created");

    // === ENVIAR EMAIL RESUMEN ===
    if ($created > 0 && $email_cliente && class_exists('BrevoMailer')) {
        try {
            $stmt_c = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
            $stmt_c->execute([$id_cancha]);
            $nombre_cancha = $stmt_c->fetchColumn() ?: 'Cancha';
            
            $total_a_pagar = $monto_unitario * $created;
            $dia_semana_texto = strtolower($day_names[$repeat_day]);

            $email_html = "
            <div style='font-family:Arial,sans-serif;max-width:650px;margin:0 auto;background:#f9f9f9;padding:25px;border-radius:16px;'>
                <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:20px;border-radius:12px 12px 0 0;'>
                    <h2 style='margin:0;font-size:1.4rem;'>🎾 Reservas Recurrentes Confirmadas</h2>
                </div>
                <div style='background:white;padding:25px;border-radius:0 0 12px 12px;'>
                    <p style='font-size:1.1rem;margin:0 0 15px 0;'>Hola <strong>" . htmlspecialchars($nombre_cliente ?: 'Usuario') . "</strong>,</p>
                    <p style='color:#555;line-height:1.6;'>Se han creado exitosamente <strong>$created reservas recurrentes</strong> con el siguiente detalle:</p>
                    
                    <div style='background:#F7FAFC;padding:15px;border-radius:10px;margin:20px 0;border-left:4px solid #AB47BC;'>
                        <p style='margin:5px 0'><strong>🏟️ Cancha:</strong> " . htmlspecialchars($nombre_cancha) . "</p>
                        <p style='margin:5px 0'><strong>🔄 Patrón:</strong> Todos los {$dia_semana_texto}, {$hora_inicio}-{$hora_fin_calc} hs</p>
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
                        <tbody>$tabla_html</tbody>
                    </table>

                    <div style='text-align:center;padding:15px;background:#E8F5E9;border-radius:10px;margin:20px 0;'>
                        <p style='margin:0;font-size:1.1rem;color:#2E7D32;'><strong>💰 Total a pagar: $" . number_format($total_a_pagar, 0, ',', '.') . "</strong></p>
                        <p style='margin:5px 0 0 0;font-size:0.9rem;color:#555;'>$created reservas × $" . number_format($monto_unitario, 0, ',', '.') . " c/u</p>
                    </div>

                    <div style='text-align:center;margin:25px 0;'>
                        <a href='https://canchasport.com/index.php' style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:10px;display:inline-block;font-weight:600;font-size:1rem;'>📋 Ir a CanchaSport</a>
                    </div>

                    <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
                    <p style='text-align:center;font-size:0.85rem;color:#888;margin:0;'>
                        ¿Necesitas modificar o cancelar alguna reserva? Contáctanos en <a href='mailto:contacto@canchasport.com' style='color:#AB47BC;'>contacto@canchasport.com</a>
                    </p>
                </div>
            </div>";

            $mail = new BrevoMailer();
            $mail->setTo($email_cliente, $nombre_cliente)
                ->setSubject("✅ $created reservas recurrentes confirmadas - CanchaSport")
                ->setHtmlBody($email_html)
                ->send();
                
            error_log("✅ [Recurrente] Email enviado a $email_cliente");
        } catch (Exception $e) {
            error_log("❌ [Recurrente] Error email: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'created' => $created]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Recurrente] ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>