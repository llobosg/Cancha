<?php
require_once __DIR__ . '/../includes/config.php';

// Cuotas vencidas hace más de 3 días
$stmt = $pdo->prepare("
    SELECT s.email, s.nombre, s.id_club, c.nombre as club_nombre, cu.monto, cu.fecha_vencimiento
    FROM cuotas cu
    JOIN socios s ON cu.id_socio = s.id_socio
    JOIN clubs c ON s.id_club = c.id_club
    WHERE cu.fecha_vencimiento < CURDATE() - INTERVAL 3 DAY
      AND cu.estado = 'pendiente'
");
$stmt->execute();
$cuotas = $stmt->fetchAll();

foreach ($cuotas as $cuota) {
    $mail = new BrevoMailer();
    $mail->setTo($cuota['email'], $cuota['nombre']);
    $mail->setSubject('⚠️ Cuota vencida en ' . $cuota['club_nombre']);
    $mail->setHtmlBody("
        <p>¡Hola {$cuota['nombre']}!</p>
        <p>Tienes una cuota pendiente en el club <strong>{$cuota['club_nombre']}</strong>:</p>
        <p><strong>Monto:</strong> $" . number_format($cuota['monto'], 0, ',', '.') . "<br>
        <strong>Vencimiento:</strong> " . date('d/m/Y', strtotime($cuota['fecha_vencimiento'])) . "</p>
        <p>Por favor regulariza tu situación para no perder acceso a los eventos.</p>
        <p><a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}'>Pagar ahora</a></p>
    ");
    $mail->send();
}
?>