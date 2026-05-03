<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id_reserva = (int)($input['id_reserva'] ?? 0);
$id_socio = (int)($input['id_socio'] ?? 0);

if (!$id_reserva || !$id_socio) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    // Verificar disponibilidad
    $stmt = $pdo->prepare("
        SELECT r.jugadores_esperados, COUNT(rp.id_socio) as actuales
        FROM reservas r
        LEFT JOIN reservas_participantes rp ON r.id_reserva = rp.id_reserva
        WHERE r.id_reserva = ?
        GROUP BY r.id_reserva
    ");
    $stmt->execute([$id_reserva]);
    $info = $stmt->fetch();
    
    if ($info['actuales'] >= $info['jugadores_esperados']) {
        echo json_encode(['success' => false, 'message' => 'Cupos completos']);
        exit;
    }
    
    // Insertar inscripción
    $stmt = $pdo->prepare("INSERT INTO reservas_participantes (id_reserva, id_socio, estado) VALUES (?, ?, 'confirmado')");
    $stmt->execute([$id_reserva, $id_socio]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>