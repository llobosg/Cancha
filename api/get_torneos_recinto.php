<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    // Recuperar torneos activos del recinto (estado != 'finalizado' y != 'borrador')
    $stmt = $pdo->prepare("
        SELECT 
            t.id_torneo,
            t.nombre,
            t.descripcion,
            t.deporte,
            t.categoria,
            t.nivel,
            t.fecha_inicio,
            t.fecha_fin,
            t.num_parejas_max,
            t.estado,
            t.publico,
            t.premios,
            t.created_at,
            t.slug,
            (SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = t.id_torneo AND estado = 'completa') AS parejas_inscritas
        FROM torneos t
        WHERE t.id_recinto = ? 
        AND t.estado IN ('abierto', 'cerrado', 'en_progreso')
        ORDER BY t.fecha_inicio DESC
    ");
    $stmt->execute([$_SESSION['id_recinto']]);
    $torneos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($torneos);
} catch (Exception $e) {
    error_log("Error en get_torneos_recinto.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar torneos']);
}
?>