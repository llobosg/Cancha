<?php
// run_generation.php - Generar disponibilidad desde web

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/api/generar_disponibilidad.php';

try {
    // Generar disponibilidad para los próximos 30 días
    $resultado = generarDisponibilidad($pdo, false, 30);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>