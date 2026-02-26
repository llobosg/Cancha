<?php
// test/notificar_prueba.php
require_once __DIR__ . '/../includes/config.php';

// Forzar una cuota vencida
$stmt = $pdo->prepare("
    SELECT 
        c.*, 
        s.email, 
        s.nombre, 
        r.id_club,
        cl.nombre as club_nombre,
        cl.email_responsable
    FROM cuotas c
    JOIN socios s ON c.id_socio = s.id_socio
    JOIN reservas r ON c.id_evento = r.id_reserva
    JOIN clubs cl ON r.id_club = cl.id_club
    WHERE c.id_socio = 53 AND c.id_evento = 2
");
$stmt->execute();
$cuota = $stmt->fetch();

if (!$cuota) {
    die("Cuota no encontrada");
}

// Simular el envío
require_once __DIR__ . '/../includes/brevo_mailer.php';
$mail = new BrevoMailer();
$mail->setTo($cuota['email'], $cuota['nombre']);
$mail->setSubject('⚠️ Prueba: Cuota vencida en ' . $cuota['club_nombre']);
$mail->setHtmlBody("
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
        <div style='text-align: center; background: #E74C3C; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
            <h2>⚠️ Cuota Vencida (Prueba)</h2>
        </div>
        <p>¡Hola {$cuota['nombre']}!</p>
        <p>Esta es una prueba del sistema de notificación de cuotas vencidas.</p>
        <p><strong>Monto:</strong> $" . number_format($cuota['monto'], 0, ',', '.') . "<br>
        <strong>Vencimiento:</strong> " . date('d/m/Y', strtotime($cuota['fecha_vencimiento'])) . "</p>
        <p>✅ Si ves este correo, el sistema funciona correctamente.</p>
    </div>
");
if ($mail->send()) {
    echo "✅ Correo de prueba enviado a {$cuota['email']}";
} else {
    echo "❌ Error al enviar";
}
?>