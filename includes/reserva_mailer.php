<?php
// includes/reserva_mailer.php
require_once __DIR__ . '/brevo_mailer.php';

class ReservaMailer {
    
    // ✅ Confirmación de Nueva Reserva
    public static function enviarConfirmacion($pdo, $id_reserva) {
        $stmt = $pdo->prepare("
            SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre,
                   s.email, s.nombre as nombre_socio, s.alias
            FROM reservas r
            JOIN canchas c ON r.id_cancha = c.id_cancha
            JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            WHERE r.id_reserva = ?
        ");
        $stmt->execute([$id_reserva]);
        $reserva = $stmt->fetch();
        
        if (!$reserva) return false;
        
        // Determinar destinatario
        $to_email = $reserva['email'] ?? $reserva['email_cliente'] ?? null;
        $to_name = $reserva['nombre_socio'] ?? $reserva['alias'] ?? $reserva['nombre_cliente'] ?? 'Cliente';
        
        if (!$to_email) return false;
        
        // Formatear datos
        $fecha_fmt = date('d/m/Y', strtotime($reserva['fecha']));
        $hora_ini = substr($reserva['hora_inicio'], 0, 5);
        $hora_fin = substr($reserva['hora_fin'], 0, 5);
        $iconos = [1=>'🎾', 2=>'🎾', 3=>'🏐', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
        $icono = $iconos[$reserva['id_deporte']] ?? $iconos['default'];
        $monto_fmt = '$' . number_format($reserva['monto_total'], 0, ',', '.');
        
        // Contenido del correo
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
            <div style='text-align:center;background:linear-gradient(135deg,#AB47BC,#8E24AA);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                <h2 style='margin:0;'>$icono CanchaSport</h2>
            </div>
            <p style='font-size:1.1rem;'>¡Hola <strong>$to_name</strong>!</p>
            <p>Tu reserva ha sido confirmada exitosamente:</p>
            <div style='background:white;padding:15px;border-radius:8px;border-left:4px solid #4CAF50;margin:15px 0;'>
                <p style='margin:5px 0'><strong>📍 Recinto:</strong> {$reserva['recinto_nombre']}</p>
                <p style='margin:5px 0'><strong>$icono Cancha:</strong> {$reserva['nombre_cancha']}</p>
                <p style='margin:5px 0'><strong>📅 Fecha:</strong> $fecha_fmt</p>
                <p style='margin:5px 0'><strong>⏰ Hora:</strong> $hora_ini - $hora_fin</p>
                <p style='margin:5px 0'><strong>💰 Valor:</strong> $monto_fmt</p>
            </div>
            <p style='margin-top:20px;text-align:center;'>
                <a href='https://canchasport.com' style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>
                    Ir a mi dashboard
                </a>
            </p>
            <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
            <p style='text-align:center;font-size:0.9rem;color:#888;'>
                ¿Necesitas ayuda? Responde a este correo o contáctanos en soporte@canchasport.com
            </p>
        </div>";
        
        try {
            $mail = new BrevoMailer();
            $mail->setTo($to_email, $to_name)
                 ->setSubject("$icono Confirmación de Reserva - CanchaSport")
                 ->setReplyTo('reservas@canchasport.com', 'Soporte CanchaSport')
                 ->setHtmlBody($html);
            
            $enviado = $mail->send();
            if ($enviado) {
                // Marcar como enviado en BD (opcional)
                $pdo->prepare("UPDATE reservas SET updated_at = NOW() WHERE id_reserva = ?")->execute([$id_reserva]);
            }
            return $enviado;
        } catch (Exception $e) {
            error_log("[ReservaMailer] Error confirmación: " . $e->getMessage());
            return false;
        }
    }
    
    // ✅ Notificación de Reserva Movida/Actualizada
    public static function enviarActualizacion($pdo, $id_reserva, $cambios) {
        $stmt = $pdo->prepare("
            SELECT r.*, c.nombre_cancha, rec.nombre as recinto_nombre,
                   s.email, s.nombre as nombre_socio, s.alias
            FROM reservas r
            JOIN canchas c ON r.id_cancha = c.id_cancha
            JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            WHERE r.id_reserva = ?
        ");
        $stmt->execute([$id_reserva]);
        $reserva = $stmt->fetch();
        
        if (!$reserva) return false;
        
        $to_email = $reserva['email'] ?? $reserva['email_cliente'] ?? null;
        $to_name = $reserva['nombre_socio'] ?? $reserva['alias'] ?? $reserva['nombre_cliente'] ?? 'Cliente';
        if (!$to_email) return false;
        
        $fecha_fmt = date('d/m/Y', strtotime($reserva['fecha']));
        $hora_ini = substr($reserva['hora_inicio'], 0, 5);
        $hora_fin = substr($reserva['hora_fin'], 0, 5);
        $iconos = [1=>'🎾', 2=>'🎾', 3=>'🏐', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
        $icono = $iconos[$reserva['id_deporte']] ?? $iconos['default'];
        
        // Describir cambios
        $cambios_txt = [];
        if (isset($cambios['cancha'])) $cambios_txt[] = "Cancha: {$cambios['cancha']}";
        if (isset($cambios['hora'])) $cambios_txt[] = "Hora: {$cambios['hora']}";
        if (isset($cambios['fecha'])) $cambios_txt[] = "Fecha: " . date('d/m/Y', strtotime($cambios['fecha']));
        $cambios_str = implode(', ', $cambios_txt);
        
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
            <div style='text-align:center;background:linear-gradient(135deg,#2196F3,#1565C0);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                <h2 style='margin:0;'>🔄 Actualización de Reserva</h2>
            </div>
            <p style='font-size:1.1rem;'>Hola <strong>$to_name</strong>,</p>
            <p>Tu reserva ha sido actualizada:</p>
            <div style='background:white;padding:15px;border-radius:8px;border-left:4px solid #2196F3;margin:15px 0;'>
                <p style='margin:5px 0'><strong>📍 Recinto:</strong> {$reserva['recinto_nombre']}</p>
                <p style='margin:5px 0'><strong>$icono Cancha:</strong> {$reserva['nombre_cancha']}</p>
                <p style='margin:5px 0'><strong>📅 Fecha:</strong> $fecha_fmt</p>
                <p style='margin:5px 0'><strong>⏰ Nueva Hora:</strong> $hora_ini - $hora_fin</p>
                <p style='margin:10px 0 0 0;font-size:0.9rem;color:#666;'><em>Cambios: $cambios_str</em></p>
            </div>
            <p style='margin-top:20px;text-align:center;'>
                <a href='https://canchasport.com' style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>
                    Ver mis reservas
                </a>
            </p>
        </div>";
        
        try {
            $mail = new BrevoMailer();
            $mail->setTo($to_email, $to_name)
                 ->setSubject("🔄 Tu reserva ha sido actualizada - CanchaSport")
                 ->setReplyTo('reservas@canchasport.com', 'Soporte CanchaSport')
                 ->setHtmlBody($html);
            return $mail->send();
        } catch (Exception $e) {
            error_log("[ReservaMailer] Error actualización: " . $e->getMessage());
            return false;
        }
    }
}
?>