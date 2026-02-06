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
        SELECT r.id_admin, r.usado, r.expires_at, a.email
        FROM recuperacion_recintos r
        JOIN admin_recintos a ON r.id_admin = a.id_admin
        WHERE a.email = ? AND r.codigo = ? AND r.usado = 0
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
    $stmt = $pdo->prepare("UPDATE admin_recintos SET contraseña = ? WHERE id_admin = ?");
    $stmt->execute([$contraseña_hash, $recuperacion['id_admin']]);
    
    // Marcar código como usado
    $stmt = $pdo->prepare("UPDATE recuperacion_recintos SET usado = 1 WHERE id_admin = ?");
    $stmt->execute([$recuperacion['id_admin']]);
    
    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>