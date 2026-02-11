<?php
// run_generation.php - Versión robusta

// Desactivar errores visibles
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/api/generar_disponibilidad.php';

// Limpiar cualquier salida previa
if (ob_get_level() === 0) {
    ob_start();
}

try {
    $resultado = generarDisponibilidad($pdo, false, 30);
    
    // Limpiar buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

// Finalizar buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>