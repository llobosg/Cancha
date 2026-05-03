<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id_reserva = (int)($input['id_reserva'] ?? 0);
$id_socio_a_bajar = (int)($input['id_socio_a_bajar'] ?? 0);
$id_responsable = (int)($input['id_responsable'] ?? 0);

// Verificar que el responsable tenga permiso
$stmt = $pdo->prepare("SELECT rol FROM socios WHERE id_socio = ?");
$stmt->execute([$id_responsable]);
$rol = $stmt->fetchColumn();

if (!in_array($rol, ['Delegado', 'Director'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM reservas_participantes WHERE id_reserva = ? AND id_socio = ?");
    $stmt->execute([$id_reserva, $id_socio_a_bajar]);
    
    // Notificar al jugador bajado (opcional)
    // require_once __DIR__ . '/../includes/notificaciones.php';
    // enviarNotificacion($id_socio_a_bajar, 'Te han bajado de un partido');
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>