<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $id_cuota = (int)$_POST['id_cuota'];
    
    // Verificar que pertenece al club del responsable
    $stmt = $pdo->prepare("
        SELECT c.id_cuota 
        FROM cuotas c
        JOIN socios s ON c.id_socio = s.id_socio
        WHERE c.id_cuota = ? AND s.id_club = ? AND c.estado = 'en_revision'
    ");
    $stmt->execute([$id_cuota, $_SESSION['club_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Cuota no encontrada o no en estado de revisión');
    }
    
    $pdo->prepare("UPDATE cuotas SET estado = 'pagado' WHERE id_cuota = ?")
        ->execute([$id_cuota]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>