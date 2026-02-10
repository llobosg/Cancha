<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    
    if (empty($email)) {
        throw new Exception('Email es requerido');
    }
    
    // Verificar que el socio exista
    $stmt = $pdo->prepare("
        SELECT id_socio 
        FROM socios 
        WHERE email = ? AND password_hash IS NOT NULL
    ");
    $stmt->execute([$email]);
    $socio = $stmt->fetch();
    
    if (!$socio) {
        // No revelar si el email existe o no (seguridad)
        echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
        exit;
    }
    
    // Generar token de recuperación
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (id_socio, token, expires_at, used) 
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$socio['id_socio'], $token, $expires]);
    
    // URL de reseteo
    $reset_link = "https://cancha-web.up.railway.app/pages/reset_password.php?token=" . $token;
    
    // Aquí iría el envío real de correo
    error_log("ENVIAR CORREO A: $email con link: $reset_link");
    
    echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>