<?php
// cron/recordatorio_eventos.php
// Recordatorio de eventos 24h antes

require_once __DIR__ . '/../includes/config.php';

try {
    // Eventos que comienzan en las próximas 24 horas
    $stmt = $pdo->prepare("
        SELECT 
            r.id_reserva, 
            r.id_club, 
            r.fecha, 
            r.hora_inicio, 
            c.nombre as club_nombre,
            c.email_responsable
        FROM reservas r
        JOIN clubs c ON r.id_club = c.id_club
        WHERE r.fecha = CURDATE() + INTERVAL 1 DAY
          AND r.estado = 'confirmada'
    ");
    $stmt->execute();
    $eventos = $stmt->fetchAll();

    if (empty($eventos)) {
        error_log("[CRON] No hay eventos para recordar hoy");
        exit(0);
    }

    foreach ($eventos as $evento) {
        // Obtener socios inscritos
        $stmt_socios = $pdo->prepare("
            SELECT s.id_socio, s.email, s.nombre
            FROM inscritos i
            JOIN socios s ON i.id_socio = s.id_socio
            WHERE i.id_evento = ?
        ");
        $stmt_socios->execute([$evento['id_reserva']]);
        $socios = $stmt_socios->fetchAll();

        if (empty($socios)) continue;

        // Recalcular club_slug
        $club_slug = substr(md5($evento['id_club'] . $evento['email_responsable']), 0, 8);

        foreach ($socios as $socio) {
            try {
                require_once __DIR__ . '/../includes/brevo_mailer.php';
                $mail = new BrevoMailer();
                $mail->setTo($socio['email'], $socio['nombre']);
                $mail->setSubject('⚽ Recordatorio: Partido mañana en ' . $evento['club_nombre']);
                $mail->setHtmlBody("
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                        <div style='text-align: center; background: #071289; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                            <h2>🔔 CanchaSport</h2>
                        </div>
                        <p style='font-size: 1.1rem; line-height: 1.5;'>
                            ¡Hola {$socio['nombre']}!
                        </p>
                        <p>Te recordamos que tienes un partido mañana:</p>
                        <p>
                            <strong>Club:</strong> {$evento['club_nombre']}<br>
                            <strong>Fecha:</strong> " . date('d/m', strtotime($evento['fecha'])) . "<br>
                            <strong>Hora:</strong> {$evento['hora_inicio']}
                        </p>
                        <p style='margin-top: 20px; text-align: center;'>
                            <a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}' 
                               style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                                Ver detalles
                            </a>
                        </p>
                        <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
                        <p style='text-align: center; font-size: 0.9rem; color: #888;'>
                            Este mensaje fue generado automáticamente.
                        </p>
                    </div>
                ");
                $mail->send();
                error_log("[CRON] Email enviado a {$socio['email']} para evento {$evento['id_reserva']}");
            } catch (Exception $e) {
                error_log("[CRON] Error al enviar email a {$socio['email']}: " . $e->getMessage());
            }
        }
    }

    error_log("[CRON] Recordatorios procesados: " . count($eventos) . " eventos");
} catch (Exception $e) {
    error_log("[CRON] Error fatal en recordatorio_eventos.php: " . $e->getMessage());
    exit(1);
}
?>