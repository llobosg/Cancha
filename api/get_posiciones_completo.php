<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio'])) {
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
        pt.id_pareja,
        COALESCE(
            CONCAT(s1.alias, ' / ', s2.alias),
            CONCAT(jt1.nombre, ' / ', jt2.nombre),
            'Pareja ' || pt.id_pareja
        ) AS nombre_pareja,
        pt.puntos_totales AS sets_ganados
    FROM parejas_torneo pt
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
    WHERE pt.id_torneo = ?
    ORDER BY pt.puntos_totales DESC, pt.id_pareja ASC
");
$stmt->execute([$id_torneo]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>