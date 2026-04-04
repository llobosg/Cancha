<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    // Sumar puntos individuales de todos los torneos
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio,
            s.alias AS nombre,
            COALESCE(SUM(rp.puntos_individual_1), 0) +
            COALESCE(SUM(rp.puntos_individual_2), 0) AS total_puntos
        FROM socios s
        LEFT JOIN ranking_padel rp ON s.id_socio = rp.id_socio_1 OR s.id_socio = rp.id_socio_2
        WHERE s.deporte = 'padel'
        GROUP BY s.id_socio, s.alias
        HAVING total_puntos > 0
        ORDER BY total_puntos DESC
    ");
    $stmt->execute();
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Asignar nivel según posición
    $niveles = ['Primera', 'Segunda', 'Tercera', 'Cuarta', 'Quinta', 'Sexta'];
    foreach ($jugadores as $i => &$j) {
        $nivel_idx = min($i, count($niveles) - 1);
        $j['nivel'] = $niveles[$nivel_idx];
    }

    echo json_encode($jugadores);

} catch (Exception $e) {
    error_log("Error ranking: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar el ranking']);
}
?>