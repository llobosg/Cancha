<?php
// api/get_fixture.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar acceso básico
if (!isset($_SESSION['id_recinto']) && !isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

try {
    // Consulta optimizada para tu estructura de BD exacta
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido,
            p.fecha_hora_programada,
            p.juegos_pareja_1,
            p.juegos_pareja_2,
            p.estado,
            
            -- CONSTRUCCIÓN NOMBRE PAREJA 1
            CONCAT(
                COALESCE(NULLIF(s1.alias, ''), jt1.nombre, CONCAT('Jugador ', pt1.id_socio_1)), 
                ' & ', 
                COALESCE(NULLIF(s1b.alias, ''), jt1b.nombre, CONCAT('Jugador ', pt1.id_socio_2))
            ) AS pareja1,
            
            -- CONSTRUCCIÓN NOMBRE PAREJA 2
            CONCAT(
                COALESCE(NULLIF(s2.alias, ''), jt2.nombre, CONCAT('Jugador ', pt2.id_socio_1)), 
                ' & ', 
                COALESCE(NULLIF(s2b.alias, ''), jt2b.nombre, CONCAT('Jugador ', pt2.id_socio_2))
            ) AS pareja2
            
        FROM partidos_torneo p
        
        -- JOINS PAREJA 1
        LEFT JOIN parejas_torneo pt1 ON p.id_pareja_1 = pt1.id_pareja
        LEFT JOIN socios s1 ON pt1.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt1.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s1b ON pt1.id_socio_2 = s1b.id_socio
        LEFT JOIN jugadores_temporales jt1b ON pt1.id_jugador_temp_2 = jt1b.id_jugador
        
        -- JOINS PAREJA 2
        LEFT JOIN parejas_torneo pt2 ON p.id_pareja_2 = pt2.id_pareja
        LEFT JOIN socios s2 ON pt2.id_socio_1 = s2.id_socio
        LEFT JOIN jugadores_temporales jt2 ON pt2.id_jugador_temp_1 = jt2.id_jugador
        LEFT JOIN socios s2b ON pt2.id_socio_2 = s2b.id_socio
        LEFT JOIN jugadores_temporales jt2b ON pt2.id_jugador_temp_2 = jt2b.id_jugador
        
        WHERE p.id_torneo = ?
        ORDER BY p.fecha_hora_programada ASC, p.id_partido ASC
    ");
    
    $stmt->execute([$id_torneo]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug temporal para ver qué llega
    if(!empty($partidos)) {
        error_log("[Fixture Debug] Muestra: " . json_encode($partidos[0]));
    }
    
    echo json_encode($partidos);
    
} catch (Exception $e) {
    error_log("❌ Error en get_fixture.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar fixture: ' . $e->getMessage()]);
}
?>