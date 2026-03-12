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
?><?php
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
    $jugador_experto = (int)($_POST['jugador_experto'] ?? 0);
    $club_id = $_SESSION['club_id'];
    
    // Verificar que la reserva pertenece al club
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Actualizar marcadores
    $pdo->prepare("UPDATE equipos_partido SET marcador_final = ? WHERE id_reserva = ? AND nombre_equipo = 'Rojos'")
         ->execute([$goles_rojos, $id_reserva]);
    $pdo->prepare("UPDATE equipos_partido SET marcador_final = ? WHERE id_reserva = ? AND nombre_equipo = 'Blancos'")
         ->execute([$goles_blancos, $id_reserva]);
    
    // Marcar ganador (✅ CORREGIDO: convertir booleano a entero)
    $ganador_rojos = ($goles_rojos > $goles_blancos) ? 1 : 0;
    $ganador_blancos = ($goles_blancos > $goles_rojos) ? 1 : 0;

    $pdo->prepare("UPDATE equipos_partido SET ganador = ? WHERE id_reserva = ? AND nombre_equipo = 'Rojos'")
         ->execute([$ganador_rojos, $id_reserva]);
    $pdo->prepare("UPDATE equipos_partido SET ganador = ? WHERE id_reserva = ? AND nombre_equipo = 'Blancos'")
         ->execute([$ganador_blancos, $id_reserva]);
    
    // Asignar jugador experto
    $pdo->prepare("UPDATE jugadores_equipo SET mejor_jugador = 0 WHERE id_equipo IN (SELECT id_equipo FROM equipos_partido WHERE id_reserva = ?)")
         ->execute([$id_reserva]);
    if ($jugador_experto) {
        $pdo->prepare("UPDATE jugadores_equipo SET mejor_jugador = 1 WHERE id_socio = ?")
             ->execute([$jugador_experto]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>