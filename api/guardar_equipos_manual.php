<?php
// === SALIDA LIMPIA ===
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    // Validar datos
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['id_reserva'] || !$data['rojos'] || !$data['blancos']) {
        throw new Exception('Datos incompletos');
    }
    
    $id_reserva = (int)$data['id_reserva'];
    $rojos = array_map('intval', $data['rojos']);
    $blancos = array_map('intval', $data['blancos']);
    $club_id = $_SESSION['club_id'];
    
    // Verificar que la reserva pertenece al club
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada');
    }
    
    $pdo->beginTransaction();
    
    // Limpiar equipos anteriores
    $pdo->prepare("
        DELETE je FROM jugadores_equipo je
        JOIN equipos_partido ep ON je.id_equipo = ep.id_equipo
        WHERE ep.id_reserva = ?
    ")->execute([$id_reserva]);
    
    // Crear equipos
    $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Rojos')")->execute([$id_reserva]);
    $id_rojos = $pdo->lastInsertId();
    
    $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Blancos')")->execute([$id_reserva]);
    $id_blancos = $pdo->lastInsertId();
    
    // Asignar jugadores
    foreach ($rojos as $id_socio) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")->execute([$id_rojos, $id_socio]);
    }
    
    foreach ($blancos as $id_socio) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")->execute([$id_blancos, $id_socio]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
?>