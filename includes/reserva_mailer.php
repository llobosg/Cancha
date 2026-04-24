// === NUEVA FUNCIÓN: Enviar actualización con datos ya actualizados ===
public static function enviarActualizacionConDatos($pdo, $reserva, $cambios) {
    $to_email = $reserva['email'] ?? $reserva['email_cliente'] ?? null;
    $to_name = $reserva['nombre_socio'] ?? $reserva['alias'] ?? $reserva['nombre_cliente'] ?? 'Cliente';
    if (!$to_email) return false;
    
    $fecha_fmt = date('d/m/Y', strtotime($reserva['fecha']));
    $hora_ini = substr($reserva['hora_inicio'], 0, 5);
    $hora_fin = substr($reserva['hora_fin'], 0, 5);
    $iconos = [1=>'🎾', 2=>'🎾', 3=>'🏐', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
    $icono = $iconos[$reserva['id_deporte']] ?? $iconos['default'];
    
    $cambios_str = implode(', ', array_map(function($k, $v) {
        return "$k: $v";
    }, array_keys($cambios), $cambios)) ?: 'Horario/Cancha';
    
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
            <p style='margin:5px 0'><strong>📅 Nueva Fecha:</strong> $fecha_fmt</p>
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