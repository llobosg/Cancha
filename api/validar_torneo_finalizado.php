<?php
// api/validar_torneo_finalizado.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php'; // Esto inicia sesión

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar admin del recinto
if (!isset($_SESSION['id_recinto']) || !in_array($_SESSION['recinto_rol'] ?? '', ['admin', 'responsable'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode(['success' => false, 'message' => 'ID de torneo no especificado']);
    exit;
}

try {
    // Contar partidos sin resultado
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM partidos_torneo 
        WHERE id_torneo = ? AND estado != 'finalizado'
    ");
    $stmt->execute([$id_torneo]);
    $pendientes = $stmt->fetchColumn();

    if ($pendientes > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Hay {$pendientes} partidos sin resultado. No se puede finalizar el torneo."
        ]);
    } else {
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    error_log("Error validación torneo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
?>