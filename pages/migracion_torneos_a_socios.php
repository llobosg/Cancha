<?php
require_once __DIR__ . '/../includes/config.php';

// Torneos a procesar
$torneos = [8, 9, 10, 11];

foreach ($torneos as $id_torneo) {
    // Obtener parejas ordenadas por sets ganados
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            pt.id_socio_1,
            pt.id_socio_2,
            pt.puntos_totales AS sets_ganados
        FROM parejas_torneo pt
        WHERE pt.id_torneo = ?
        ORDER BY pt.puntos_totales DESC, pt.id_pareja ASC
    ");
    $stmt->execute([$id_torneo]);
    $parejas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($parejas);
    foreach ($parejas as $i => $p) {
        $pos = $i + 1;

        // Sistema de puntos ITF
        $puntos_pareja = match(true) {
            $pos == 1 => 100,
            $pos == 2 => 70,
            $pos == 3 => 50,
            $pos == 4 => 30,
            $pos == 5 => 20,
            default => 10
        };

        // Dividir puntos individual (60/40 si hay diferencia)
        if ($p['sets_ganados'] > 0) {
            $p1 = round($puntos_pareja * 0.6);
            $p2 = $puntos_pareja - $p1;
        } else {
            $p1 = $p2 = intval($puntos_pareja / 2);
        }

        // Insertar en ranking
        $pdo->prepare("
            INSERT INTO ranking_padel (
                id_torneo, id_socio_1, id_socio_2, sets_ganados,
                puntos_pareja, puntos_individual_1, puntos_individual_2
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                sets_ganados = VALUES(sets_ganados),
                puntos_pareja = VALUES(puntos_pareja),
                puntos_individual_1 = VALUES(puntos_individual_1),
                puntos_individual_2 = VALUES(puntos_individual_2)
        ")->execute([
            $id_torneo,
            $p['id_socio_1'] ?: 0,
            $p['id_socio_2'] ?: 0,
            $p['sets_ganados'],
            $puntos_pareja,
            $p1,
            $p2
        ]);
    }
}

echo "✅ Ranking inicial calculado para torneos: " . implode(', ', $torneos) . "\n";
?>