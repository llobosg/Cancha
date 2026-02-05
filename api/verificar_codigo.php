<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    $codigo = $input['codigo'] ?? '';
    $nueva_contraseña = $input['nueva_contraseña'] ?? '';
    
    if (empty($correo) || empty($codigo) || empty($nueva_contraseña)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido');
    }
    
    if (strlen($nueva_contraseña) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }
    
    // Verificar código válido
    $stmt = $pdo->prepare("
        SELECT r.id_ceo, r.usado, r.expires_at, c.correo
        FROM ceo_recuperacion r
        JOIN ceocancha c ON r.id_ceo = c.id_ceo
        WHERE c.correo = ? AND r.codigo = ? AND r.usado = 0
    ");
    $stmt->execute([$correo, $codigo]);
    $recuperacion = $stmt->fetch();
    
    if (!$recuperacion) {
        throw new Exception('Código inválido o ya utilizado');
    }
    
    // Verificar si ha expirado
    if (strtotime($recuperacion['expires_at']) < time()) {
        throw new Exception('El código ha expirado');
    }
    
    // Actualizar contraseña
    $contraseña_hash = password_hash($nueva_contraseña, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE ceocancha SET contraseña = ? WHERE id_ceo = ?");
    $stmt->execute([$contraseña_hash, $recuperacion['id_ceo']]);
    
    // Marcar código como usado
    $stmt = $pdo->prepare("UPDATE ceo_recuperacion SET usado = 1 WHERE id_ceo = ?");
    $stmt->execute([$recuperacion['id_ceo']]);
    
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>