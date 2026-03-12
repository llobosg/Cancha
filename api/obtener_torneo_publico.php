<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            id_torneo, nombre, descripcion, deporte, categoria, nivel,
            fecha_inicio, num_parejas_max, publico, slug
        FROM torneos
        WHERE publico = 1 AND estado IN ('abierto', 'cerrado')
        ORDER BY fecha_inicio DESC
    ");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar torneos']);
}
?>