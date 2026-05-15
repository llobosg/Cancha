<?php
// api/get_posiciones_torneo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['posiciones' => [], 'torneo_nombre' => '']);
    exit;
}

try {
    // ✅ CONSULTA CORREGIDA: Contar PARTIDOS ganados, no juegos
    $stmt = $pdo->prepare("
        WITH partidos_ganados AS (
            SELECT 
                id_pareja_1, 
                id_pareja_2,
                juegos_pareja_1,
                juegos_pareja_2,
                CASE 
                    WHEN juegos_pareja_1 > juegos_pareja_2 THEN id_pareja_1
                    WHEN juegos_pareja_2 > juegos_pareja_1 THEN id_pareja_2
                    ELSE NULL
                END AS id_pareja_ganadora
            FROM partidos_torneo
            WHERE id_torneo = ? 
            AND estado = 'finalizado'
            AND juegos_pareja_1 IS NOT NULL 
            AND juegos_pareja_2 IS NOT NULL
        ),
        victorias_por_pareja AS (
            SELECT 
                id_pareja_ganadora AS id_pareja,
                COUNT(*) AS sets_ganados
            FROM partidos_ganados
            WHERE id_pareja_ganadora IS NOT NULL
            GROUP BY id_pareja_ganadora
        ),
        todas_parejas AS (
            SELECT id_pareja FROM parejas_torneo WHERE id_torneo = ?
        )
        SELECT 
            tp.id_pareja,
            COALESCE(vp.sets_ganados, 0) AS sets_ganados,
            -- Construir nombre de la pareja con ambos jugadores
            CONCAT(
                COALESCE(s1.alias, s1.nombre, 'Pareja 1'), ' - ',
                COALESCE(s2.alias, s2.nombre, 'Pareja 2')
            ) AS nombre_pareja
        FROM todas_parejas tp
        LEFT JOIN victorias_por_pareja vp ON tp.id_pareja = vp.id_pareja
        LEFT JOIN parejas_torneo pt ON tp.id_pareja = pt.id_pareja
        -- Jugador 1
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        -- Jugador 2 (puede ser NULL si la pareja no está completa)
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        ORDER BY sets_ganados DESC, nombre_pareja ASC
    ");
    
    $stmt->execute([$id_torneo, $id_torneo]);
    $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener nombre del torneo para el header
    $stmt_torneo = $pdo->prepare("SELECT nombre FROM torneos WHERE id_torneo = ?");
    $stmt_torneo->execute([$id_torneo]);
    $torneo_nombre = $stmt_torneo->fetchColumn() ?: 'Torneo';
    
    echo json_encode([
        'posiciones' => $posiciones,
        'torneo_nombre' => $torneo_nombre
    ]);
    
} catch (Exception $e) {
    error_log("Error get_posiciones_torneo: " . $e->getMessage());
    echo json_encode(['posiciones' => [], 'torneo_nombre' => '', 'error' => $e->getMessage()]);
}
?>