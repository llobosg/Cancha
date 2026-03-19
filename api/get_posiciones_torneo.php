<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        pt.puntos_totales,
        COALESCE(s1.alias, jt1.nombre, '#1') AS nombre
    FROM parejas_torneo pt
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    WHERE pt.id_torneo = ?
    ORDER BY pt.puntos_totales DESC, pt.id_pareja ASC
");
$stmt->execute([$id_torneo]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>