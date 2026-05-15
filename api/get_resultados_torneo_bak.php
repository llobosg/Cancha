<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

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

// ✅ CORRECCIÓN: 
// 1. Quitar filtro de estado para mostrar TODOS los partidos
// 2. Agregar set_num calculado en PHP (compatible MySQL 5.7+)
$stmt = $pdo->prepare("
    SELECT 
        pt.id_partido,
        pt.juegos_pareja_1 AS juegos1,
        pt.juegos_pareja_2 AS juegos2,
        COALESCE(s1.alias, jt1.nombre, 'Pareja 1') AS pareja1,
        COALESCE(s2.alias, jt2.nombre, 'Pareja 2') AS pareja2,
        pt.fecha_hora_programada,
        c.nombre_cancha
    FROM partidos_torneo pt
    LEFT JOIN parejas_torneo p1 ON pt.id_pareja_1 = p1.id_pareja
    LEFT JOIN socios s1 ON p1.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON p1.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN parejas_torneo p2 ON pt.id_pareja_2 = p2.id_pareja
    LEFT JOIN socios s2 ON p2.id_socio_1 = s2.id_socio
    LEFT JOIN jugadores_temporales jt2 ON p2.id_jugador_temp_1 = jt2.id_jugador
    LEFT JOIN canchas c ON pt.id_cancha = c.id_cancha
    WHERE pt.id_torneo = ?
    ORDER BY pt.id_partido ASC
");
$stmt->execute([$id_torneo]);
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Agregar set_num manualmente: cada 3 partidos = 1 set
foreach ($partidos as $index => &$p) {
    $p['set_num'] = (int)ceil(($index + 1) / 3);
}

echo json_encode($partidos);
?>