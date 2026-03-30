<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['id_socio'])) {
        throw new Exception('No autenticado');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $club_slug = $input['club_slug'] ?? '';

    if (strlen($club_slug) !== 8) throw new Exception('Club inválido');

    // Buscar club
    $stmt = $pdo->prepare("SELECT id_club, email_responsable, nombre FROM clubs WHERE email_verified = 1");
    $stmt->execute();
    $clubs = $stmt->fetchAll();

    $id_club = null;
    $email_responsable = null;
    $nombre_club = null;
    foreach ($clubs as $c) {
        if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
            $id_club = $c['id_club'];
            $email_responsable = $c['email_responsable'];
            $nombre_club = $c['nombre'];
            break;
        }
    }

    if (!$id_club) throw new Exception('Club no encontrado');

    // Verificar si ya pertenece
    $stmt = $pdo->prepare("SELECT 1 FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt->execute([$_SESSION['id_socio'], $id_club]);
    if ($stmt->fetch()) {
        throw new Exception('Ya perteneces a este club');
    }

    // Verificar si ya hay una solicitud pendiente
    $stmt = $pdo->prepare("
        SELECT 1 FROM solicitudes_club 
        WHERE id_socio = ? AND id_club = ? AND estado = 'pendiente'
    ");
    $stmt->execute([$_SESSION['id_socio'], $id_club]);
    if ($stmt->fetch()) {
        throw new Exception('Ya enviaste una solicitud a este club');
    }

    // Guardar solicitud
    $stmt = $pdo->prepare("
        INSERT INTO solicitudes_club (id_socio, id_club) 
        VALUES (?, ?)
    ");
    $stmt->execute([$_SESSION['id_socio'], $id_club]);
    $id_solicitud = $pdo->lastInsertId();

    // Enviar correo al responsable
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();

    // Datos del socio
    $stmt = $pdo->prepare("SELECT nombre, alias, email FROM socios WHERE id_socio = ?");
    $stmt->execute([$_SESSION['id_socio']]);
    $socio = $stmt->fetch();

    $url_aceptar = "https://canchasport.com/pages/responder_solicitud.php?id=$id_solicitud&accion=aceptar";
    $url_rechazar = "https://canchasport.com/pages/responder_solicitud.php?id=$id_solicitud&accion=rechazar";

    $mail->setTo($email_responsable, 'Responsable del Club');
    $mail->setSubject('🤝 Nueva solicitud para unirse a tu club');
    $mail->setHtmlBody("
        <h2>¡Nueva solicitud en CanchaSport!</h2>
        <p>El socio <strong>{$socio['nombre']} ({$socio['alias']})</strong> desea unirse a <strong>$nombre_club</strong>.</p>
        <p>Por favor, revisa y decide:</p>
        <p>
            <a href='$url_aceptar' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>✅ Aceptar</a>
            &nbsp;
            <a href='$url_rechazar' style='background:#F44336;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>❌ Rechazar</a>
        </p>
        <p>Gracias por gestionar tu club en CanchaSport.</p>
    ");
    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Solicitud enviada. El responsable decidirá pronto.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>