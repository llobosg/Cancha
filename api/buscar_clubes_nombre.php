<?php
// api/buscar_clubes_nombre.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT nombre, deporte FROM clubs WHERE LOWER(nombre) LIKE ? LIMIT 5");
    $stmt->execute(["%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode([]);
}
?>