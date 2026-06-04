<?php
// api/gestion_asistentes.php

// 1. Limpieza extrema para asegurar JSON puro
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/config.php';
session_start();

// 2. Validación de Sesión
if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$id_recinto = $_SESSION['id_recinto']; // CRÍTICO: Definir variable global del recinto

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'crear':
            $usuario = trim($_POST['usuario'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($usuario) || empty($email) || empty($nombre) || empty($password)) {
                throw new Exception("Todos los campos son obligatorios");
            }

            if (strlen($password) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }

            // Validar duplicados
            $stmt = $pdo->prepare("SELECT id_admin FROM admin_recintos WHERE usuario = ? OR email = ?");
            $stmt->execute([$usuario, $email]);
            if ($stmt->fetch()) {
                throw new Exception("El usuario o email ya existen");
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO admin_recintos 
                (id_recinto, usuario, contraseña, email, rol, nombre_completo)
                VALUES (?, ?, ?, ?, 'asistente', ?)
            ");

            $stmt->execute([
                $id_recinto, // Usar la variable de sesión
                $usuario,
                $hash,
                $email,
                $nombre
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'eliminar':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("ID inválido");

            $stmt = $pdo->prepare("
                DELETE FROM admin_recintos 
                WHERE id_admin = ? 
                AND id_recinto = ?
                AND rol = 'asistente'
            ");
            $stmt->execute([$id, $id_recinto]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo eliminar o no existe");
            }

            echo json_encode(['success' => true]);
            break;

        case 'editar':
            $id = (int)($_POST['id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $nombre = trim($_POST['nombre_completo'] ?? '');
            // Opcional: Permitir cambiar contraseña si se envía
            $password = $_POST['password'] ?? '';

            if (!$id || empty($email) || empty($nombre)) {
                throw new Exception("Datos incompletos");
            }

            if (!empty($password)) {
                if (strlen($password) < 6) throw new Exception("Contraseña muy corta");
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE admin_recintos 
                    SET email = ?, nombre_completo = ?, contraseña = ?
                    WHERE id_admin = ? AND id_recinto = ?
                ");
                $stmt->execute([$email, $nombre, $hash, $id, $id_recinto]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE admin_recintos 
                    SET email = ?, nombre_completo = ?
                    WHERE id_admin = ? AND id_recinto = ?
                ");
                $stmt->execute([$email, $nombre, $id, $id_recinto]);
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Acción inválida");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>