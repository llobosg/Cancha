<?php
// Desactivar errores visibles
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar sesión mínima
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Acceso denegado', 401);
    }
    
    // Simular datos de prueba
    $datos_prueba = [
        [
            'id_cancha' => 1,
            'nro_cancha' => 'Cancha A',
            'id_deporte' => 'futbol',
            'valor_arriendo' => '15000',
            'fecha' => date('Y-m-d'),
            'hora_inicio' => '19:00',
            'hora_fin' => '20:00',
            'recinto_nombre' => 'Recinto Prueba',
            'estado' => 'disponible'
        ]
    ];
    
    echo json_encode($datos_prueba);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>