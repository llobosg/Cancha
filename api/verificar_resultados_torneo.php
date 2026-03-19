<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode(['tiene_resultados' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 1 FROM partidos_torneo 
    WHERE id_torneo = ? AND estado = 'finalizado' 
    LIMIT 1
");
$stmt->execute([$id_torneo]);
$tiene = (bool)$stmt->fetch();

echo json_encode(['tiene_resultados' => $tiene]);
?>