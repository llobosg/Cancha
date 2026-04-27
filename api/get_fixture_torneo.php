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
    // Consulta HÍBRIDA: Une Socios Y Jugadores Temporales
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido, 
            p.fecha_hora_programada,
            p.estado,
            p.juegos_pareja_1,
            p.juegos_pareja_2,
            
            -- PAREJA 1: Prioriza Socio, sino Temporal
            CONCAT(
                COALESCE(NULLIF(s1.alias, ''), SUBSTRING_INDEX(s1.nombre, ' ', 1), jt1.nombre, 'J1'), 
                ' & ', 
                COALESCE(NULLIF(s1b.alias, ''), SUBSTRING_INDEX(s1b.nombre, ' ', 1), jt1b.nombre, 'J2')
            ) AS nombre_pareja_1,
            
            -- PAREJA 2: Prioriza Socio, sino Temporal
            CONCAT(
                COALESCE(NULLIF(s2.alias, ''), SUBSTRING_INDEX(s2.nombre, ' ', 1), jt2.nombre, 'J3'), 
                ' & ', 
                COALESCE(NULLIF(s2b.alias, ''), SUBSTRING_INDEX(s2b.nombre, ' ', 1), jt2b.nombre, 'J4')
            ) AS nombre_pareja_2
            
        FROM partidos_torneo p
        
        -- Joins Pareja 1
        LEFT JOIN parejas_torneo pt1 ON p.id_pareja_1 = pt1.id_pareja
        LEFT JOIN socios s1 ON pt1.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt1.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s1b ON pt1.id_socio_2 = s1b.id_socio
        LEFT JOIN jugadores_temporales jt1b ON pt1.id_jugador_temp_2 = jt1b.id_jugador
        
        -- Joins Pareja 2
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