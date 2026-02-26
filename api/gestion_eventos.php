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

    $id_actividad = (int)($_POST['id_actividad'] ?? 0);
    $tipo_actividad = $_POST['tipo_actividad'] ?? 'reserva'; // 'reserva' o 'evento'
    $deporte = $_POST['deporte'] ?? '';
    $players_max = (int)($_POST['players_max'] ?? 0);
    $monto_total = (float)($_POST['monto_total'] ?? 0);

    if (!$id_actividad || !in_array($tipo_actividad, ['reserva', 'evento'])) {
        throw new Exception('Actividad inválida');
    }

    // === VALIDAR ACTIVIDAD SEGÚN TIPO ===
    if ($tipo_actividad === 'reserva') {
        $stmt = $pdo->prepare("
            SELECT r.id_reserva as id, r.fecha, r.hora_inicio, r.id_cancha, r.monto_total
            FROM reservas r 
            WHERE r.id_reserva = ? AND r.id_club = ? AND r.estado = 'confirmada'
        ");
        $stmt->execute([$id_actividad, $id_club]);
        $actividad = $stmt->fetch();
        $monto = $actividad['monto_total'] ?? 0;
        $fecha_evento = $actividad['fecha'] ?? null;
    } else {
        $stmt = $pdo->prepare("
            SELECT e.id_evento as id, e.fecha, e.hora, e.valor_cuota
            FROM eventos e 
            WHERE e.id_evento = ? AND e.id_club = ?
        ");
        $stmt->execute([$id_actividad, $id_club]);
        $actividad = $stmt->fetch();
        $monto = $actividad['valor_cuota'] ?? 0;
        $fecha_evento = $actividad['fecha'] ?? null;
    }

    if (!$actividad) {
        throw new Exception('Actividad no encontrada o no pertenece a tu club');
    }

    // === VERIFICAR SI YA ESTÁ INSCRITO ===
    $stmt_check = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?");
    $stmt_check->execute([$id_actividad, $id_socio, $tipo_actividad]);
    $ya_inscrito = $stmt_check->fetch();

    if ($ya_inscrito) {
        // === DAR DE BAJA ===
        $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?")
             ->execute([$id_actividad, $id_socio, $tipo_actividad]);
        $pdo->prepare("DELETE FROM cuotas WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?")
             ->execute([$id_actividad, $id_socio, $tipo_actividad]);

        $accion = 'bajado';
        $mensaje = "✅ Te has dado de baja del evento";
    } else {
        // === VALIDAR CUPO (solo deportes específicos) ===
        if ($tipo_actividad === 'reserva' && in_array($deporte, ['futbolito', 'futsal', 'padel', 'tenis'])) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ? AND tipo_actividad = ?");
            $stmt_count->execute([$id_actividad, $tipo_actividad]);
            $total_inscritos = $stmt_count->fetch()['total'];
            if ($total_inscritos >= $players_max) {
                throw new Exception('Cupo lleno para este evento');
            }
        }

        // === OBTENER POSICIÓN Y EQUIPO POR DEFECTO ===
        $posicion_default = null;
        $equipo_default = 'blanco';
        if ($tipo_actividad === 'reserva') {
            $stmt_puesto = $pdo->prepare("SELECT rol, genero FROM socios WHERE id_socio = ?");
            $stmt_puesto->execute([$id_socio]);
            $socio_info = $stmt_puesto->fetch();

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

            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ? AND tipo_actividad = ?");
            $stmt_count->execute([$id_actividad, $tipo_actividad]);
            $total_inscritos = $stmt_count->fetch()['total'];
            $equipo_default = ($total_inscritos % 2 == 0) ? 'blanco' : 'azul';
        }

        // === INSERTAR EN INSCRITOS ===
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, tipo_actividad, equipo, posicion_jugador)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$id_actividad, $id_socio, $tipo_actividad, $equipo_default, $posicion_default]);

        // === GENERAR CUOTA SI HAY MONTO ===
        if ($monto > 0 && $fecha_evento) {
            $fecha_vencimiento = date('Y-m-d', strtotime($fecha_evento . ' +3 days'));
            $pdo->prepare("
                INSERT INTO cuotas (id_evento, id_socio, monto, fecha_vencimiento, tipo_actividad, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ")->execute([$id_actividad, $id_socio, $monto, $fecha_vencimiento, $tipo_actividad]);
        }

        $accion = 'anotado';
        $mensaje = "✅ ¡Inscripción confirmada!";
    }

    // === NOTIFICACIONES PUSH ===
    $stmt_nombre = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
    $stmt_nombre->execute([$id_socio]);
    $nombre_inscrito = $stmt_nombre->fetch()['nombre'] ?? 'Un jugador';

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

        $msg = ($accion === 'bajado')
            ? "{$nombre_inscrito} se ha dado de baja"
            : "{$nombre_inscrito} se ha anotado";

        foreach ($suscripciones as $sub) {
            $webPush->queueNotification(
                $sub['endpoint'],
                json_encode([
                    'title' => '⚽ CanchaSport',
                    'body' => $msg,
                    'icon' => '/assets/icons/logo2-icon-192x192.png',
                    'badge' => '/assets/icons/logo2-icon-192x192.png',
                    'data' => ['url' => "/pages/dashboard_socio.php?id_club={$club_slug}"]
                ]),
                null,
                ['TTL' => 3600]
            );
        }
        $webPush->flush();
    }

    echo json_encode(['success' => true, 'message' => $mensaje]);

} catch (Exception $e) {
    error_log("Gestión eventos error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>