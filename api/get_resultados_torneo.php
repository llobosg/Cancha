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
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        pt.juegos_pareja_1 AS juegos1,
        pt.juegos_pareja_2 AS juegos2,
        COALESCE(s1.alias, jt1.nombre, '#1') AS pareja1,
        COALESCE(s2.alias, jt2.nombre, '#2') AS pareja2,
        pt.fecha_hora_programada
    FROM partidos_torneo pt
    LEFT JOIN parejas_torneo p1 ON pt.id_pareja_1 = p1.id_pareja
    LEFT JOIN socios s1 ON p1.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON p1.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN parejas_torneo p2 ON pt.id_pareja_2 = p2.id_pareja
    LEFT JOIN socios s2 ON p2.id_socio_1 = s2.id_socio
    LEFT JOIN jugadores_temporales jt2 ON p2.id_jugador_temp_1 = jt2.id_jugador
    WHERE pt.id_torneo = ? AND pt.estado = 'finalizado'
    ORDER BY pt.fecha_hora_programada ASC, pt.id_partido ASC
");
$stmt->execute([$id_torneo]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>