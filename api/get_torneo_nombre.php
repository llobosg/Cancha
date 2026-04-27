<?php
// api/get_torneo_nombre.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['nombre' => '']);
    exit;
}

$stmt = $pdo->prepare("SELECT nombre FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
$stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
$torneo = $stmt->fetch();
echo json_encode(['nombre' => $torneo ? $torneo['nombre'] : '']);
?>