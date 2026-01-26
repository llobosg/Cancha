<?php
// api/get_puestos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log("Error get_puestos: " . $e->getMessage());
    echo json_encode([]);
}
?>