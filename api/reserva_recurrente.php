<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // ← BrevoMailer

if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_recinto = (int)$_SESSION['id_recinto'];

try {
    // === VALIDACIÓN DE DATOS ===
    $required = ['id_cancha', 'hora_inicio', 'hora_fin', 'repeat_day', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) throw new Exception("Campo requerido: $field");
    }
    
    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $hora_fin = $input['hora_fin'];
    $repeat_day = (int)$input['repeat_day']; // 0=Dom, 1=Lun, ..., 6=Sáb
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $id_socio = $input['id_socio'] ?? null;
    $monto_total = floatval($input['monto_total'] ?? 0);
    $jugadores = intval($input['jugadores_esperados'] ?? 4);
    
    // Verificar cancha pertenece al recinto
    $stmt = $pdo->prepare("SELECT id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    if ($stmt->fetchColumn() != $id_recinto) {
        throw new Exception('Cancha no pertenece a este recinto');
    }
    
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
    $reservas_creadas = [];
    $created = 0;
    $skipped = 0;
    $pdo->beginTransaction();
    
    foreach ($fechas_disponibles as $item) {
        $fecha = $item['fecha'];
        try {
            // Doble-check de disponibilidad (concurrencia)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
            if ($stmt->fetchColumn() > 0) { $skipped++; continue; }
            
            // Insertar reserva
            $stmt = $pdo->prepare("INSERT INTO reservas (id_cancha, id_socio, fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, estado_pago, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())");
            $stmt->execute([$id_cancha, $id_socio, $fecha, $hora_inicio, $hora_fin, $monto_total, $jugadores]);
            
            $reservas_creadas[] = [
                'fecha' => $fecha,
                'dia_nombre' => $item['dia_nombre'],
                'dia_num' => $item['dia_num'],
                'hora' => substr($hora_inicio,0,5) . '-' . substr($hora_fin,0,5),
                'id_reserva' => $pdo->lastInsertId()
            ];
            $created++;
        } catch (Exception $e) {
            $skipped++;
            error_log("[Recurrente] Error en $fecha: " . $e->getMessage());
        }
    }
    $pdo->commit();
    
    // === 📧 ENVIAR EMAIL RESUMEN (SOLO SI HAY SOCIO Y SE CREÓ AL MENOS 1) ===
    if ($created > 0 && $id_socio) {
        // Obtener datos del socio y cancha
        $stmt_socio = $pdo->prepare("SELECT email, nombre, alias FROM socios WHERE id_socio = ?");
        $stmt_socio->execute([$id_socio]);
        $socio = $stmt_socio->fetch();
        
        $stmt_cancha = $pdo->prepare("SELECT nombre_cancha, id_deporte FROM canchas WHERE id_cancha = ?");
        $stmt_cancha->execute([$id_cancha]);
        $cancha = $stmt_cancha->fetch();
        
        if ($socio && $socio['email'] && $cancha) {
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
            $icono = $iconos[$cancha['id_deporte']] ?? $iconos['default'];
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
                        <p style='margin:5px 0'><strong>🏟️ Cancha:</strong> " . htmlspecialchars($cancha['nombre_cancha']) . "</p>
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
                error_log("⚠️ [Recurrente] BrevoMailer falló al enviar resumen a {$socio['email']}");
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