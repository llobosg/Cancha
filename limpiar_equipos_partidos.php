<?php
require_once __DIR__ . '/includes/config.php';

try {
    // Iniciar transacción (opcional, pero recomendado)
    $pdo->beginTransaction();

    // Eliminar todos los registros de equipos_partido
    $stmt = $pdo->prepare("DELETE FROM equipos_partido");
    $stmt->execute();

    // Reiniciar auto-incremento (opcional)
    $pdo->exec("ALTER TABLE equipos_partido AUTO_INCREMENT = 1");

    // Confirmar cambios
    $pdo->commit();

    echo "✅ Tabla 'equipos_partido' limpiada correctamente.\n";
    echo "🗑️ Registros eliminados: " . $stmt->rowCount() . "\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error al limpiar la tabla: " . $e->getMessage() . "\n";
}
?>