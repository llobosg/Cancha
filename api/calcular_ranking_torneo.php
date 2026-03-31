<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['recinto_rol']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_torneo = $input['id_torneo'] ?? null;

if (!$id_torneo) {
    echo json_encode(['success' => false, 'message' => 'ID de torneo no especificado']);
    exit;
}

try {
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

    if (empty($parejas)) {
        throw new Exception('No hay parejas en el torneo');
    }

    // Solo procesar parejas con ambos socios válidos
    if (!$p['id_socio_1'] || !$p['id_socio_2']) {
        error_log("⚠️ Pareja {$p['id_pareja']} incompleta. Saltando.");
        continue;
    }

    $total = count($parejas);
    foreach ($parejas as $i => $p) {
        $pos = $i + 1;

        // Sistema de puntos ITF simplificado
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

        // Insertar en ranking_padel
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

    // Actualizar estado del torneo
    $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?")
         ->execute([$id_torneo]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error cálculo ranking: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>