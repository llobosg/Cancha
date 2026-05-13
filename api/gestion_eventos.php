<?php
// === SALIDA LIMPIA: evitar cualquier salida previa ===
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;


try {
    // Validar sesión y parámetros
    if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
        throw new Exception('Acceso no autorizado');
    }

    $action = $_POST['action'] ?? '';
    $id_socio = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'];
    $club_slug = $_SESSION['current_club'] ?? ($_SESSION['club_id'] ?? 'default');

    // Determinar el socio objetivo
    if (isset($_POST['id_socio_objetivo'])) {
        $stmt_check_responsable = $pdo->prepare("SELECT es_responsable FROM socios WHERE id_socio = ? AND id_club = ?");
        $stmt_check_responsable->execute([$_SESSION['id_socio'], $id_club]);
        $es_responsable = $stmt_check_responsable->fetch()['es_responsable'] ?? 0;
        
        if (!$es_responsable) {
            throw new Exception('Solo el responsable puede dar de baja a otros socios');
        }
        $id_socio = (int)$_POST['id_socio_objetivo'];
    } else {
        $id_socio = $_SESSION['id_socio'];
    }

    if ($action !== 'anotarse' && $action !== 'bajarse') {
        throw new Exception('Acción no válida');
    }

    $id_actividad = (int)($_POST['id_actividad'] ?? 0);
    $tipo_actividad = $_POST['tipo_actividad'] ?? 'reserva';
    $deporte = $_POST['deporte'] ?? '';
    $players_max = (int)($_POST['players_max'] ?? 0);
    $monto_total = (float)($_POST['monto_total'] ?? 0);

    if (!$id_actividad || !in_array($tipo_actividad, ['reserva', 'evento'])) {
        throw new Exception('Actividad inválida');
    }

    // === VALIDAR ACTIVIDAD SEGÚN TIPO ===
    if ($tipo_actividad === 'reserva') {
        // ✅ MODIFICADO: Traemos también valor_mes para la validación
        $stmt = $pdo->prepare("
            SELECT r.id_reserva as id, r.fecha, r.hora_inicio, r.id_cancha, r.monto_total, r.valor_mes
            FROM reservas r 
            WHERE r.id_reserva = ? AND r.id_club = ? AND r.estado = 'confirmada'
        ");
        $stmt->execute([$id_actividad, $id_club]);
        $actividad = $stmt->fetch();
        $monto = $actividad['monto_total'] ?? 0;
        $fecha_evento = $actividad['fecha'] ?? null;
        $valor_mes_reserva = (float)($actividad['valor_mes'] ?? 0); // Guardamos el valor mes
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
        $valor_mes_reserva = 0; // Eventos no tienen valor_mes por ahora
    }

    if (!$actividad) {
        throw new Exception('Actividad no encontrada o no pertenece a tu club');
    }

    // === VERIFICAR SI YA ESTÁ INSCRITO ===
    $stmt_check = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?");
    $stmt_check->execute([$id_actividad, $id_socio, $tipo_actividad]);
    $ya_inscrito = $stmt_check->fetch();

    if ($action === 'bajarse') {
        $id_actividad = (int)($_POST['id_actividad'] ?? 0);
        $tipo_actividad = $_POST['tipo_actividad'] ?? 'reserva';
        
        // Determinar qué socio bajar
        $id_socio_a_bajar = isset($_POST['id_socio_objetivo']) && !empty($_POST['id_socio_objetivo']) 
            ? (int)$_POST['id_socio_objetivo'] 
            : $id_socio_actual;

        // Verificar permisos si es un responsable bajando a otro
        if ($id_socio_a_bajar !== $id_socio_actual) {
            // Verificar si el usuario actual tiene permiso para gestionar jugadores
            // Opción A: Por Rol (si usas columnas de rol)
            // Opción B: Por columna 'es_responsable' (más común en tu estructura)
            
            $stmt_check_permiso = $pdo->prepare("SELECT es_responsable FROM socios WHERE id_socio = ?");
            $stmt_check_permiso->execute([$id_socio_actual]);
            $es_resp = $stmt_check_permiso->fetchColumn();

            if (!$es_resp) {
                throw new Exception('No tienes permisos para bajar a otros jugadores');
            }
        }

        // Ejecutar baja
        $stmt_delete = $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?");
        $stmt_delete->execute([$id_actividad, $id_socio_a_bajar, $tipo_actividad]);
        
        // Opcional: Borrar cuota asociada si existe
        $stmt_cuota = $pdo->prepare("DELETE FROM cuotas WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = ?");
        $stmt_cuota->execute([$id_actividad, $id_socio_a_bajar, $tipo_actividad]);
        
        echo json_encode(['success' => true, 'message' => 'Baja registrada']);
    } else {
        // === VALIDAR CUPO ===
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
        $monto_cuota = 0;

        if ($tipo_actividad === 'reserva') {
            $stmt_res = $pdo->prepare("SELECT monto_total, monto_recaudacion, jugadores_esperados FROM reservas WHERE id_reserva = ?");
            $stmt_res->execute([$id_actividad]);
            $reserva = $stmt_res->fetch();

            if ($reserva['monto_recaudacion'] !== null) {
                $monto_cuota = (float)$reserva['monto_recaudacion'];
            } else {
                $monto_cuota = (float)($reserva['monto_total'] ?? 0);
            }
        } elseif ($tipo_actividad === 'evento') {
            $stmt_evt = $pdo->prepare("SELECT valor_cuota FROM eventos WHERE id_evento = ?");
            $stmt_evt->execute([$id_actividad]);
            $evento = $stmt_evt->fetch();
            $monto_cuota = (float)($evento['valor_cuota'] ?? 0);
        }

        // =================================================================
        // === NUEVA LÓGICA: VALIDACIÓN DE PAGO MENSUAL ANTES DE GENERAR CUOTA ===
        // =================================================================
        $generar_cuota = true;
        $mensaje_bloqueo = '';

        if ($tipo_actividad === 'reserva' && $valor_mes_reserva > 0 && $fecha_evento) {
            // 1. Obtener el mes de la reserva actual (ej: 2026-04)
            $mes_actual = date('Y-m', strtotime($fecha_evento));
            
            // 2. Buscar si existe una cuota PAGADA o EN REVISIÓN para este socio, en este club, para este MES, con monto >= valor_mes
            // Usamos una subconsulta para encontrar todas las reservas del mismo mes en el mismo club
            $stmt_validar_pago = $pdo->prepare("
                SELECT c.id_cuota, c.monto, c.estado
                FROM cuotas c
                JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                WHERE c.id_socio = ?
                AND r.id_club = ?
                AND DATE_FORMAT(r.fecha, '%Y-%m') = ?
                AND c.estado IN ('en_revision', 'pagado')
                AND c.monto >= ?
                LIMIT 1
            ");
            
            $stmt_validar_pago->execute([
                $id_socio, 
                $id_club, 
                $mes_actual, 
                $valor_mes_reserva
            ]);
            
            $pago_mensual_existente = $stmt_validar_pago->fetch();
            
            if ($pago_mensual_existente) {
                // ✅ BLOQUEAR: Ya pagó el mes
                $generar_cuota = false;
                $mensaje_bloqueo = 'NO_CUOTA_GENERADA';
            }
        }

        // Si no hay bloqueo, proceder con la inscripción normal
        if (!$generar_cuota) {
            // Inscribir en el evento (inscritos) pero NO generar cuota
            $lleva_cerveza = $_POST['lleva_cerveza'] ?? '0';
            $pdo->prepare("
                INSERT INTO inscritos (id_evento, id_socio, tipo_actividad, equipo, posicion_jugador, lleva_cerveza)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$id_actividad, $id_socio, $tipo_actividad, $equipo_default, $posicion_default, $lleva_cerveza]);
            
            // Retornar respuesta especial para el frontend
            echo json_encode([
                'success' => false, 
                'message' => $mensaje_bloqueo, 
                'detail' => 'Ya has pagado la cuota mensual para este periodo.'
            ]);
            ob_end_flush();
            exit; // Detener ejecución aquí
        }

        // === GENERAR CUOTA SEMANAL NORMAL (Si no fue bloqueado) ===
        $lleva_cerveza = $_POST['lleva_cerveza'] ?? '0';
        
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, tipo_actividad, equipo, posicion_jugador, lleva_cerveza)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$id_actividad, $id_socio, $tipo_actividad, $equipo_default, $posicion_default, $lleva_cerveza]);

        if ($monto_cuota > 0 && $fecha_evento) {
            $fecha_vencimiento = date('Y-m-d', strtotime($fecha_evento . ' +3 days'));
            $pdo->prepare("
                INSERT INTO cuotas (id_evento, id_socio, monto, fecha_vencimiento, tipo_actividad, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ")->execute([$id_actividad, $id_socio, $monto_cuota, $fecha_vencimiento, $tipo_actividad]);
        }

        $mensaje = "✅ ¡Inscripción confirmada!";
    }

    // === NOTIFICACIONES PUSH ===
    if (!empty($club_slug) && $action === 'anotarse') {
        $vapidPublic = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : null;
        $vapidPrivate = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : null;
        
        if ($vapidPublic && $vapidPrivate) {
            try {
                $stmt_nombre = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
                $stmt_nombre->execute([$id_socio]);
                $nombre_inscrito = $stmt_nombre->fetch()['nombre'] ?? 'Un jugador';

                $stmt_subs = $pdo->prepare("
                    SELECT sp.endpoint, sp.p256dh AS publicKey, sp.auth AS authToken
                    FROM suscripciones_push sp
                    JOIN socios s ON sp.id_socio = s.id_socio
                    WHERE s.id_club = ? AND s.id_socio != ?
                    AND sp.endpoint IS NOT NULL AND sp.p256dh IS NOT NULL AND sp.auth IS NOT NULL
                ");
                $stmt_subs->execute([$id_club, $id_socio]);
                $suscripciones = $stmt_subs->fetchAll();

                if (!empty($suscripciones)) {
                    $webPush = new WebPush([
                        'VAPID' => [
                            'subject' => 'https://canchasport.com',
                            'publicKey' => $vapidPublic,
                            'privateKey' => $vapidPrivate,
                        ],
                    ]);

                    $msg = "{$nombre_inscrito} se ha anotado";

                    foreach ($suscripciones as $sub) {
                        if (empty($sub['endpoint']) || empty($sub['publicKey']) || empty($sub['authToken'])) continue;

                        $subscription = \Minishlink\WebPush\Subscription::create([
                            'endpoint' => $sub['endpoint'],
                            'keys' => ['p256dh' => $sub['publicKey'], 'auth' => $sub['authToken']]
                        ]);

                        $webPush->queueNotification($subscription, json_encode([
                            'title' => ' CanchaSport',
                            'body' => $msg,
                            'icon' => '/assets/icons/logo2-icon-192x192.png',
                            'badge' => '/assets/icons/logo2-icon-192x192.png',
                            'data' => ['url' => "/pages/dashboard_socio.php?id_club={$club_slug}"]
                        ]), [], ['TTL' => 3600]);
                    }
                    $webPush->flush();
                }
            } catch (Exception $e) {
                error_log("Push notification error: " . $e->getMessage());
            }
        }
    }

    echo json_encode(['success' => true, 'message' => $mensaje]);

} catch (Exception $e) {
    error_log("Gestión eventos error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
?>