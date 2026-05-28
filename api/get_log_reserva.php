<?php
// api/get_log_reserva.php
if (ob_get_level() > 0) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/config.php';

try {
    if (!isset($_SESSION['id_recinto'])) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $id_reserva = (int)($_GET['id_reserva'] ?? 0);
    if (!$id_reserva) {
        echo json_encode(['success' => false, 'error' => 'ID requerido']);
        exit;
    }

    // Verificar propiedad de la reserva
    $stmt_check = $pdo->prepare("
        SELECT r.id_reserva FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt_check->execute([$id_reserva, $_SESSION['id_recinto']]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Reserva no encontrada']);
        exit;
    }

    // Obtener logs
    $stmt = $pdo->prepare("
        SELECT 
            usuario_nombre as usuario,
            accion,
            descripcion,
            monto_anterior,
            monto_nuevo,
            metadata,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM reservas_log 
        WHERE id_reserva = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id_reserva]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs ?: []
    ]);

} catch (Exception $e) {
    error_log("[get_log_reserva] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>