<?php
// api/gestion_asistentes.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permisos.php';


// Seguridad: Solo Admins
if (!esAdmin() || !isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = $_SESSION['id_recinto'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'crear_asistente') {
        $nombre = trim($_POST['nombre_completo']);
        $usuario = trim($_POST['usuario']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['contraseña'];

        // Validaciones básicas
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres.');
        }

        // Verificar si el usuario ya existe en ESTE recinto
        $stmtCheck = $pdo->prepare("SELECT id_admin FROM admin_recintos WHERE usuario = ? AND id_recinto = ?");
        $stmtCheck->execute([$usuario, $id_recinto]);
        if ($stmtCheck->fetch()) {
            throw new Exception('El nombre de usuario ya está en uso en este recinto.');
        }

        // Hash contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar
        $stmt = $pdo->prepare("
            INSERT INTO admin_recintos (id_recinto, usuario, contraseña, email, nombre_completo, telefono, rol) 
            VALUES (?, ?, ?, ?, ?, ?, 'asistente')
        ");
        
        $stmt->execute([$id_recinto, $usuario, $passwordHash, $email, $nombre, $telefono]);

        echo json_encode(['success' => true, 'message' => 'Asistente creado correctamente']);

    } elseif ($action === 'eliminar_asistente') {
        $id_admin = (int)$_POST['id_admin'];

        // No permitir eliminarse a sí mismo (aunque sea admin, por seguridad)
        if ($id_admin == $_SESSION['id_admin']) {
            throw new Exception('No puedes darte de baja a ti mismo desde aquí.');
        }

        // Opcional: Verificar que pertenezca al recinto
        $stmtCheck = $pdo->prepare("SELECT id_recinto FROM admin_recintos WHERE id_admin = ?");
        $stmtCheck->execute([$id_admin]);
        $row = $stmtCheck->fetch();
        
        if (!$row || $row['id_recinto'] != $id_recinto) {
            throw new Exception('Asistente no encontrado o no pertenece a este recinto.');
        }

        // Eliminar (Soft delete sería mejor cambiando estado, pero hacemos hard delete por simplicidad ahora)
        $stmtDel = $pdo->prepare("DELETE FROM admin_recintos WHERE id_admin = ? AND id_recinto = ?");
        $stmtDel->execute([$id_admin, $id_recinto]);

        echo json_encode(['success' => true, 'message' => 'Asistente eliminado correctamente']);

    } else {
        throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>