<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $id_reserva = $_GET['id_reserva'] ?? null;
    if (!$id_reserva || !isset($_SESSION['club_id'])) {
        throw new Exception('Datos incompletos');
    }

    $stmt = $pdo->prepare("
        SELECT s.id_socio, s.alias 
        FROM inscritos i
        JOIN socios s ON i.id_socio = s.id_socio
        WHERE i.id_evento = ? AND i.tipo_actividad = 'reserva'
        ORDER BY s.alias
    ");
    $stmt->execute([$id_reserva]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>