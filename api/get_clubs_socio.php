<?php
// api/get_clubs_socio.php - VERSIÓN SIMPLE Y FUNCIONAL
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_socio'])) {
    echo json_encode([]);
    exit;
}

$id_socio = (int)$_SESSION['id_socio'];

try {
    // Consulta ORIGINAL que ya funciona: solo datos crudos, SIN generar slug en SQL
    $stmt = $pdo->prepare("
        SELECT c.id_club, c.nombre AS club_nombre, c.email_responsable
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo'
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$id_socio]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver array limpio (el slug se genera en JS, igual que en tu código que funciona)
    echo json_encode($clubs);
    
} catch (PDOException $e) {
    error_log("Error get_clubs_socio: " . $e->getMessage());
    echo json_encode([]);
}
?>