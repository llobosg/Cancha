<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';

session_start();

error_log("🔍 [INSCRIBIR_TORNEO] Inicio del script");
error_log("🔍 [INSCRIBIR_TORNEO] Sesión actual: " . print_r($_SESSION, true));
error_log("🔍 [INSCRIBIR_TORNEO] POST recibido: " . print_r($_POST, true));

try {
    $slug = $_POST['slug'] ?? '';
    if (!$slug || strlen($slug) !== 8) {
        throw new Exception('Torneo no válido');
    }

    // Buscar torneo
    $stmt_torneo = $pdo->prepare("
        SELECT id_torneo, nivel, num_parejas_max, nombre 
        FROM torneos 
        WHERE slug = ? AND estado = 'abierto'
    ");
    $stmt_torneo->execute([$slug]);
    $torneo = $stmt_torneo->fetch();
    if (!$torneo) {
        error_log("❌ [INSCRIBIR_TORNEO] Torneo no encontrado o no abierto: $slug");
        throw new Exception('Torneo no encontrado o cerrado');
    }
    error_log("✅ [INSCRIBIR_TORNEO] Torneo cargado: ID=" . $torneo['id_torneo']);

    // Verificar cupo
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ?");
    $stmt_count->execute([$torneo['id_torneo']]);
    $inscritos = (int)$stmt_count->fetchColumn();
    if ($inscritos >= $torneo['num_parejas_max']) {
        error_log("❌ [INSCRIBIR_TORNEO] Cupo lleno para torneo ID=" . $torneo['id_torneo']);
        throw new Exception('Cupo lleno');
    }

    // Determinar tipo de jugador
    $es_socio = isset($_SESSION['id_socio']);
    $id_socio = null;
    $id_temporal = null;

    if ($es_socio) {
        $id_socio = (int)$_SESSION['id_socio'];
        if ($id_socio <= 0) {
            error_log("❌ [INSCRIBIR_TORNEO] ID_SOCIO inválido: " . $id_socio);
            throw new Exception('Sesión de socio inválida');
        }
        error_log("✅ [INSCRIBIR_TORNEO] Jugador es socio: ID=$id_socio");
    } else {
        // Validar datos mínimos
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ [INSCRIBIR_TORNEO] Datos inválidos para temporal: nombre='$nombre', email='$email'");
            throw new Exception('Nombre y email válidos son requeridos');
        }

        // Verificar si ya existe como temporal
        $stmt_check = $pdo->prepare("SELECT id_jugador FROM jugadores_temporales WHERE email = ?");
        $stmt_check->execute([$email]);
        $temp = $stmt_check->fetch();

        if ($temp) {
            $id_temporal = $temp['id_jugador'];
            error_log("✅ [INSCRIBIR_TORNEO] Jugador temporal existente: ID=$id_temporal");
        } else {
            // Crear nuevo jugador temporal
            $token = hash('sha256', $email . time() . random_bytes(16));
            $pdo->prepare("
                INSERT INTO jugadores_temporales (nombre, email, token_registro)
                VALUES (?, ?, ?)
            ")->execute([$nombre, $email, $token]);
            $id_temporal = $pdo->lastInsertId();
            error_log("✅ [INSCRIBIR_TORNEO] Nuevo jugador temporal creado: ID=$id_temporal");
        }
    }

    // Verificar que no esté ya inscrito
    $stmt_check_inscrito = $pdo->prepare("
        SELECT 1 FROM parejas_torneo 
        WHERE id_torneo = ? 
          AND (
            (id_socio_1 = ? OR id_socio_2 = ?) 
            OR (id_jugador_temp_1 = ? OR id_jugador_temp_2 = ?)
          )
    ");
    $stmt_check_inscrito->execute([
        $torneo['id_torneo'],
        $id_socio, $id_socio,
        $id_temporal, $id_temporal
    ]);
    if ($stmt_check_inscrito->fetch()) {
        error_log("❌ [INSCRIBIR_TORNEO] Jugador ya inscrito en torneo ID=" . $torneo['id_torneo']);
        throw new Exception('Ya estás inscrito en este torneo');
    }

    // Generar código de pareja
    $codigo_pareja = substr(md5(uniqid()), 0, 8);
    error_log("✅ [INSCRIBIR_TORNEO] Código de pareja generado: $codigo_pareja");

    // Insertar inscripción
    if ($es_socio) {
        $pdo->prepare("
            INSERT INTO parejas_torneo (
                id_torneo, id_socio_1, id_socio_2, id_jugador_temp_1, id_jugador_temp_2,
                codigo_pareja, estado, nombre_pareja
            ) VALUES (?, ?, NULL, NULL, NULL, ?, 'esperando_pareja', NULL)
        ")->execute([$torneo['id_torneo'], $id_socio, $codigo_pareja]);
        error_log("✅ [INSCRIBIR_TORNEO] Inscripción de socio completada: socio=$id_socio, torneo={$torneo['id_torneo']}");
    } else {
        $pdo->prepare("
            INSERT INTO parejas_torneo (
                id_torneo, id_socio_1, id_socio_2, id_jugador_temp_1, id_jugador_temp_2,
                codigo_pareja, estado, nombre_pareja
            ) VALUES (?, NULL, NULL, ?, NULL, ?, 'esperando_pareja', NULL)
        ")->execute([$torneo['id_torneo'], $id_temporal, $codigo_pareja]);
        error_log("✅ [INSCRIBIR_TORNEO] Inscripción de temporal completada: temporal=$id_temporal, torneo={$torneo['id_torneo']}");
    }

    // === ENVIAR CORREO CON BREVO ===
    if ($es_socio) {
        $stmt_email = $pdo->prepare("SELECT email, nombre FROM socios WHERE id_socio = ?");
        $stmt_email->execute([$id_socio]);
        $user = $stmt_email->fetch();
        $email_destino = $user['email'] ?? null;
        $nombre_destino = $user['nombre'] ?? 'Jugador';
    } else {
        $email_destino = $_POST['email'] ?? null;
        $nombre_destino = $_POST['nombre'] ?? 'Jugador';
    }

    if ($email_destino && $nombre_destino) {
        // Generar QR en base64
        $qrLink = "https://canchasport.com/pages/torneo_invite.php?code={$codigo_pareja}";
        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrLink);
        $qrImage = @file_get_contents($qrApiUrl);
        $qrData = $qrImage ? base64_encode($qrImage) : '';

        // Enviar correo
        try {
            $mailer = new BrevoMailer();
            $mailer->setTo($email_destino, $nombre_destino);
            $mailer->setSubject('⚽ ¡Tu link de invitación para el torneo!');
            $html = "
                <h2>¡Hola {$nombre_destino}!</h2>
                <p>Te has inscrito como primer jugador en el torneo <strong>{$torneo['nombre']}</strong>.</p>
                <p>Ahora solo falta invitar a tu pareja:</p>
                <p><strong>Link de invitación:</strong><br>
                <a href='{$qrLink}' style='color:#071289;word-break:break-all;'>{$qrLink}</a></p>
                " . ($qrData ? "<p>O escanea este QR:</p><img src='data:image/png;base64,{$qrData}' alt='QR' style='max-width:200px; border:1px solid #eee; border-radius:8px;'>" : "") . "
                <p>¡Mucha suerte en el torneo!</p>
                <hr style='margin:20px 0; border:0; border-top:1px solid #eee;'>
                <small>Este mensaje fue generado automáticamente por CanchaSport.</small>
            ";
            $mailer->setHtmlBody($html);
            if ($mailer->send()) {
                error_log("✅ [INSCRIBIR_TORNEO] Correo enviado a: $email_destino");
            } else {
                error_log("⚠️ [INSCRIBIR_TORNEO] Falló el envío del correo a: $email_destino");
            }
        } catch (Exception $e) {
            error_log("❌ [INSCRIBIR_TORNEO] Error al enviar correo: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'redirect' => "/pages/torneo_pair.php?slug={$slug}&code={$codigo_pareja}"
    ]);

} catch (Exception $e) {
    error_log("❌ [INSCRIBIR_TORNEO] Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>