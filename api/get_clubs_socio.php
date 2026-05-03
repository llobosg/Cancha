<?php
// api/get_clubs_socio.php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id_socio = (int)($_GET['id_socio'] ?? 0);
if (!$id_socio) { echo json_encode([]); exit; }

try {
    // Obtener clubs activos del socio
    $stmt = $pdo->prepare("
        SELECT c.id_club, c.nombre, c.slug 
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo' AND c.activo = 1
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$id_socio]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($clubs);
} catch (PDOException $e) {
    error_log("Error get_clubs_socio: " . $e->getMessage());
    echo json_encode([]);
}
?>