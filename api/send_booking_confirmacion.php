<?php
// api/send_booking_confirmation.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_reserva = $data['id_reserva'] ?? null;
    
    if (!$id_reserva) throw new Exception('ID de reserva requerido');
    
    // Obtener datos de la reserva
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre,
               s.email, s.nombre as nombre_socio
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $reserva = $stmt->fetch();
    
    if (!$reserva) throw new Exception('Reserva no encontrada');
    
    // Determinar destinatario: socio registrado o cliente manual
    $to_email = $reserva['email'] ?? $reserva['email_cliente'] ?? null;
    $to_name = $reserva['nombre_socio'] ?? $reserva['nombre_cliente'] ?? 'Cliente';
    
    if (!$to_email) throw new Exception('No hay email para enviar confirmación');
    
    // Formatear hora
    $hora_inicio = substr($reserva['hora_inicio'], 0, 5);
    $hora_fin = substr($reserva['hora_fin'], 0, 5);
    
    // Icono según deporte
    $iconos = [1=>'🎾', 2=>'🎾', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
    $icono = $iconos[$reserva['id_deporte']] ?? $iconos['default'];
    
    // Enviar correo
    $mail = new BrevoMailer();
    $mail->setTo($to_email, $to_name)
         ->setSubject("$icono Confirmación de Reserva - CanchaSport")
         ->setReplyTo('reservas@canchasport.com', 'Soporte CanchaSport')
         ->setHtmlBody("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                <div style='text-align: center; background: linear-gradient(135deg, #AB47BC, #8E24AA); color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <h2 style='margin:0;'>$icono CanchaSport</h2>
                </div>
                <p style='font-size: 1.1rem;'>¡Hola <strong>$to_name</strong>!</p>
                <p>Tu reserva ha sido confirmada exitosamente:</p>
                <div style='background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #4CAF50; margin: 15px 0;'>
                    <p style='margin:5px 0'><strong>📍 Recinto:</strong> {$reserva['recinto_nombre']}</p>
                    <p style='margin:5px 0'><strong>$icono Cancha:</strong> {$reserva['nombre_cancha']}</p>
                    <p style='margin:5px 0'><strong>📅 Fecha:</strong> " . date('d/m/Y', strtotime($reserva['fecha'])) . "</p>
                    <p style='margin:5px 0'><strong>⏰ Hora:</strong> $hora_inicio - $hora_fin</p>
                    <p style='margin:5px 0'><strong>💰 Valor:</strong> $" . number_format($reserva['monto_total'], 0, ',', '.') . "</p>
                </div>
                <p style='margin-top: 20px; text-align: center;'>
                    <a href='https://canchasport.com' 
                       style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        Ir a mi dashboard
                    </a>
                </p>
                <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
                <p style='text-align: center; font-size: 0.9rem; color: #888;'>
                    ¿Necesitas ayuda? Responde a este correo o contáctanos en soporte@canchasport.com
                </p>
            </div>
         ");
    
    $enviado = $mail->send();
    
    // Marcar como enviado en BD (opcional)
    if ($enviado) {
        $pdo->prepare("UPDATE reservas SET updated_at = NOW() WHERE id_reserva = ?")->execute([$id_reserva]);
    }
    
    echo json_encode(['success' => $enviado, 'message' => $enviado ? 'Correo enviado' : 'Falló el envío']);
    
} catch (Exception $e) {
    error_log("[Confirmación Reserva] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>