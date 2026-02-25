<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $id_socio = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'];
    $club_slug = $_SESSION['current_club'] ?? '';
    
    if ($action !== 'anotarse') {
        throw new Exception('Acción no válida');
    }
    
    $id_reserva = (int)($_POST['id_reserva'] ?? 0);
    $deporte = $_POST['deporte'] ?? '';
    $players_max = (int)($_POST['players_max'] ?? 0);
    
    if (!$id_reserva) {
        throw new Exception('Reserva inválida');
    }
    
    // Verificar que la reserva existe y pertenece al club
    $stmt = $pdo->prepare("
        SELECT r.id_reserva, r.id_club, r.fecha, r.hora_inicio, r.id_cancha, r.monto_total
        FROM reservas r 
        WHERE r.id_reserva = ? AND r.id_club = ? AND r.estado = 'confirmada'
    ");
    $stmt->execute([$id_reserva, $id_club]);
    $reserva = $stmt->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada o no pertenece a tu club');
    }
    
    // Verificar si ya está inscrito
    $stmt_check = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ?");
    $stmt_check->execute([$id_reserva, $id_socio]);
    $ya_inscrito = $stmt_check->fetch();
    
    if ($ya_inscrito) {
        // Dar de baja
        $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ?")
             ->execute([$id_reserva, $id_socio]);
        
        $accion = 'bajado';
        $mensaje = "✅ Te has dado de baja del evento";
    } else {
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
        
        // Obtener puesto del socio para determinar posición por defecto
        $stmt_puesto = $pdo->prepare("SELECT rol, genero FROM socios WHERE id_socio = ?");
        $stmt_puesto->execute([$id_socio]);
        $socio_info = $stmt_puesto->fetch();
        
        $posicion_default = null;
        if ($socio_info) {
            $rol = $socio_info['rol'];
            if (strpos($rol, 'Arquero') !== false || strpos($rol, 'Portero') !== false) {
                $posicion_default = 'arquero';
            } elseif (strpos($rol, 'Defensa') !== false) {
                $posicion_default = 'defensa';
            } elseif (strpos($rol, 'Delantero') !== false) {
                $posicion_default = 'delantero';
            } elseif (strpos($rol, 'Medio') !== false || strpos($rol, 'Central') !== false) {
                $posicion_default = 'medio';
            }
        }
        
        // Determinar equipo por defecto
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ?");
        $stmt_count->execute([$id_reserva]);
        $total_inscritos = $stmt_count->fetch()['total'];
        $equipo_default = ($total_inscritos % 2 == 0) ? 'blanco' : 'azul';
        
        // Insertar en inscritos
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, anotado, equipo, posicion_jugador)
            VALUES (?, ?, 1, ?, ?)
        ")->execute([$id_reserva, $id_socio, $equipo_default, $posicion_default]);
        
        $accion = 'anotado';
        $mensaje = "✅ ¡Inscripción confirmada!";
    }

    // === ENVIAR NOTIFICACIONES PUSH A SOCIOS DEL CLUB ===
    $stmt_subs = $pdo->prepare("
        SELECT sp.endpoint, sp.p256dh, sp.auth
        FROM suscripciones_push sp
        JOIN socios s ON sp.id_socio = s.id_socio
        WHERE s.id_club = ? AND s.id_socio != ?
    ");
    $stmt_subs->execute([$id_club, $id_socio]);
    $suscripciones = $stmt_subs->fetchAll();

    if (!empty($suscripciones)) {
        require_once __DIR__ . '/../vendor/autoload.php';
        use Minishlink\WebPush\WebPush;

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'https://canchasport.com',
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);

        $mensaje = ($accion === 'bajado')
            ? "{$nombre_inscrito} se ha dado de baja del evento"
            : "{$nombre_inscrito} se ha anotado al evento";

        foreach ($suscripciones as $sub) {
            $webPush->queueNotification(
                $sub['endpoint'],
                json_encode([
                    'title' => '⚽ CanchaSport',
                    'body' => $mensaje,
                    'icon' => '/assets/icons/logo2-icon-192x192.png',
                    'badge' => '/assets/icons/logo2-icon-192x192.png',
                    'data' => ['url' => "/pages/dashboard_socio.php?id_club={$club_slug}"]
                ]),
                null,
                ['TTL' => 60 * 60] // 1 hora
            );
        }

        $webPush->flush();
    }
    
    // Mensaje único para el usuario que actuó
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    error_log("Gestión eventos error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>