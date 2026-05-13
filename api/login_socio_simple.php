<?php
// api/login_socio_simple.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

// Obtener datos del cuerpo JSON
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

try {
    if (!$email || !$password) {
        throw new Exception('Datos incompletos');
    }

    // Buscar usuario
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Iniciar sesión solo si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['id_socio'] = $user['id_socio'];
        $_SESSION['nombre_socio'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'] ?? 'Jugador';
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>