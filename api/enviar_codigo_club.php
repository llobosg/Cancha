<?php
// api/enviar_codigo_club.php
header('Content-Type: application/json; charset=utf-8');

// 1. Silenciar errores visibles (solo logs)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. Capturar cualquier error fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor. Revisa las variables de entorno.'
        ]);
    }
});

try {
    require_once __DIR__ . '/../includes/config.php';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    // Validar campos
    $required = ['nombre', 'deporte', 'pais', 'region', 'ciudad', 'comuna', 'responsable', 'telefono', 'email_responsable'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es obligatorio");
        }
    }

    $nombre = trim($_POST['nombre']);
    $deporte = $_POST['deporte'];
    $pais = trim($_POST['pais']);
    $ciudad = trim($_POST['ciudad']);
    $comuna = trim($_POST['comuna']);
    $responsable = trim($_POST['responsable']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email_responsable']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo electrónico inválido');
    }

    // Verificar duplicados
    $stmt = $pdo->prepare("
        SELECT id_club FROM clubs 
        WHERE nombre = ? AND comuna = ? AND responsable = ?
    ");
    $stmt->execute([$nombre, $comuna, $responsable]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un club con estos datos');
    }

    // Generar código
    $codigo = rand(1000, 9999);

    // Insertar
    $stmt = $pdo->prepare("
        INSERT INTO clubs (
            nombre, pais, ciudad, comuna, responsable, telefono, email_responsable,
            deporte, verification_code, email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$nombre, $pais, $ciudad, $comuna, $responsable, $telefono, $email, $deporte, $codigo]);

    $id_club = $pdo->lastInsertId();

    // Enviar correo
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($email, $responsable);
    $mail->setSubject('🔐 Código de verificación - Cancha');
    $mail->setHtmlBody("
        <h2>¡Bienvenido a Cancha!</h2>
        <p>Tu código de verificación es:</p>
        <h1 style='color:#009966;'>$codigo</h1>
        <p>Ingresa este código en los próximos 10 minutos para confirmar tu club.</p>
    ");

    if (!$mail->send()) {
        $pdo->prepare("DELETE FROM clubs WHERE id_club = ?")->execute([$id_club]);
        throw new Exception('Error al enviar el correo. Verifica tu conexión.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Código enviado a tu correo',
        'redirect' => '../pages/verificar_club.php?id=' . $id_club
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    http_response_code($code);
    error_log("Registro club error [$code]: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}