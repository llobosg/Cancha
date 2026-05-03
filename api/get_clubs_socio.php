<?php
// api/get_clubs_socio.php - CON SLUG CALCULADO EN PHP
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_socio'])) {
    echo json_encode([]);
    exit;
}

$id_socio = (int)$_SESSION['id_socio'];

try {
    $stmt = $pdo->prepare("
        SELECT c.id_club, c.nombre AS club_nombre, c.email_responsable
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo'
        ORDER BY c.nombre ASC
    ");
    $stmt->execute([$id_socio]);
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ AGREGAR SLUG CALCULADO (igual que en tu PHP antiguo)
    $clubs_con_slug = array_map(function($c) {
        return [
            'id_club' => $c['id_club'],
            'club_nombre' => $c['club_nombre'],
            'slug' => substr(md5($c['id_club'] . $c['email_responsable']), 0, 8)
        ];
    }, $clubs);
    
    echo json_encode($clubs_con_slug);
    
} catch (PDOException $e) {
    error_log("Error get_clubs_socio: " . $e->getMessage());
    echo json_encode([]);
}
?>