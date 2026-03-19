<?php
// ✅ Primera línea del archivo
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // ← Ocultar errores en pantalla
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';
session_start();

try {
    $code = $_POST['code'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$code || strlen($code) !== 8) {
        throw new Exception('Código de invitación no válido');
    }
    if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Nombre y email válidos son requeridos');
    }

    // Verificar que la invitación existe
    $stmt = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_torneo, pt.id_socio_1, pt.id_jugador_temp_1, t.nombre AS torneo_nombre
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
    ");
    $stmt->execute([$code]);
    $pareja = $stmt->fetch();
    if (!$pareja) {
        throw new Exception('Invitación no válida o ya usada');
    }

    // Crear o obtener jugador temporal
    $stmt_check = $pdo->prepare("SELECT id_jugador FROM jugadores_temporales WHERE email = ?");
    $stmt_check->execute([$email]);
    $temp = $stmt_check->fetch();

    if ($temp) {
        $id_temporal = $temp['id_jugador'];
    } else {
        $token = hash('sha256', $email . time() . random_bytes(16));
        $pdo->prepare("
            INSERT INTO jugadores_temporales (nombre, email, token_registro)
            VALUES (?, ?, ?)
        ")->execute([$nombre, $email, $token]);
        $id_temporal = $pdo->lastInsertId();
    }

    // Completar la pareja
    $pdo->prepare("
        UPDATE parejas_torneo
        SET id_jugador_temp_2 = ?, estado = 'completa'
        WHERE id_pareja = ?
    ")->execute([$id_temporal, $pareja['id_pareja']]);

    // === Datos del torneo ===
    $torneo_id = $pareja['id_torneo'];
    $torneo_nombre = $pareja['torneo_nombre'];

    // === Correo al primer jugador (pareja 1) ===
    $mailer = new BrevoMailer();
    $mailer->setTo($email_primer, $nombre_primer);
    $mailer->setSubject('🎉 ¡Tu pareja se ha unido!');
    $mailer->setHtmlBody("
        <h2>¡Hola {$nombre_primer}!</h2>
        <p>¡Tu pareja ya se ha inscrito al torneo <strong>{$torneo_nombre}</strong>!</p>
        <p>Ya están listos para jugar. 🎾</p>
    ");
    $mailer->send();

    // === Correo al invitado (pareja 2) ===
    // Obtener el slug del torneo (asumiendo que ya tienes $torneo_id)
    $stmt_slug = $pdo->prepare("SELECT slug FROM torneos WHERE id_torneo = ?");
    $stmt_slug->execute([$torneo_id]);
    $slug = $stmt_slug->fetchColumn();

    if ($slug) {
        $link_torneo = "https://canchasport.com/pages/torneo_jugador.php?slug=" . urlencode($slug);
    } else {
        // Fallback por si no hay slug
        $link_torneo = "https://canchasport.com/pages/registro_socio.php?modo=individual";
    }

    $mailer = new BrevoMailer();
    $mailer->setTo($email_invitado, $nombre_invitado);
    $mailer->setSubject('✅ ¡Inscripción confirmada!');
    $mailer->setHtmlBody("
        <h2>¡Hola {$nombre_invitado}!</h2>
        <p>Tu inscripción al torneo <strong>{$torneo_nombre}</strong> ha sido confirmada.</p>
        <p>📊 <a href='{$link_torneo}' style='color:#FFD700;'>Ver estadísticas y fixture</a></p>
        <p>¡Listos para jugar!</p>
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
            <p><strong>Pareja:</strong> {$nombre} ({$email})</p>
        ");
        $mailer->send();
    }

    // === Cerrar torneo si está lleno ===
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ? AND estado = 'completa'");
    $stmt_count->execute([$torneo_id]);
    $inscritos = (int)$stmt_count->fetchColumn();

    $stmt_torneo = $pdo->prepare("SELECT num_parejas_max FROM torneos WHERE id_torneo = ?");
    $stmt_torneo->execute([$torneo_id]);
    $cupo = (int)$stmt_torneo->fetchColumn();

    if ($inscritos >= $cupo) {
        $pdo->prepare("UPDATE torneos SET estado = 'cerrado' WHERE id_torneo = ?")->execute([$torneo_id]);

        // === Notificar al admin ===
        $stmt_admin = $pdo->prepare("
            SELECT ar.email, r.nombre AS recinto_nombre
            FROM admin_recintos ar
            JOIN recintos_deportivos r ON ar.id_recinto = r.id_recinto
            WHERE ar.id_recinto = (
                SELECT id_recinto FROM torneos WHERE id_torneo = ?
            )
        ");
        $stmt_admin->execute([$torneo_id]);
        $admin = $stmt_admin->fetch();

        if ($admin) {
            $mailer = new BrevoMailer();
            $mailer->setTo($admin['email'], 'Admin del recinto');
            $mailer->setSubject('✅ Torneo cerrado: cupo completo');
            $mailer->setHtmlBody("
                <h2>El torneo <strong>{$torneo_nombre}</strong> ha sido cerrado.</h2>
                <p>✅ Se alcanzó el límite de <strong>{$cupo} parejas</strong>.</p>
                <p>Ya puedes proceder a generar el fixture desde tu dashboard.</p>
            ");
            $mailer->send();
        }
    }

    echo json_encode(['success' => true, 'message' => '✅ ¡Pareja completada!']);

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