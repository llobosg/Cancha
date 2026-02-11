<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/disponibilidad.php';
require_once __DIR__ . '/includes/reservas_recurrentes.php';

// Test 1: Verificar que las funciones se cargan
echo "✅ Funciones cargadas correctamente\n";

// Test 2: Probar getDisponibilidad con fechas actuales
try {
    $hoy = date('Y-m-d');
    $fin_semana = date('Y-m-d', strtotime('+7 days'));
    
    $disponibilidad = getDisponibilidad($pdo, $hoy, $fin_semana);
    echo "✅ getDisponibilidad funcionando\n";
    echo "Registros encontrados: " . count($disponibilidad) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error en getDisponibilidad: " . $e->getMessage() . "\n";
}

// Test 3: Verificar tablas existen
$tables = ['canchas', 'disponibilidad_canchas', 'reservas_recurrentes'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "✅ Tabla $table existe\n";
    } catch (Exception $e) {
        echo "❌ Tabla $table no existe\n";
    }
}
?>