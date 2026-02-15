<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $id_socio = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'];
    
    if ($action === 'anotarse') {
        $id_reserva = (int)$_POST['id_reserva'];
        $deporte = $_POST['deporte'];
        $players_max = (int)$_POST['players_max'];
        
        // Verificar que la reserva existe y pertenece al club
        $stmt = $pdo->prepare("
            SELECT r.id_reserva, r.id_club, r.fecha, r.hora_inicio, r.id_cancha
            FROM reservas r 
            WHERE r.id_reserva = ? AND r.id_club = ? AND r.tipo_reserva = 'evento'
        ");
        $stmt->execute([$id_reserva, $id_club]);
        $reserva = $stmt->fetch();
        
        if (!$reserva) {
            throw new Exception('Evento no encontrado');
        }
        
        // Verificar si ya está inscrito
        $stmt_check = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ?");
        $stmt_check->execute([$id_reserva, $id_socio]);
        $inscrito_existente = $stmt_check->fetch();
        
        if ($inscrito_existente) {
            // Ya está inscrito → dar de baja
            $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ?")
                 ->execute([$id_reserva, $id_socio]);
            echo json_encode(['success' => true, 'message' => 'Te has dado de baja del evento']);
            exit;
        }
        
        // Validar cupo solo para deportes específicos
        $deportes_con_cupo = ['futbolito', 'futsal', 'padel', 'tenis'];
        if (in_array($deporte, $deportes_con_cupo)) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ?");
            $stmt_count->execute([$id_reserva]);
            $total_inscritos = $stmt_count->fetch()['total'];
            
            if ($total_inscritos >= $players_max) {
                throw new Exception('Cupo lleno para este evento');
            }
        }
        
        // Obtener puesto del socio
        $stmt_puesto = $pdo->prepare("SELECT id_puesto FROM socios WHERE id_socio = ?");
        $stmt_puesto->execute([$id_socio]);
        $puesto = $stmt_puesto->fetch()['id_puesto'] ?? null;
        
        // Insertar en inscritos
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, anotado, equipo, posicion_jugador)
            VALUES (?, ?, 1, NULL, NULL)
        ")->execute([$id_reserva, $id_socio]);
        
        // Mensaje personalizado
        $fecha_formateada = date('d/m', strtotime($reserva['fecha']));
        $mensaje = "Anotado para {$fecha_formateada} {$reserva['hora_inicio']} Cancha {$reserva['id_cancha']}";
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>