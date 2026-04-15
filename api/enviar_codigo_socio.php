<?php
// api/enviar_codigo_socio.php
if (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/config.php';

try {
    // 1. Validar Método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {throw new Exception('Método no permitido');}

    // 2. Logs de Debug (Solo en servidor, no afecta output)
    error_log(" [DEBUG] Inicio proceso registro");

    // 3. Validar Campos Obligatorios
    $required = ['nombre', 'alias', 'email', 'genero', 'rol', 'password', 'password_confirm', 'deporte'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Falta campo obligatorio: $field");
        }
    }

    // 4. Recoger Datos
    $nombre = trim($_POST['nombre']);
    $alias = trim($_POST['alias']);
    $email = trim($_POST['email']);
    $genero = $_POST['genero'] ?? 'Masculino';
    $rol = $_POST['rol'] ?? 'Jugador';
    $deporte = $_POST['deporte'] ?? 'Pádel';
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $club_slug = $_POST['club_slug'] ?? '';
    
    // Datos con defaults
    $fecha_nac = $_POST['fecha_nac'] ?? '2000-01-01';
    $celular = trim($_POST['celular'] ?? '+56900000000');
    $direccion = trim($_POST['direccion'] ?? 'Pendiente');
    $pais = $_POST['pais'] ?? 'Chile';
    $region = $_POST['region'] ?? 'Metropolitana';
    $ciudad = $_POST['ciudad'] ?? 'Santiago';
    $comuna = $_POST['comuna'] ?? 'Ñuñoa';
    $habilidad = $_POST['habilidad'] ?? 'Intermedia';
    $id_puesto = !empty($_POST['id_puesto']) ? (int)$_POST['id_puesto'] : 1;

    // Validaciones básicas
    if ($password !== $password_confirm) throw new Exception('Contraseñas no coinciden');
    if (strlen($password) < 6) throw new Exception('Contraseña muy corta');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

    // Verificar email duplicado
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) throw new Exception('Email ya registrado');

    // 5. Preparar Hash y Código
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $modo_individual = empty($club_slug);
    $id_club = null;

     // Verificar contraseña y email
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