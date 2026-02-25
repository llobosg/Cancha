<?php
// cron/bienvenida_club.php
// Envía correo de bienvenida a nuevos socios (solo una vez)

require_once __DIR__ . '/../includes/config.php';

try {
    // Buscar socios recién registrados que aún no han recibido bienvenida
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio,
            s.email,
            s.nombre,
            s.id_club,
            c.nombre as club_nombre,
            c.email_responsable
        FROM socios s
        LEFT JOIN clubs c ON s.id_club = c.id_club
        LEFT JOIN logs_notificaciones ln ON s.id_socio = ln.id_socio AND ln.tipo = 'bienvenida'
        WHERE ln.id_socio IS NULL
          AND s.email_verified = 1
          AND s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $nuevos_socios = $stmt->fetchAll();

    if (empty($nuevos_socios)) {
        error_log("[CRON] No hay nuevos socios para enviar bienvenida");
        exit(0);
    }

    foreach ($nuevos_socios as $socio) {
        try {
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo($socio['email'], $socio['nombre']);
            
            if ($socio['id_club']) {
                // Socio de club
                $club_slug = substr(md5($socio['id_club'] . $socio['email_responsable']), 0, 8);
                $asunto = '⚽ ¡Bienvenido a ' . $socio['club_nombre'] . ' en CanchaSport!';
                $mensaje = "
                    <p>¡Hola {$socio['nombre']}!</p>
                    <p>Te damos la más cordial bienvenida al club <strong>{$socio['club_nombre']}</strong> en CanchaSport.</p>
                    <p>A partir de ahora podrás:</p>
                    <ul>
                        <li>Confirmar tu asistencia a partidos y eventos</li>
                        <li>Pagar tus cuotas de forma segura</li>
                        <li>Recibir notificaciones en tiempo real</li>
                        <li>Participar activamente en la vida del club</li>
                    </ul>
                    <p><a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}' 
                       style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        Ir a tu dashboard
                    </a></p>
                ";
            } else {
                // Socio individual
                $asunto = '⚽ ¡Bienvenido a CanchaSport como socio individual!';
                $mensaje = "
                    <p>¡Hola {$socio['nombre']}!</p>
                    <p>Gracias por unirte a CanchaSport como socio individual.</p>
                    <p>Aquí podrás gestionar tus partidos, reservar canchas y participar en la comunidad deportiva multi-deporte.</p>
                    <p><a href='https://canchasport.com/pages/dashboard_socio.php' 
                       style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        Ir a tu dashboard
                    </a></p>
                ";
            }

            $mail->setSubject($asunto);
            $mail->setHtmlBody("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                    <div style='text-align: center; background: #071289; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2>🔔 CanchaSport</h2>
                    </div>
                    {$mensaje}
                    <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
                    <p style='text-align: center; font-size: 0.9rem; color: #888;'>
                        Este mensaje fue generado automáticamente. Por favor, no respondas a este correo.
                    </p>
                </div>
            ");
            $mail->send();

            // Registrar en log para evitar reenvíos
            $stmt_log = $pdo->prepare("
                INSERT INTO logs_notificaciones (id_socio, tipo, fecha_envio)
                VALUES (?, 'bienvenida', NOW())
            ");
            $stmt_log->execute([$socio['id_socio']]);

            error_log("[CRON] Bienvenida enviada a {$socio['email']} (ID: {$socio['id_socio']})");
        } catch (Exception $e) {
            error_log("[CRON] Error al enviar bienvenida a {$socio['email']}: " . $e->getMessage());
        }
    }

    error_log("[CRON] Bienvenidas procesadas: " . count($nuevos_socios));
} catch (Exception $e) {
    error_log("[CRON] Error fatal en bienvenida_club.php: " . $e->getMessage());
    exit(1);
}
?>