<?php
// api/get_inscritos_reserva.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$id_reserva = $_GET['id_reserva'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT s.nombre, i.id_socio
        FROM inscritos i
        JOIN socios s ON i.id_socio = s.id_socio
        WHERE i.id_evento = ?
        ORDER BY i.fecha_inscripcion ASC
    ");
    $stmt->execute([$id_reserva]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marcar cuál es el usuario actual
    foreach ($inscritos as &$inscrito) {
        $inscrito['es_yo'] = ($inscrito['id_socio'] == $_SESSION['id_socio']);
    }
    
    echo json_encode($inscritos);
} catch (Exception $e) {
    echo json_encode([]);
}
?>