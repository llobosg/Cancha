<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $slug = $_GET['slug'] ?? '';
    if (!$slug || strlen($slug) !== 8) {
        throw new Exception('Torneo no válido');
    }

    // Buscar torneo y su recinto
    $stmt_torneo = $pdo->prepare("
        SELECT id_torneo, id_recinto 
        FROM torneos 
        WHERE slug = ? AND estado = 'abierto'
    ");
    $stmt_torneo->execute([$slug]);
    $torneo = $stmt_torneo->fetch();
    if (!$torneo) {
        throw new Exception('Torneo no encontrado o cerrado');
    }

    $id_recinto = $torneo['id_recinto'];
    $id_torneo = $torneo['id_torneo'];

    // Obtener socios del recinto que NO están inscritos en este torneo
    $stmt = $pdo->prepare("
        SELECT s.id_socio, s.alias
        FROM socios s
        WHERE s.id_club = ?
          AND s.id_socio NOT IN (
              SELECT id_socio_1 FROM parejas_torneo WHERE id_torneo = ?
              UNION
              SELECT id_socio_2 FROM parejas_torneo WHERE id_torneo = ?
          )
        ORDER BY s.alias
    ");
    $stmt->execute([$id_recinto, $id_torneo, $id_torneo]);
    $socios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($socios);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>