<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Acceso denegado');
    }

    $id_recinto = $_POST['id_recinto'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if ($id_recinto != $_SESSION['id_recinto']) {
        throw new Exception('ID de recinto no coincide');
    }

    if (empty($nombre) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Nombre y correo válidos son obligatorios');
    }

    $pdo->prepare("
        UPDATE admin_recintos 
        SET nombre = ?, email = ?, telefono = ?, direccion = ?
        WHERE id_recinto = ?
    ")->execute([$nombre, $email, $telefono, $direccion, $id_recinto]);

    echo json_encode(['success' => true, 'message' => '✅ Perfil actualizado correctamente']);

} catch (Exception $e) {
    http_response_code(400);
    error_log("Error en guardar_perfil_admin_recinto.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>