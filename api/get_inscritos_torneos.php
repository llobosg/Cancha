<?php
// api/get_inscritos_torneo.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            COALESCE(s1.alias, jt1.nombre, 'Jugador 1') AS jugador1,
            COALESCE(s2.alias, jt2.nombre, 'Jugador 2') AS jugador2,
            CONCAT(COALESCE(s1.alias, jt1.nombre), ' & ', COALESCE(s2.alias, jt2.nombre)) AS nombre_pareja_completo,
            s1.email as email1,
            s2.email as email2,
            jt1.email as email_temp1,
            jt2.email as email_temp2
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
        WHERE pt.id_torneo = ?
        ORDER BY pt.id_pareja ASC
    ");
    $stmt->execute([$id_torneo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log("Error get_inscritos: " . $e->getMessage());
    echo json_encode([]);
}
?>