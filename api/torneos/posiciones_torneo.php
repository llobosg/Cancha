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

// Ejemplo: ranking por puntos (ajusta según tu lógica)
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(u1.nombre, ' & ', u2.nombre) as nombre,
        COALESCE(pa.alias1, u1.nombre) as alias,
        SUM(CASE WHEN p.ganador = pa.id_pareja THEN 1 ELSE 0 END) as puntos
    FROM parejas pa
    JOIN usuarios u1 ON pa.id_jugador1 = u1.id_usuario
    JOIN usuarios u2 ON pa.id_jugador2 = u2.id_usuario
    LEFT JOIN partidos p ON (p.id_pareja1 = pa.id_pareja OR p.id_pareja2 = pa.id_pareja)
    WHERE pa.id_torneo = ?
    GROUP BY pa.id_pareja
    ORDER BY puntos DESC
");
$stmt->execute([$id_torneo]);
$ranking = $stmt->fetchAll();

echo json_encode($ranking);
?>