<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_socio']) || !isset($_POST['id_reserva'])) {
        throw new Exception('Acceso no autorizado');
    }
    
    $id_reserva = (int)$_POST['id_reserva'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $monto_recaudacion = !empty($_POST['monto_recaudacion']) ? (float)$_POST['monto_recaudacion'] : null;
    $jugadores_esperados = !empty($_POST['jugadores_esperados']) ? (int)$_POST['jugadores_esperados'] : null;
    
    // Validar que pertenece al club
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $_SESSION['club_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Actualizar
    $stmt = $pdo->prepare("
        UPDATE reservas SET 
            fecha = ?, hora_inicio = ?, hora_fin = ?,
            monto_recaudacion = ?, jugadores_esperados = ?
        WHERE id_reserva = ?
    ");
    $stmt->execute([
        $fecha, $hora_inicio, $hora_fin,
        $monto_recaudacion, $jugadores_esperados,
        $id_reserva
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>