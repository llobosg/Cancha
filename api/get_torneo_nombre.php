<?php
// api/get_torneo_nombre.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['error' => 'ID requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.nombre, r.nombre as recinto_nombre
        FROM torneos t
        JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
        WHERE t.id_torneo = ?
    ");
    $stmt->execute([$id_torneo]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($data ?: ['error' => 'No encontrado']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>