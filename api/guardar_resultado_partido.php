<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    // Validar rol responsable
    if (!isset($_SESSION['club_id']) || empty($_POST['id_reserva']) || empty($_POST['marcador']) || empty($_POST['mejor_jugador'])) {
        throw new Exception('Datos incompletos');
    }
    
    $id_reserva = (int)$_POST['id_reserva'];
    $marcador = $_POST['marcador'];
    $mejor_jugador = (int)$_POST['mejor_jugador'];
    $club_id = $_SESSION['club_id'];
    
    // Verificar que la reserva pertenece al club
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Determinar equipo ganador y marcador numérico
    $goles = explode('-', $marcador);
    $goles_equipo1 = (int)($goles[0] ?? 0);
    $goles_equipo2 = (int)($goles[1] ?? 0);
    
    // Actualizar marcador en ambos equipos
    $pdo->prepare("UPDATE equipos_partido SET marcador_final = ? WHERE id_reserva = ?")
         ->execute([$goles_equipo1, $id_reserva]); // Equipo 1 (Rojos)
    $pdo->prepare("UPDATE equipos_partido SET marcador_final = ? WHERE id_reserva = ? AND nombre_equipo = 'Blancos'")
         ->execute([$goles_equipo2, $id_reserva]); // Equipo 2 (Blancos)
    
    // Marcar equipo ganador
    $pdo->prepare("UPDATE equipos_partido SET ganador = ? WHERE id_reserva = ? AND nombre_equipo = 'Rojos'")
         ->execute([$goles_equipo1 > $goles_equipo2, $id_reserva]);
    $pdo->prepare("UPDATE equipos_partido SET ganador = ? WHERE id_reserva = ? AND nombre_equipo = 'Blancos'")
         ->execute([$goles_equipo2 > $goles_equipo1, $id_reserva]);
    
    // Asignar mejor jugador
    $pdo->prepare("UPDATE jugadores_equipo SET mejor_jugador = 0 WHERE id_equipo IN (SELECT id_equipo FROM equipos_partido WHERE id_reserva = ?)")
         ->execute([$id_reserva]);
    $pdo->prepare("UPDATE jugadores_equipo SET mejor_jugador = 1 WHERE id_socio = ?")
         ->execute([$mejor_jugador]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>