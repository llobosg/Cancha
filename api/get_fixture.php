<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

// Validar que el usuario esté autenticado (socio o recinto)
if (!isset($_SESSION['id_socio']) && !isset($_SESSION['id_recinto'])) {
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
        p.id_partido,
        p.fecha_hora_programada,
        COALESCE(s1.alias, CONCAT(jt1.nombre, ' / ', jt1.apellido), '#Pareja 1') AS pareja1,
        COALESCE(s2.alias, CONCAT(jt2.nombre, ' / ', jt2.apellido), '#Pareja 2') AS pareja2
    FROM partidos_torneo p
    LEFT JOIN parejas_torneo pt1 ON p.id_pareja_1 = pt1.id_pareja
    LEFT JOIN socios s1 ON pt1.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt1.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN parejas_torneo pt2 ON p.id_pareja_2 = pt2.id_pareja
    LEFT JOIN socios s2 ON pt2.id_socio_1 = s2.id_socio
    LEFT JOIN jugadores_temporales jt2 ON pt2.id_jugador_temp_1 = jt2.id_jugador
    WHERE p.id_torneo = ?
    ORDER BY p.fecha_hora_programada ASC, p.id_partido ASC
");
$stmt->execute([$id_torneo]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>