<?php
// api/registro_rapido_invitado.php
header('Content-Type: application/json');
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
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) {
        // Si ya existe, intentamos loguearlo automáticamente
        session_start();
        $stmt_pass = $pdo->prepare("SELECT password_hash FROM socios WHERE email = ?");
        $stmt_pass->execute([$email]);
        $hash = $stmt_pass->fetchColumn();
        
        if (password_verify($password, $hash)) {
            $stmt_user = $pdo->prepare("SELECT * FROM socios WHERE email = ?");
            $stmt_user->execute([$email]);
            $user = $stmt_user->fetch();
            
            $_SESSION['id_socio'] = $user['id_socio'];
            $_SESSION['nombre_socio'] = $user['nombre'];
            echo json_encode(['success' => true, 'message' => 'Usuario existente logueado']);
            exit;
        } else {
            throw new Exception('El email ya está registrado. Intenta iniciar sesión.');
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
    error_log("Error registro rapido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>