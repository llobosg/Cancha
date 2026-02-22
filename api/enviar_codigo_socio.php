<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Validar campos básicos
    $required = ['club_slug', 'nombre', 'alias', 'email', 'genero', 'rol', 'password', 'password_confirm'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es obligatorio");
        }
    }

    // ✅ DEFINIR VARIABLES ANTES DE USARLAS
    $club_slug = $_POST['club_slug'];
    $nombre = trim($_POST['nombre']);
    $alias = trim($_POST['alias']);
    $email = trim($_POST['email']);
    $genero = $_POST['genero'];
    $rol = $_POST['rol'];
    $fecha_nac = $_POST['fecha_nac'] ?? null;
    $celular = trim($_POST['celular'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $id_puesto = !empty($_POST['id_puesto']) ? (int)$_POST['id_puesto'] : null;
    $habilidad = $_POST['habilidad'] ?? null;
    
    // ✅ VALIDAR DEPORTE (aquí sí existe $_POST)
    $deporte = $_POST['deporte'] ?? '';
    if (empty($deporte)) {
        throw new Exception('El deporte es obligatorio');
    }

    // Debug temporal - ahora sí funciona
    error_log("DEPORTE RECIBIDO: '" . $deporte . "'");
    error_log("HEX RECIBIDO: " . bin2hex($deporte));

    // Verificar que el deporte sea válido según el modo
    $modo_individual = empty($club_slug); // ← ¡Asegúrate de tener esta línea!
    
    if ($modo_individual) {
        $stmt_check = $pdo->prepare("SELECT 1 FROM deportes WHERE deporte = ? AND tipo_deporte = '1'");
    } else {
        $stmt_check = $pdo->prepare("SELECT 1 FROM deportes WHERE deporte = ? AND tipo_deporte = '2'");
    }
    $stmt_check->execute([$deporte]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Deporte no válido para este tipo de registro');
    }

    $club_slug = $_POST['club_slug'];
    $nombre = trim($_POST['nombre']);
    $alias = trim($_POST['alias']);
    $email = trim($_POST['email']);
    $genero = $_POST['genero'];
    $rol = $_POST['rol'];
    $fecha_nac = $_POST['fecha_nac'] ?? null;
    $celular = trim($_POST['celular'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $id_puesto = !empty($_POST['id_puesto']) ? (int)$_POST['id_puesto'] : null;
    $habilidad = $_POST['habilidad'] ?? null;
    
    // Validar contraseña
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    if ($password !== $password_confirm) {
        throw new Exception('Las contraseñas no coinciden');
    }
    
    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo electrónico inválido');
    }

    // Obtener id_club desde slug
    $stmt = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE email_verified = 1");
    $stmt->execute();
    $clubs = $stmt->fetchAll();
    $id_club = null;
    foreach ($clubs as $c) {
        if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
            $id_club = $c['id_club'];
            break;
        }
    }
    if (!$id_club) throw new Exception('Club no encontrado');

    // Verificar duplicados
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? AND id_club = ?");
    $stmt->execute([$email, $id_club]);
    if ($stmt->fetch()) throw new Exception('Ya estás inscrito en este club');

    // Validar fecha de nacimiento y edad mínima
    if (!empty($fecha_nac)) {
        $fecha_nac_obj = DateTime::createFromFormat('Y-m-d', $fecha_nac);
        if (!$fecha_nac_obj || $fecha_nac_obj->format('Y-m-d') !== $fecha_nac) {
            throw new Exception('Formato de fecha de nacimiento inválido');
        }
        
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac_obj)->y;
        
        if ($edad < 6) {
            throw new Exception('La edad mínima para CanchaSport es de 6 años');
        }
    }

    // === Manejar subida de foto ===
    $foto_url = null;
    if (!empty($_FILES['foto']['name'])) {
        // Verificar errores de subida
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la foto. Intenta con una imagen más pequeña.');
        }

        $upload_dir = __DIR__ . '/../uploads/socios/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($ext), $allowed_ext)) {
            throw new Exception('Formato de imagen no permitido. Usa JPG, PNG o GIF.');
        }

        $filename = 'socio_' . time() . '.' . strtolower($ext);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $filepath)) {
            $foto_url = $filename;
        } else {
            throw new Exception('No se pudo guardar la imagen. Contacta al administrador.');
        }
    }

    // Validar campos de ubicación
    $pais = $_POST['pais'] ?? 'Chile';
    $region = $_POST['region'] ?? null;
    $ciudad = $_POST['ciudad'] ?? null;
    $comuna = $_POST['comuna'] ?? null;

    // Validar que si se selecciona región, también se seleccione ciudad y comuna
    if ($region && (!$ciudad || !$comuna)) {
        throw new Exception('Debes seleccionar región, ciudad y comuna completos');
    }

    // Hashear contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Generar código de verificación
    $verification_code = rand(1000, 9999);

    $stmt = $pdo->prepare("
    INSERT INTO socios (
        id_club, nombre, alias, fecha_nac, celular, email, direccion, 
        pais, region, ciudad, comuna,  -- ← NUEVOS CAMPOS
        rol, foto_url, genero, deporte, id_puesto, habilidad,
        activo, email_verified, verification_code, es_responsable, datos_completos, password_hash
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Si', 0, ?, 0, 1, ?)
    ");
    $stmt->execute([
        $id_club,
        $nombre,
        $alias,
        !empty($fecha_nac) ? $fecha_nac : null,
        !empty($celular) ? $celular : null,
        $email,
        !empty($direccion) ? $direccion : null,
        $pais,
        $region, 
        $ciudad,
        $comuna,
        $rol,
        $foto_url,
        $genero,
        $deporte,
        $id_puesto ?: null,
        $habilidad ?: 'Básica',
        $verification_code,
        $password_hash
    ]);

    $id_socio = $pdo->lastInsertId();

    // Enviar correo
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($email, $nombre);
    $mail->setSubject('🔐 Código de inscripción - CanchaSport');

    // Obtener nombre del club
    $stmt = $pdo->prepare("SELECT nombre FROM clubs WHERE id_club = ?");
    $stmt->execute([$id_club]);
    $club_nombre = $stmt->fetchColumn() ?: 'tu club';

    $mail->setHtmlBody("
        <h2>¡Bienvenido a CanchaSport!</h2>
        <p>Tu código de inscripción para entrar a <strong>{$club_nombre}</strong> es:</p>
        <h1 style='color:#009966;'>{$verification_code}</h1>
        <p>Ingresa este código para confirmar tu inscripción.</p>
        <p>El código tiene validez de medio tiempo sin alargue</p>
    ");

    if (!$mail->send()) {
        // No eliminamos el socio si falla el correo (puede reintentar)
        error_log("Correo fallido para socio $id_socio");
    }

    $response_data = [
        'success' => true,
        'id_socio' => $id_socio,
        'club_slug' => $club_slug
    ];
    echo json_encode($response_data);

} catch (Exception $e) {
    error_log("Registro socio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>