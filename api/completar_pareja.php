<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';
session_start();

try {
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Acceso no autorizado');
    }

    $code = $_POST['code'] ?? '';
    if (!$code) throw new Exception('Código no válido');

    $id_socio_2 = $_SESSION['id_socio'];

    // Verificar que la invitación existe y está disponible
    $stmt = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_socio_1, pt.id_torneo, t.nombre AS torneo_nombre
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
    ");
    $stmt->execute([$code]);
    $pareja = $stmt->fetch();
    if (!$pareja) throw new Exception('Invitación no válida o ya usada');

    if ($pareja['id_socio_1'] == $id_socio_2) {
        throw new Exception('No puedes invitarte a ti mismo');
    }

    // Completar la pareja
    $pdo->prepare("
        UPDATE parejas_torneo
        SET id_socio_2 = ?, estado = 'completa'
        WHERE id_pareja = ?
    ")->execute([$id_socio_2, $pareja['id_pareja']]);

    // === Obtener datos del torneo ===
    $torneo_id = $pareja['id_torneo'];
    $torneo_nombre = $pareja['torneo_nombre'];

    // === Correo al invitado ===
    $stmt_inv = $pdo->prepare("SELECT email, nombre FROM socios WHERE id_socio = ?");
    $stmt_inv->execute([$id_socio_2]);
    $invitado = $stmt_inv->fetch();
    if ($invitado) {
        $link_dashboard = 'https://canchasport.com/pages/dashboard_socio.php';
        enviarCorreoConfirmacion($invitado['email'], $invitado['nombre'], $torneo_nombre, $link_dashboard);
    }

    // === Correo al primer jugador ===
    $stmt1 = $pdo->prepare("SELECT email, nombre FROM socios WHERE id_socio = ?");
    $stmt1->execute([$pareja['id_socio_1']]);
    $primer = $stmt1->fetch();
    if ($primer) {
        $mailer = new BrevoMailer();
        $mailer->setTo($primer['email'], $primer['nombre']);
        $mailer->setSubject('🎉 ¡Tu pareja se ha unido!');
        $mailer->setHtmlBody("
            <h2>¡Hola {$primer['nombre']}!</h2>
            <p>¡Tu pareja ya se ha inscrito al torneo <strong>{$torneo_nombre}</strong>!</p>
            <p>Ya están listos para jugar. 🎾</p>
        ");
        $mailer->send();
    }

    // === Correo al admin ===
    $stmt_admin = $pdo->prepare("
        SELECT ar.email 
        FROM admin_recintos ar 
        JOIN torneos t ON ar.id_recinto = t.id_recinto 
        WHERE t.id_torneo = ?
    ");
    $stmt_admin->execute([$torneo_id]);
    $admin = $stmt_admin->fetch();
    if ($admin) {
        $mailer = new BrevoMailer();
        $mailer->setTo($admin['email'], 'Admin del recinto');
        $mailer->setSubject('🆕 Nueva pareja inscrita');
        $mailer->setHtmlBody("
            <h2>Nueva pareja en {$torneo_nombre}</h2>
            <p><strong>Primer jugador:</strong> {$primer['nombre']} ({$primer['email']})</p>
            <p><strong>Pareja:</strong> {$invitado['nombre']} ({$invitado['email']})</p>
        ");
        $mailer->send();
    }

    // === Verificar cierre de torneo ===
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ? AND estado = 'completa'");
    $stmt_count->execute([$torneo_id]);
    $inscritos = (int)$stmt_count->fetchColumn();

    $stmt_torneo = $pdo->prepare("SELECT num_parejas_max FROM torneos WHERE id_torneo = ?");
    $stmt_torneo->execute([$torneo_id]);
    $cupo = (int)$stmt_torneo->fetchColumn();

    if ($inscritos >= $cupo) {
        $pdo->prepare("UPDATE torneos SET estado = 'cerrado' WHERE id_torneo = ?")->execute([$torneo_id]);
        // Notificación opcional: se puede implementar después
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enviarCorreoConfirmacion($email, $nombre, $torneo, $link) {
    $mailer = new BrevoMailer();
    $mailer->setTo($email, $nombre);
    $mailer->setSubject('✅ ¡Inscripción confirmada!');
    $mailer->setHtmlBody("
        <h2>¡Hola {$nombre}!</h2>
        <p>Tu inscripción al torneo <strong>{$torneo}</strong> ha sido confirmada.</p>
        <p>📊 <a href='{$link}' style='color:#FFD700;'>Ver estadísticas y fixture</a></p>
        <p>¡Listos para jugar!</p>
    ");
    $mailer->send();
}
?>