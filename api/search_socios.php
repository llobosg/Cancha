<?php
// api/search_socios.php
header('Content-Type: application/json');
if (ob_get_level() > 0) ob_clean(); // Limpiar buffer para evitar HTML mezclado

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Buscar por nombre, email o celular
    $stmt = $pdo->prepare("
        SELECT id_socio, nombre, email, celular 
        FROM socios 
        WHERE nombre LIKE ? OR email LIKE ? OR celular LIKE ? 
        LIMIT 10
    ");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($resultados);
} catch (Exception $e) {
    error_log("Error en search_socios.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>