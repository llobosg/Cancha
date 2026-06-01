<?php
// api/search_convenios.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$query = $_GET['q'] ?? '';
$id_recinto = $_SESSION['id_recinto'];

if (empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id_convenio, nombre_empresa, contacto_nombre, contacto_email, porc_dscto 
        FROM convenios 
        WHERE id_recinto = ? 
        AND estado = 'activo'
        AND (nombre_empresa LIKE ? OR contacto_nombre LIKE ? OR contacto_email LIKE ?)
        ORDER BY nombre_empresa ASC
        LIMIT 5
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$id_recinto, $searchTerm, $searchTerm, $searchTerm]);
    $convenios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($convenios);
} catch (Exception $e) {
    error_log("Error search_convenios: " . $e->getMessage());
    echo json_encode(['error' => 'Error al buscar convenios']);
}
?>