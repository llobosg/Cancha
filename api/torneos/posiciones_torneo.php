<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
session_start();

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

// Suponiendo que calculas puntos por victorias en partidos_torneo
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(s1.alias, jt1.nombre, 'Pareja') as alias,
        COUNT(CASE WHEN p.ganador = p.id_pareja_1 THEN 1 END) +
        COUNT(CASE WHEN p.ganador = p.id_pareja_2 THEN 1 END) as puntos
    FROM parejas_torneo pt
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN partidos_torneo p ON (p.id_pareja_1 = pt.id_pareja OR p.id_pareja_2 = pt.id_pareja)
    WHERE pt.id_torneo = ?
    GROUP BY pt.id_pareja
    ORDER BY puntos DESC
");
$stmt->execute([$id_torneo]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>