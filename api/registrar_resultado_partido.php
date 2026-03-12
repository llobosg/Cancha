<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_partido = $data['id_partido'] ?? null;
    $resultado_1 = (int)($data['resultado_1'] ?? 0);
    $resultado_2 = (int)($data['resultado_2'] ?? 0);

    if (!$id_partido) throw new Exception('Partido no especificado');

    // Registrar resultado
    $pdo->prepare("
        UPDATE partidos_torneo 
        SET resultado_1 = ?, resultado_2 = ?, estado = 'jugado'
        WHERE id_partido = ?
    ")->execute([$resultado_1, $resultado_2, $id_partido]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>