<?php
// api/get_log_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_reserva = $_GET['id_reserva'] ?? null;
if (!$id_reserva) {
    echo json_encode(['success' => false, 'message' => 'ID de reserva requerido']);
    exit;
}

try {
    // Verificar pertenencia al recinto
    $stmt = $pdo->prepare("
        SELECT 1 FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_reserva, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
        exit;
    }
    
    // Obtener logs ordenados por fecha (más reciente primero)
    // En api/get_log_reserva.php
    $stmt = $pdo->prepare("
        SELECT 
            id_log,
            usuario_nombre as usuario,
            accion,
            descripcion,
            monto_anterior,
            monto_nuevo,
            created_at
        FROM reservas_log 
        WHERE id_reserva = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id_reserva]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear para frontend
    $response = array_map(function($log) {
        return [
            'fecha' => $log['fecha_formateada'],
            'usuario' => htmlspecialchars($log['usuario_nombre']),
            'accion' => htmlspecialchars($log['accion']),
            'descripcion' => htmlspecialchars($log['descripcion'] ?? ''),
            'monto_anterior' => $log['monto_anterior'] ? number_format($log['monto_anterior'], 0, ',', '.') : null,
            'monto_nuevo' => $log['monto_nuevo'] ? number_format($log['monto_nuevo'], 0, ',', '.') : null
        ];
    }, $logs);
    
    echo json_encode(['success' => true, 'logs' => $response]);
    
} catch (Exception $e) {
    error_log("[GET_LOG] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>