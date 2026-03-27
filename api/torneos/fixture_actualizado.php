<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$id_torneo = (int)($_GET['id'] ?? 0);
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

// Obtener partidos del torneo
$stmt = $pdo->prepare("
    SELECT 
        p.id_partido,
        CONCAT(j1.nombre, ' & ', j2.nombre) as pareja1,
        CONCAT(j3.nombre, ' & ', j4.nombre) as pareja2,
        p.set1_p1,
        p.set1_p2
    FROM partidos p
    JOIN parejas pa1 ON p.id_pareja1 = pa1.id_pareja
    JOIN usuarios j1 ON pa1.id_jugador1 = j1.id_usuario
    JOIN usuarios j2 ON pa1.id_jugador2 = j2.id_usuario
    JOIN parejas pa2 ON p.id_pareja2 = pa2.id_pareja
    JOIN usuarios j3 ON pa2.id_jugador1 = j3.id_usuario
    JOIN usuarios j4 ON pa2.id_jugador2 = j4.id_usuario
    WHERE p.id_torneo = ?
    ORDER BY p.id_partido
");
$stmt->execute([$id_torneo]);
$partidos = $stmt->fetchAll();

echo json_encode($partidos);
?>