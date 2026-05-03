<?php
// api/get_clubs_socio.php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id_socio = (int)($_GET['id_socio'] ?? 0);
if (!$id_socio) { echo json_encode([]); exit; }

try {
    // ✅ CONSULTA ORIGINAL: solo filtrar por estado de la relación
    $stmt = $pdo->prepare("
        SELECT c.id_club, c.nombre, 
               SUBSTR(MD5(CONCAT(c.id_club, c.email_responsable)), 1, 8) as slug
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo'
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$id_socio]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log("Error get_clubs_socio: " . $e->getMessage());
    echo json_encode([]);
}
?>