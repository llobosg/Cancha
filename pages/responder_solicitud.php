<?php
require_once __DIR__ . '/../includes/config.php';

$id_solicitud = $_GET['id'] ?? null;
$accion = $_GET['accion'] ?? null;

if (!$id_solicitud || !in_array($accion, ['aceptar', 'rechazar'])) {
    die('Solicitud inválida');
}

// Obtener datos de la solicitud
$stmt = $pdo->prepare("
    SELECT s.id_socio, s.id_club, soc.nombre AS nombre_socio, soc.email AS email_socio,
           c.nombre AS nombre_club, c.email_responsable
    FROM solicitudes_club s
    JOIN socios soc ON s.id_socio = soc.id_socio
    JOIN clubs c ON s.id_club = c.id_club
    WHERE s.id_solicitud = ? AND s.estado = 'pendiente'
");
$stmt->execute([$id_solicitud]);
$solicitud = $stmt->fetch();

if (!$solicitud) {
    die('Solicitud no encontrada o ya procesada.');
}

if ($accion === 'aceptar') {
    // Copiar perfil del socio al club
    $stmt = $pdo->prepare("
        INSERT INTO socios (
            id_club, nombre, alias, fecha_nac, celular, email, direccion,
            pais, region, ciudad, comuna, rol, foto_url, genero, deporte,
            id_puesto, habilidad, activo, email_verified, verification_code,
            es_responsable, datos_completos, password_hash
        )
        SELECT 
            ?, nombre, alias, fecha_nac, celular, email, direccion,
            pais, region, ciudad, comuna, rol, foto_url, genero, deporte,
            id_puesto, habilidad, activo, 1, NULL,
            0, datos_completos, password_hash
        FROM socios
        WHERE id_socio = ?
    ");
    $stmt->execute([$solicitud['id_club'], $solicitud['id_socio']]);

    // Marcar solicitud como aceptada
    $pdo->prepare("UPDATE solicitudes_club SET estado = 'aceptada', fecha_respuesta = NOW() WHERE id_solicitud = ?")
         ->execute([$id_solicitud]);

    // Correo al socio
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($solicitud['email_socio'], $solicitud['nombre_socio']);
    $mail->setSubject('🎉 ¡Bienvenido a ' . $solicitud['nombre_club'] . '!');
    $mail->setHtmlBody("
        <h2>¡Felicitaciones!</h2>
        <p>Has sido aceptado en <strong>{$solicitud['nombre_club']}</strong>.</p>
        <p>Ya puedes acceder a tu nuevo club desde tu dashboard en CanchaSport.</p>
        <p>¡Disfruta del juego!</p>
    ");
    $mail->send();

    echo "<h2>✅ Solicitud aceptada</h2><p>El socio ha sido notificado.</p>";

} else {
    // Rechazar
    $pdo->prepare("UPDATE solicitudes_club SET estado = 'rechazada', fecha_respuesta = NOW() WHERE id_solicitud = ?")
         ->execute([$id_solicitud]);

    // Correo al socio
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($solicitud['email_socio'], $solicitud['nombre_socio']);
    $mail->setSubject('😔 Solicitud de unión a club');
    $mail->setHtmlBody("
        <h2>Hola {$solicitud['nombre_socio']}</h2>
        <p>Lamentamos informarte que tu solicitud para unirte a <strong>{$solicitud['nombre_club']}</strong> no fue aceptada en esta ocasión.</p>
        <p>No te desanimes: puedes solicitar unirte a otros clubes en CanchaSport.</p>
        <p>¡Gracias por ser parte de nuestra comunidad!</p>
    ");
    $mail->send();

    echo "<h2>❌ Solicitud rechazada</h2><p>El socio ha sido notificado cordialmente.</p>";
}
?>