<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $id_torneo = (int)($_GET['id_torneo'] ?? 0);
    if (!$id_torneo) throw new Exception('ID de torneo requerido');

    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido,
            p.fecha_hora_programada,
            c.nombre_cancha,
            s1.alias AS pareja1_j1,
            s2.alias AS pareja1_j2,
            s3.alias AS pareja2_j1,
            s4.alias AS pareja2_j2,
            p.resultado_1,
            p.resultado_2,
            p.estado
        FROM partidos_torneo p
        LEFT JOIN canchas c ON p.id_cancha = c.id_cancha
        LEFT JOIN parejas_torneo pt1 ON p.id_pareja_1 = pt1.id_pareja
        LEFT JOIN parejas_torneo pt2 ON p.id_pareja_2 = pt2.id_pareja
        LEFT JOIN socios s1 ON pt1.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt1.id_socio_2 = s2.id_socio
        LEFT JOIN socios s3 ON pt2.id_socio_1 = s3.id_socio
        LEFT JOIN socios s4 ON pt2.id_socio_2 = s4.id_socio
        WHERE p.id_torneo = ?
        ORDER BY p.fecha_hora_programada ASC
    ");
    $stmt->execute([$id_torneo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar fixture']);
}
?>