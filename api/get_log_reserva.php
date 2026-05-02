<?php
// api/get_log_reserva.php
// === LIMPIEZA DE OUTPUT PARA EVITAR JSON ROTO ===
if (ob_get_level() > 0) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Manejar errores de PHP para que no rompan el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);  // ❌ No mostrar errores en pantalla
ini_set('log_errors', 1);      // ✅ Loguear errores en archivo
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../includes/config.php';

try {
    // 1. Verificar autenticación
    if (!isset($_SESSION['id_recinto'])) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // 2. Obtener ID de reserva
    $id_reserva = (int)($_GET['id_reserva'] ?? 0);
    if (!$id_reserva) {
        echo json_encode(['success' => false, 'error' => 'ID de reserva requerido']);
        exit;
    }

    // 3. Verificar que la reserva pertenece al recinto del usuario
    $stmt_check = $pdo->prepare("
        SELECT r.id_reserva 
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt_check->execute([$id_reserva, $_SESSION['id_recinto']]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Reserva no encontrada']);
        exit;
    }

    // 4. Obtener logs de la reserva (con formato de fecha compatible)
    $stmt = $pdo->prepare("
        SELECT 
            id_log,
            usuario_nombre as usuario,
            accion,
            descripcion,
            monto_anterior,
            monto_nuevo,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
        FROM reservas_log 
        WHERE id_reserva = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id_reserva]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Respuesta JSON exitosa
    echo json_encode([
        'success' => true,
        'logs' => $logs ?: []
    ]);

} catch (Exception $e) {
    // Logging del error real
    error_log("❌ [get_log_reserva] Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    
    // Respuesta JSON de error (sin HTML)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>