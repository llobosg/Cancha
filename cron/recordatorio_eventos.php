<?php
require_once __DIR__ . '/../includes/config.php';

// Eventos que comienzan en las próximas 24 horas
$stmt = $pdo->prepare("
    SELECT r.id_reserva, r.id_club, r.fecha, r.hora_inicio, c.nombre as club_nombre, c.email_responsable
    FROM reservas r
    JOIN clubs c ON r.id_club = c.id_club
    WHERE r.fecha = CURDATE() + INTERVAL 1 DAY
      AND r.estado = 'confirmada'
");
$stmt->execute();
$eventos = $stmt->fetchAll();

foreach ($eventos as $evento) {
    // Obtener socios inscritos
    $stmt_socios = $pdo->prepare("
        SELECT s.email, s.nombre
        FROM inscritos i
        JOIN socios s ON i.id_socio = s.id_socio
        WHERE i.id_evento = ?
    ");
    $stmt_socios->execute([$evento['id_reserva']]);
    $socios = $stmt_socios->fetchAll();

    foreach ($socios as $socio) {
        $mail = new BrevoMailer();
        $mail->setTo($socio['email'], $socio['nombre']);
        $mail->setSubject('⚽ Recordatorio: Partido mañana');
        $mail->setHtmlBody("
            <p>¡Hola {$socio['nombre']}!</p>
            <p>Te recordamos que tienes un partido mañana:</p>
            <p><strong>Club:</strong> {$evento['club_nombre']}<br>
            <strong>Fecha:</strong> " . date('d/m', strtotime($evento['fecha'])) . "<br>
            <strong>Hora:</strong> {$evento['hora_inicio']}</p>
            <p><a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}'>Ver detalles</a></p>
        ");
        $mail->send();
    }
}
?>