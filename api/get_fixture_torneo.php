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
    // ✅ CORRECCIÓN: Eliminada columna 'ronda' que no existe en tu tabla
    // Usamos las columnas reales: juegos_pareja_1/2, fecha_hora_programada, etc.
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido, 
            p.fecha_hora_programada,
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
        ORDER BY p.fecha_hora_programada ASC, p.id_partido ASC
    ");
    $stmt->execute([$id_torneo]);
    $fixture = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("[Fixture] Torneo $id_torneo: " . count($fixture) . " partidos encontrados");
    
    echo json_encode($fixture);
    
} catch (Exception $e) {
    error_log("❌ Error en get_fixture_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar fixture: ' . $e->getMessage()]);
}
?>