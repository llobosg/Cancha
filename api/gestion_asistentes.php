<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ============================
        // [1] CREAR ASISTENTE
        // ============================
        case 'crear':

            $usuario = trim($_POST['usuario']);
            $email = trim($_POST['email']);
            $nombre = trim($_POST['nombre_completo']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Validar duplicado
            $stmt = $pdo->prepare("SELECT id_admin FROM admin_recintos WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) {
                throw new Exception("Usuario ya existe");
            }

            $stmt = $pdo->prepare("
                INSERT INTO admin_recintos 
                (id_recinto, usuario, contraseña, email, rol, nombre_completo)
                VALUES (?, ?, ?, ?, 'asistente', ?)
            ");

            $stmt->execute([
                $_SESSION['id_recinto'],
                $usuario,
                $password,
                $email,
                $nombre
            ]);

            echo json_encode(['success' => true]);
            break;


        // ============================
        // [2] ELIMINAR
        // ============================
        case 'eliminar':

            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("
                DELETE FROM admin_recintos 
                WHERE id_admin = ? 
                AND id_recinto = ?
                AND rol = 'asistente'
            ");

            $stmt->execute([$id, $_SESSION['id_recinto']]);

            echo json_encode(['success' => true]);
            break;


        // ============================
        // [3] EDITAR
        // ============================
        case 'editar':

            $id = (int)$_POST['id'];
            $email = trim($_POST['email']);
            $nombre = trim($_POST['nombre_completo']);

            $stmt = $pdo->prepare("
                UPDATE admin_recintos 
                SET email = ?, nombre_completo = ?
                WHERE id_admin = ? AND id_recinto = ?
            ");

            $stmt->execute([
                $email,
                $nombre,
                $id,
                $_SESSION['id_recinto']
            ]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Acción inválida");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}