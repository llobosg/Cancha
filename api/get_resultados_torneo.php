<?php
// api/get_resultados_torneo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['error' => 'ID de torneo requerido']);
    exit;
}

try {
    // ✅ Consulta con JOINs para traer nombres de parejas
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_partido,
            pt.id_torneo,
            pt.juegos_pareja_1,
            pt.juegos_pareja_2,
            pt.estado,
            -- Pareja 1: obtener nombres de ambos jugadores
            CONCAT(
                COALESCE(s1a.nombre, 'Pareja 1A'), ' - ', 
                COALESCE(s1b.nombre, 'Pareja 1B')
            ) AS pareja1,
            -- Pareja 2: obtener nombres de ambos jugadores
            CONCAT(
                COALESCE(s2a.nombre, 'Pareja 2A'), ' - ', 
                COALESCE(s2b.nombre, 'Pareja 2B')
            ) AS pareja2,
            -- Cancha (opcional)
            c.nombre_cancha,
            -- Orden por id_partido para mantener secuencia de sets
            pt.id_partido
        FROM partidos_torneo pt
        JOIN parejas_torneo p1 ON pt.id_pareja_1 = p1.id_pareja
        JOIN parejas_torneo p2 ON pt.id_pareja_2 = p2.id_pareja
        -- Jugadores de Pareja 1
        LEFT JOIN socios s1a ON p1.id_socio_1 = s1a.id_socio
        LEFT JOIN socios s1b ON p1.id_socio_2 = s1b.id_socio
        -- Jugadores de Pareja 2
        LEFT JOIN socios s2a ON p2.id_socio_1 = s2a.id_socio
        LEFT JOIN socios s2b ON p2.id_socio_2 = s2b.id_socio
        -- Cancha (opcional)
        LEFT JOIN canchas c ON pt.id_cancha = c.id_cancha
        WHERE pt.id_torneo = ?
        ORDER BY pt.id_partido ASC
    ");
    $stmt->execute([$id_torneo]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($partidos);
    
} catch (Exception $e) {
    error_log("Error get_resultados_torneo: " . $e->getMessage());
    echo json_encode(['error' => 'Error al cargar resultados']);
}
?>