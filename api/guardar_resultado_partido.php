<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['club_id']) || empty($_POST['id_reserva'])) {
        throw new Exception('Datos incompletos');
    }
    
    $id_reserva = (int)$_POST['id_reserva'];
    $goles_rojos = (int)($_POST['goles_rojos'] ?? 0);
    $goles_blancos = (int)($_POST['goles_blancos'] ?? 0);
    $jugador_experto = !empty($_POST['jugador_experto']) ? (int)$_POST['jugador_experto'] : null;
    $club_id = (int)$_SESSION['club_id'];

    // Verificar que el resultado NO esté ya grabado
    $stmt_check = $pdo->prepare("SELECT goles_rojos FROM reservas WHERE id_reserva = ? AND goles_rojos IS NOT NULL");
    $stmt_check->execute([$id_reserva]);
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El resultado ya fue registrado']);
        exit;
    }

    // Verificar que la reserva pertenece al club
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada o no pertenece al club');
    }

    // Actualizar resultado en la tabla `reservas`
    $pdo->prepare("
        UPDATE reservas 
        SET 
            goles_rojos = ?,
            goles_blancos = ?,
            jugador_experto = ?
        WHERE id_reserva = ?
    ")->execute([
        $goles_rojos,
        $goles_blancos,
        $jugador_experto,
        $id_reserva
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>