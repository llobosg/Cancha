<?php
// cron/notificar_cuotas_vencidas.php
// Notificación de cuotas vencidas hace más de 3 días

require_once __DIR__ . '/../includes/config.php';

try {
    // Cuotas vencidas hace más de 3 días y aún pendientes
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            s.email,
            s.nombre,
            s.id_club,
            cl.nombre as club_nombre,
            cl.email_responsable,
            -- Detalle del origen
            CASE 
                WHEN c.tipo_actividad = 'reserva' THEN ca.id_deporte
                WHEN c.tipo_actividad = 'evento' THEN e.nombre
                ELSE 'Sin detalle'
            END as detalle_origen,
            CASE 
                WHEN c.tipo_actividad = 'reserva' THEN r.fecha
                WHEN c.tipo_actividad = 'evento' THEN e.fecha
                ELSE c.fecha_vencimiento
            END as fecha_origen
        FROM cuotas c
        JOIN socios s ON c.id_socio = s.id_socio
        JOIN clubs cl ON s.id_club = cl.id_club
        LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
        LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
        LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
        WHERE c.estado = 'pendiente'
        AND c.fecha_vencimiento <= CURDATE() - INTERVAL 3 DAY
        AND s.email_verified = 1
    ");
    $stmt->execute();
    $cuotas = $stmt->fetchAll();

    if (empty($cuotas)) {
        error_log("[CRON] No hay cuotas vencidas para notificar");
        exit(0);
    }

    foreach ($cuotas as $cuota) {
        try {
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo($cuota['email'], $cuota['nombre']);
            $mail->setSubject('⚠️ Cuota vencida en ' . $cuota['club_nombre']);
            $mail->setHtmlBody("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                    <div style='text-align: center; background: #E74C3C; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2>⚠️ Cuota Vencida</h2>
                    </div>
                    <p style='font-size: 1.1rem; line-height: 1.5;'>
                        ¡Hola {$cuota['nombre']}!
                    </p>
                    <p>Tienes una cuota pendiente en el club <strong>{$cuota['club_nombre']}</strong>:</p>
                    
                    <p style='background: #fff; padding: 12px; border-radius: 8px; margin: 15px 0; font-weight: bold;'>
                        <strong>Detalle:</strong> {$cuota['detalle_origen']}<br>
                        <strong>Fecha:</strong> " . date('d/m/Y', strtotime($cuota['fecha_origen'])) . "<br>
                        <strong>Monto:</strong> $" . number_format($cuota['monto'], 0, ',', '.') . "
                    </p>

                    <p>Por favor regulariza tu situación para no perder acceso a los eventos.</p>
                    <p style='margin-top: 20px; text-align: center;'>
                        <a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}' 
                        style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                            Pagar ahora
                        </a>
                    </p>
                    <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
                    <p style='text-align: center; font-size: 0.9rem; color: #888;'>
                        Este mensaje fue generado automáticamente.
                    </p>
                </div>
            ");
            $mail->send();
            error_log("[CRON] Notificación de cuota enviada a {$cuota['email']} (ID: {$cuota['id_cuota']})");
        } catch (Exception $e) {
            error_log("[CRON] Error al enviar notificación a {$cuota['email']}: " . $e->getMessage());
        }
    }

    error_log("[CRON] Cuotas vencidas notificadas: " . count($cuotas));
} catch (Exception $e) {
    error_log("[CRON] Error fatal en notificar_cuotas_vencidas.php: " . $e->getMessage());
    exit(1);
}
?>