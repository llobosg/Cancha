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
    echo json_encode(['nombre' => '']);
    exit;
}

$stmt = $pdo->prepare("SELECT nombre FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
$stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
$torneo = $stmt->fetch();
echo json_encode(['nombre' => $torneo ? $torneo['nombre'] : '']);
?>