<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_partido = $_GET['id_partido'] ?? null;
if (!$id_partido) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT juegos_pareja_1, juegos_pareja_2 
    FROM partidos_torneo 
    WHERE id_partido = ?
");
$stmt->execute([$id_partido]);
echo json_encode($stmt->fetch() ?: ['juegos_pareja_1' => 0, 'juegos_pareja_2' => 0]);
?>