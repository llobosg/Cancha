<?php
// api/registrar_log_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Validar datos mínimos
    if (empty($input['id_reserva']) || empty($input['accion'])) {
        throw new Exception('Datos incompletos para registrar log');
    }
    
    $id_reserva = (int)$input['id_reserva'];
    $accion = $input['accion'];  // ya validado por ENUM en BD
    $descripcion = $input['descripcion'] ?? '';
    $monto_anterior = $input['monto_anterior'] ?? null;
    $monto_nuevo = $input['monto_nuevo'] ?? null;
    $metadata = $input['metadata'] ?? null;
    
    // Datos del usuario que realiza la acción
    $id_admin = $_SESSION['id_admin'] ?? null;
    $usuario_nombre = $_SESSION['recinto_usuario'] ?? $_SESSION['recinto_rol'] ?? 'Sistema';
    
    // Verificar que la reserva pertenece al recinto del admin
    $stmt = $pdo->prepare("
        SELECT r.id_reserva FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_reserva, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada o no pertenece a este recinto');
    }
    
    // Insertar log
    $stmt = $pdo->prepare("
        INSERT INTO reservas_log 
        (id_reserva, id_admin, usuario_nombre, accion, descripcion, monto_anterior, monto_nuevo, metadata, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $id_reserva,
        $id_admin,
        $usuario_nombre,
        $accion,
        $descripcion,
        $monto_anterior,
        $monto_nuevo,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
    ]);
    
    echo json_encode(['success' => true, 'id_log' => $pdo->lastInsertId()]);
    
} catch (Exception $e) {
    error_log("[LOG_RESERVA] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>