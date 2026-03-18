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
        pt.id_pareja,
        COALESCE(s1.alias, jt1.nombre) AS nombre1,
        COALESCE(s2.alias, jt2.nombre) AS nombre2,
        t.valor,
        'pendiente' AS estado_valor
    FROM parejas_torneo pt
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
    WHERE pt.id_torneo = ? AND pt.estado = 'completa'
    ORDER BY pt.id_pareja
");
$stmt->execute([$id_torneo]);
$parejas = [];
while ($row = $stmt->fetch()) {
    $parejas[] = [
        'id_pareja' => $row['id_pareja'],
        'nombre' => $row['nombre1'] . ' + ' . $row['nombre2'],
        'estado_valor' => $row['estado_valor']
    ];
}
echo json_encode($parejas);
?>