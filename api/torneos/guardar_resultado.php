<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_partido = (int)($data['id_partido'] ?? 0);
$campo = $data['campo'] ?? ''; // 'set1_p1' o 'set1_p2'
$valor = (int)($data['valor'] ?? 0);

if (!$id_partido || !in_array($campo, ['set1_p1', 'set1_p2']) || $valor < 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Mapear campo a columna real
$columna = ($campo === 'set1_p1') ? 'juegos_pareja_1' : 'juegos_pareja_2';

$stmt = $pdo->prepare("UPDATE partidos_torneo SET $columna = ? WHERE id_partido = ?");
$stmt->execute([$valor, $id_partido]);

echo json_encode(['success' => true]);
?>