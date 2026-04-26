<?php
// api/get_fixture_torneo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

try {
    // Obtenemos partidos con nombres completos de las parejas
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido, 
            p.fecha_hora_programada,
            p.estado,
            p.juegos_pareja_1,
            p.juegos_pareja_2,
            -- Nombre Pareja 1
            CONCAT(
                COALESCE(s1.alias, jt1.nombre, 'J1'), 
                ' & ', 
                COALESCE(s1b.alias, jt1b.nombre, 'J2')
            ) AS nombre_pareja_1,
            -- Nombre Pareja 2
            CONCAT(
                COALESCE(s2.alias, jt2.nombre, 'J3'), 
                ' & ', 
                COALESCE(s2b.alias, jt2b.nombre, 'J4')
            ) AS nombre_pareja_2
        FROM partidos_torneo p
        LEFT JOIN parejas_torneo pt1 ON p.id_pareja_1 = pt1.id_pareja
        LEFT JOIN socios s1 ON pt1.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt1.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s1b ON pt1.id_socio_2 = s1b.id_socio
        LEFT JOIN jugadores_temporales jt1b ON pt1.id_jugador_temp_2 = jt1b.id_jugador
        
        LEFT JOIN parejas_torneo pt2 ON p.id_pareja_2 = pt2.id_pareja
        LEFT JOIN socios s2 ON pt2.id_socio_1 = s2.id_socio
        LEFT JOIN jugadores_temporales jt2 ON pt2.id_jugador_temp_1 = jt2.id_jugador
        LEFT JOIN socios s2b ON pt2.id_socio_2 = s2b.id_socio
        LEFT JOIN jugadores_temporales jt2b ON pt2.id_jugador_temp_2 = jt2b.id_jugador
        
        WHERE p.id_torneo = ?
        ORDER BY p.fecha_hora_programada ASC, p.id_partido ASC
    ");
    $stmt->execute([$id_torneo]);
    $fixture = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($fixture);
    
} catch (Exception $e) {
    error_log("❌ Error en get_fixture_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar fixture']);
}
?>