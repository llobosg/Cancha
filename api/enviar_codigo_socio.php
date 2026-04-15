<?php
// api/enviar_codigo_socio.php
if (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

    // Validar campos básicos
    $required = ['nombre', 'alias', 'email', 'genero', 'rol', 'password', 'password_confirm', 'deporte'];
    foreach ($required as $f) {
        if (empty($_POST[$f])) throw new Exception("Falta campo: $f");
    }

    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $passConf = $_POST['password_confirm'];

    if ($pass !== $passConf) throw new Exception('Contraseñas no coinciden');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

    // Verificar duplicado
    $chk = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
    $chk->execute([$email]);
    if ($chk->fetch()) throw new Exception('Email ya registrado');

    // Insertar
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("INSERT INTO socios (nombre, alias, email, genero, rol, deporte, password_hash, verification_code, activo, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Si', 0, NOW())");
    $stmt->execute([
        $_POST['nombre'], $_POST['alias'], $email, $_POST['genero'], $_POST['rol'], $_POST['deporte'], $hash, $code
    ]);
    
    $id = $pdo->lastInsertId();

    // Enviar Mail (Simulado o real, sin romper el flujo si falla)
    try {
        require_once __DIR__ . '/../includes/brevo_mailer.php';
        $mail = new BrevoMailer();
        $mail->setTo($email, $_POST['nombre']);
        $mail->setSubject('Tu código: ' . $code);
        $mail->setHtmlBody("<h1>Código: $code</h1>");
        $mail->send();
    } catch (Exception $eMail) {
        // Ignorar error de mail para no romper el registro
    }

    echo json_encode(['success' => true, 'id_socio' => $id, 'message' => 'Registro exitoso']);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>