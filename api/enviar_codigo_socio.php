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
    $required = ['club_slug', 'nombre', 'alias', 'email', 'genero', 'rol'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es obligatorio");
        }
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

    // Generar código
    $codigo = rand(1000, 9999);

    // Insertar socio
    $stmt = $pdo->prepare("
        INSERT INTO socios (
            id_club, nombre, alias, fecha_nac, celular, email, genero, 
            rol, direccion, foto_url, id_puesto, habilidad,
            verification_code, email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $id_club, $nombre, $alias, $fecha_nac, $celular, $email, $genero,
        $rol, $direccion, $foto_url, $id_puesto, $habilidad, $codigo
    ]);

    $id_socio = $pdo->lastInsertId();

    // Enviar correo
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($email, $nombre);
    $mail->setSubject('🔐 Código de inscripción - Cancha');

    // Obtener nombre del club
    $stmt = $pdo->prepare("SELECT nombre FROM clubs WHERE id_club = ?");
    $stmt->execute([$id_club]);
    $club_nombre = $stmt->fetchColumn() ?: 'tu club';

    $mail->setHtmlBody("
        <h2>¡Bienvenido a Cancha!</h2>
        <p>Tu código de inscripción para entrar a <strong>{$club_nombre}</strong> es:</p>
        <h1 style='color:#009966;'>$codigo</h1>
        <p>Ingresa este código para confirmar tu inscripción.</p>
        <p>El código tiene validez de medio tiempo sin alargue</p>
    ");

    if (!$mail->send()) {
        // No eliminamos el socio si falla el correo (puede reintentar)
        error_log("Correo fallido para socio $id_socio");
    }

    echo json_encode(['success' => true, 'id_socio' => $id_socio]);

} catch (Exception $e) {
    error_log("Registro socio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}