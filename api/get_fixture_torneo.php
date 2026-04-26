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
    // ✅ CORRECCIÓN: Leer directamente de partidos_torneo (los resultados YA están ahí)
    // Unir con parejas_torneo para obtener nombres legibles
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido, 
            p.ronda, 
            p.fecha_hora as fecha_hora_programada,
            p.estado,
            -- Pareja 1
            COALESCE(pj1.nombre_pareja, CONCAT('Pareja #', p.id_pareja_1)) as pareja1,
            p.juegos_pareja_1,
            -- Pareja 2
            COALESCE(pj2.nombre_pareja, CONCAT('Pareja #', p.id_pareja_2)) as pareja2,
            p.juegos_pareja_2
        FROM partidos_torneo p
        LEFT JOIN parejas_torneo pj1 ON p.id_pareja_1 = pj1.id_pareja
        LEFT JOIN parejas_torneo pj2 ON p.id_pareja_2 = pj2.id_pareja
        WHERE p.id_torneo = ?
        ORDER BY p.ronda ASC, p.fecha_hora ASC, p.id_partido ASC
    ");
    $stmt->execute([$id_torneo]);
    $fixture = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging (eliminar en producción)
    error_log("[Fixture] Torneo $id_torneo: " . count($fixture) . " partidos encontrados");
    
    echo json_encode($fixture);
    
} catch (Exception $e) {
    error_log("❌ Error en get_fixture_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar fixture: ' . $e->getMessage()]);
}
?>