<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $modo = $_POST['modo'] ?? 'normal';
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_puesto = $_POST['id_puesto'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$nombre || !$email || !$telefono || !$id_puesto || !$password) {
        throw new Exception('Todos los campos son obligatorios');
    }

    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    // Verificar email único
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) {
        throw new Exception('Este email ya está registrado');
    }

    // Insertar socio individual
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO socios (nombre, alias, email, telefono, id_puesto, password_hash, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $nombre,
        $nombre,
        $email,
        $telefono,
        $id_puesto,
        $password_hash
    ]);

    $id_socio = $pdo->lastInsertId();

    // Iniciar sesión
    $_SESSION['id_socio'] = $id_socio;
    $_SESSION['user_email'] = $email;

    // Redirigir según modo
    if ($modo === 'torneo' && !empty($_SESSION['post_registro_torneo'])) {
        $data = $_SESSION['post_registro_torneo'];
        unset($_SESSION['post_registro_torneo']);
        $redirect = "/torneo_pair.php?slug=" . urlencode($data['slug']) . "&code=" . urlencode($data['code']);
    } else {
        $redirect = null;
    }

    echo json_encode(['success' => true, 'redirect' => $redirect]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>