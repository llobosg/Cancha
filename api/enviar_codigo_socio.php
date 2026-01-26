<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $club_slug = $_POST['club_slug'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $alias = trim($_POST['alias'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $genero = $_POST['genero'] ?? '';
    $rol = $_POST['rol'] ?? 'Jugador';

    if (!$club_slug || !$nombre || !$alias || !$email || !$genero) {
        throw new Exception('Todos los campos son obligatorios');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo inválido');
    }

    // Obtener id_club desde slug (aquí usamos hash simple; en producción, mejor guardar slug en DB)
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

    // Verificar si ya está inscrito
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? AND id_club = ?");
    $stmt->execute([$email, $id_club]);
    if ($stmt->fetch()) throw new Exception('Ya estás inscrito en este club');

    $codigo = rand(1000, 9999);

    // Guardar socio temporal - En api/enviar_codigo_socio.php
    $fecha_nac = $_POST['fecha_nac'] ?? null;
    $celular = trim($_POST['celular'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $id_puesto = $_POST['id_puesto'] ?? null;
    $habilidad = $_POST['habilidad'] ?? null;

    // Guardar socio temporal
    $stmt = $pdo->prepare("
        INSERT INTO socios (
            id_club, nombre, alias, fecha_nac, celular, email, genero, 
            rol, direccion, foto_url, id_puesto, habilidad,
            verification_code, email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 0)
    ");
    $stmt->execute([
        $id_club, $nombre, $alias, $fecha_nac, $celular, $email, $genero,
        $rol, $direccion, $id_puesto, $habilidad, $codigo
    ]);

    $id_socio = $pdo->lastInsertId();

    // Enviar correo
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($email, $nombre);
    $mail->setSubject('🔐 Código de inscripción - Cancha');

    // Obtener nombre del club para personalizar el correo
    $stmt = $pdo->prepare("SELECT nombre FROM clubs WHERE id_club = ?");
    $stmt->execute([$id_club]);
    $club_nombre = $stmt->fetchColumn() ?: 'tu club';

    $mail->setHtmlBody("
        <h2>¡Bienvenido a Cancha!</h2>
        <p>Tu código de inscripción para entrar a <strong>{$club_nombre}</strong> es:</p>
        <h1 style='color:#009966;'>$codigo</h1>
        <p>Ingresa este código para confirmar tu inscripción.</p>
        <p>La validez del código es medio tiempo sin alargue</p>
    ");

    if (!$mail->send()) {
        $pdo->prepare("DELETE FROM socios WHERE id_socio = ?")->execute([$id_socio]);
        throw new Exception('Error al enviar el correo');
    }

    echo json_encode(['success' => true, 'id_socio' => $id_socio]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}