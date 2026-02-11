<?php
require_once __DIR__ . '/includes/config.php';

// Verificar canchas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM canchas WHERE activa = 1");
echo "Canchas activas: " . $stmt->fetch()['total'] . "\n";

// Verificar disponibilidad (si existe la tabla)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM disponibilidad_canchas WHERE estado = 'disponible'");
    echo "Disponibilidad: " . $stmt->fetch()['total'] . "\n";
} catch (Exception $e) {
    echo "Tabla disponibilidad no existe\n";
}

// Verificar recintos
$stmt = $pdo->query("SELECT id_recinto, nombre FROM recintos_deportivos LIMIT 3");
echo "Recintos:\n";
while ($row = $stmt->fetch()) {
    echo "- {$row['nombre']} (ID: {$row['id_recinto']})\n";
}
?>