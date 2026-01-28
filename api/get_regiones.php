<?php
// api/get_regiones.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("
        SELECT codigo_region, nombre_region, codigo_ciudad, nombre_ciudad, codigo_comuna, nombre_comuna 
        FROM regiones_chile 
        ORDER BY nombre_region, nombre_ciudad, nombre_comuna
    ");
    
    $datos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $region = $row['codigo_region'];
        $ciudad = $row['codigo_ciudad'];
        
        // Asegurar que existan las estructuras
        if (!isset($datos[$region])) {
            $datos[$region] = ['ciudades' => [], 'comunas' => []];
        }
        
        // Agregar ciudad si no existe
        if (!isset($datos[$region]['ciudades'][$ciudad])) {
            $datos[$region]['ciudades'][$ciudad] = $row['nombre_ciudad'];
        }
        
        // Agregar comuna
        $datos[$region]['comunas'][$ciudad][] = $row['nombre_comuna'];
    }
    
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar regiones']);
}
?>