<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $pdo->exec("INSERT INTO reservas (id_cancha, fecha, hora_inicio, hora_fin, monto_total, estado) VALUES (1, CURDATE(), '10:00', '11:00', 1000, 'confirmada')");
    echo "OK: Insertado ID " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>