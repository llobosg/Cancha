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

// Obtener todas las parejas del torneo
$stmt = $pdo->prepare("
    SELECT 
        pt.id_pareja,
        CONCAT(
            COALESCE(s1.alias, jt1.nombre, 'J1'),
            ' & ',
            COALESCE(s2.alias, jt2.nombre, 'J2')
        ) AS nombre_pareja
    FROM parejas_torneo pt
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
    LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
    WHERE pt.id_torneo = ?
");
$stmt->execute([$id_torneo]);
$parejas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id_pareja => nombre

// Inicializar puntos
$puntos = array_fill_keys(array_keys($parejas), 0);

// Contar victorias desde partidos
$stmt = $pdo->prepare("
    SELECT id_pareja_1, id_pareja_2, juegos_pareja_1, juegos_pareja_2
    FROM partidos_torneo
    WHERE id_torneo = ? AND estado = 'finalizado'
");
$stmt->execute([$id_torneo]);
$partidos = $stmt->fetchAll();

foreach ($partidos as $p) {
    if ($p['juegos_pareja_1'] > $p['juegos_pareja_2']) {
        $puntos[$p['id_pareja_1']] = ($puntos[$p['id_pareja_1']] ?? 0) + 1;
    } elseif ($p['juegos_pareja_2'] > $p['juegos_pareja_1']) {
        $puntos[$p['id_pareja_2']] = ($puntos[$p['id_pareja_2']] ?? 0) + 1;
    }
}

// Construir ranking
$ranking = [];
foreach ($parejas as $id => $nombre) {
    $ranking[] = [
        'alias' => $nombre,
        'puntos' => $puntos[$id] ?? 0
    ];
}

// Ordenar por puntos descendente
usort($ranking, function($a, $b) {
    return $b['puntos'] - $a['puntos'];
});

echo json_encode($ranking);
?>