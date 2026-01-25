<?php
// api/enviar_codigo_club.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $nombre = trim($_POST['nombre'] ?? '');
    $deporte = $_POST['deporte'] ?? '';
    $ciudad = trim($_POST['ciudad'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email_responsable'] ?? '');

    // Validaciones
    if (!$nombre || !$deporte || !$ciudad || !$comuna || !$responsable || !$email) {
        throw new Exception('Todos los campos son obligatorios');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo electrónico inválido');
    }

    // Verificar si ya existe un club con mismo nombre + comuna + responsable
    $stmt = $pdo->prepare("
        SELECT id_club FROM clubs 
        WHERE nombre = ? AND comuna = ? AND responsable = ?
    ");
    $stmt->execute([$nombre, $comuna, $responsable]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un club con estos datos');
    }

    // Verificar si el responsable ya tiene 2 clubes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clubs WHERE email_responsable = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() >= 2) {
        throw new Exception('El responsable ya administra 2 clubes');
    }

    // Generar código de 4 dígitos
    $codigo = rand(1000, 9999);

    // Guardar temporalmente en sesión o base de datos
    // Aquí usamos base de datos para persistencia
    $stmt = $pdo->prepare("
        INSERT INTO clubs (
            nombre, pais, ciudad, comuna, responsable, telefono, email_responsable,
            deporte, verification_code, email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $nombre, $pais, $ciudad, $comuna, $responsable, $telefono, $email, $deporte, $codigo
    ]);

    $id_club = $pdo->lastInsertId();

    // === Enviar correo con Brevo ===
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($email, $responsable);
    $mail->setSubject('🔐 Código de verificación - Cancha');
    $mail->setHtmlBody("
        <h2>¡Bienvenido a Cancha!</h2>
        <p>Tu código de verificación es:</p>
        <h1 style='color:#009966;'>$codigo</h1>
        <p>Ingresa este código en los próximos 10 minutos para confirmar tu club.</p>
        <hr>
        <p><small>Este código expira en 10 minutos.</small></p>
    ");

    if (!$mail->send()) {
        // Si falla el correo, eliminamos el registro
        $pdo->prepare("DELETE FROM clubs WHERE id_club = ?")->execute([$id_club]);
        throw new Exception('Error al enviar el correo. Inténtalo nuevamente.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Código enviado a tu correo',
        'redirect' => '../pages/verificar_club.php?id=' . $id_club
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}