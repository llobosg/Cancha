<?php
// api/registro_rapido_invitado.php
header('Content-Type: application/json');
// Suprimir errores visuales para devolver JSON limpio
error_reporting(0); 

require_once __DIR__ . '/../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$nombre = trim($data['nombre'] ?? '');
$email = trim($data['email'] ?? '');
$telefono = trim($data['telefono'] ?? '');
$password = $data['password'] ?? '';

try {
    if (!$nombre || !$email || !$password) {
        throw new Exception('Datos incompletos');
    }

    // Verificar si ya existe
    $stmt_check = $pdo->prepare("SELECT id_socio, password_hash FROM socios WHERE email = ?");
    $stmt_check->execute([$email]);
    $existing_user = $stmt_check->fetch();

    if ($existing_user) {
        // Si ya existe, verificar contraseña para loguearlo
        if (password_verify($password, $existing_user['password_hash'])) {
            session_start();
            $_SESSION['id_socio'] = $existing_user['id_socio'];
            // Obtener nombre
            $stmt_name = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
            $stmt_name->execute([$existing_user['id_socio']]);
            $_SESSION['nombre_socio'] = $stmt_name->fetchColumn();
            
            echo json_encode(['success' => true, 'message' => 'Usuario existente logueado']);
            exit;
        } else {
            throw new Exception('Contraseña incorrecta para este email registrado.');
        }
    }

    // Crear nuevo socio
    $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode(' ', $nombre)[0]));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt_insert = $pdo->prepare("
        INSERT INTO socios (nombre, alias, email, celular, rol, activo, email_verified, password_hash, created_at) 
        VALUES (?, ?, ?, ?, 'Jugador', 'Si', 0, ?, NOW())
    ");
    
    $stmt_insert->execute([
        $nombre, 
        $alias ?: substr($email, 0, 5), 
        $email, 
        $telefono, 
        $hash
    ]);
    
    $id_socio = $pdo->lastInsertId();

    // Iniciar sesión automáticamente
    session_start();
    $_SESSION['id_socio'] = $id_socio;
    $_SESSION['nombre_socio'] = $nombre;

    echo json_encode(['success' => true, 'message' => 'Registro exitoso']);

} catch (Exception $e) {
    // Loguear internamente pero devolver JSON limpio
    error_log("Error registro rapido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>