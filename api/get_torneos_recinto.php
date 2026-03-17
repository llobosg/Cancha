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
            id_torneo,
            nombre,
            descripcion,
            deporte,
            categoria,
            nivel,
            fecha_inicio,
            fecha_fin,
            num_parejas_max,
            estado,
            publico,
            premios,
            created_at,
            slug
        FROM torneos
        WHERE id_recinto = ? 
        AND estado IN ('abierto', 'cerrado', 'en_progreso')
        ORDER BY fecha_inicio DESC
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