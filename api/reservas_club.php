<?php
    header('Content-Type: application/json; charset=utf-8');

    // Configuración robusta de sesiones para APIs
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // Verificar sesión (prioridad: POST > SESSION > COOKIES)
    $id_socio = $_POST['id_socio'] ?? ($_SESSION['id_socio'] ?? ($_COOKIE['cancha_id_socio'] ?? null));
    $club_id = $_POST['club_id'] ?? ($_SESSION['club_id'] ?? ($_COOKIE['cancha_club_id'] ?? null));

    if (!$id_socio || !$club_id) {
        throw new Exception('Acceso no autorizado', 401);
    }

    // Guardar en sesión para futuras solicitudes
    $_SESSION['id_socio'] = $id_socio;
    $_SESSION['club_id'] = $club_id;
    $action = $_GET['action'] ?? '';

    require_once __DIR__ . '/../includes/config.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error de conexión a la base de datos', 500);
    }

     // Verificar socio
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt->execute([$id_socio, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Socio no válido', 401);
    }
    
    switch ($action) {
        case 'get_disponibilidad':
            echo json_encode(getDisponibilidad($_POST, $pdo));
            break;
            
        case 'crear_reserva':
            echo json_encode(crearReserva($_POST, $pdo, $id_club, $id_socio));
            break;
            
        default:
            throw new Exception('Acción no válida');
    }

function getDisponibilidad($post, $pdo) {
    $deporte = $post['deporte'] ?? '';
    $recinto = $post['recinto'] ?? '';
    $rango = $post['rango'] ?? 'semana';
    
    // Determinar fechas
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = date('Y-m-d', strtotime('+6 days')); // Semana por defecto
    
    if ($rango === 'hoy') {
        $fecha_fin = $fecha_inicio;
    } elseif ($rango === 'mañana') {
        $fecha_inicio = date('Y-m-d', strtotime('+1 day'));
        $fecha_fin = $fecha_inicio;
    } elseif ($rango === 'mes') {
        $fecha_fin = date('Y-m-d', strtotime('+30 days'));
    }
    
    // Construir consulta
    $where_conditions = ['c.activa = 1', 'dc.estado = "disponible"'];
    $params = [$fecha_inicio, $fecha_fin];
    
    if ($deporte) {
        $where_conditions[] = 'c.id_deporte = ?';
        $params[] = $deporte;
    }
    
    if ($recinto) {
        $where_conditions[] = 'rd.id_recinto = ?';
        $params[] = $recinto;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id_cancha,
            c.nro_cancha,
            c.id_deporte,
            c.valor_arriendo,
            dc.fecha,
            dc.hora_inicio,
            dc.hora_fin,
            rd.nombre as recinto_nombre
        FROM canchas c
        JOIN recintos_deportivos rd ON c.id_recinto = rd.id_recinto
        JOIN disponibilidad_canchas dc ON c.id_cancha = dc.id_cancha
        WHERE dc.fecha BETWEEN ? AND ? AND $where_clause
        ORDER BY dc.fecha, dc.hora_inicio, c.id_deporte
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function crearReserva($post, $pdo, $id_club, $id_socio) {
    $id_cancha = (int)$post['id_cancha'];
    $fecha = $post['fecha'];
    $hora_inicio = $post['hora_inicio'];
    $hora_fin = $post['hora_fin'];
    $tipo_reserva = $post['tipo_reserva'] ?? 'spot';
    $valor_arriendo = (float)$post['valor_arriendo'];
    
    // Validar que la disponibilidad existe
    $stmt = $pdo->prepare("
        SELECT id_disponibilidad FROM disponibilidad_canchas 
        WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado = 'disponible'
    ");
    $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
    $disponibilidad = $stmt->fetch();
    
    if (!$disponibilidad) {
        throw new Exception('La cancha ya no está disponible para esa fecha y hora');
    }
    
    // Generar código de reserva único
    $codigo_reserva = 'R' . date('Ymd') . str_pad(rand(1000, 9999), 4, '0');
    
    // Crear la reserva principal
    $stmt = $pdo->prepare("
        INSERT INTO reservas (
            id_cancha, id_club, id_socio, fecha, hora_inicio, hora_fin,
            tipo_reserva, monto_total, estado, estado_pago, codigo_reserva
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', 'pendiente', ?)
    ");
    $stmt->execute([
        $id_cancha, $id_club, $id_socio, $fecha, $hora_inicio, $hora_fin,
        $tipo_reserva, $valor_arriendo, $codigo_reserva
    ]);
    
    $id_reserva = $pdo->lastInsertId();
    
    // Actualizar disponibilidad
    $stmt = $pdo->prepare("UPDATE disponibilidad_canchas SET estado = 'reservada', id_reserva = ? WHERE id_disponibilidad = ?");
    $stmt->execute([$id_reserva, $disponibilidad['id_disponibilidad']]);
    
    // Manejar reservas recurrentes
    if ($tipo_reserva === 'semanal' || $tipo_reserva === 'mensual') {
        crearReservasRecurrentes($pdo, $id_cancha, $id_club, $id_socio, $fecha, $hora_inicio, $hora_fin, $tipo_reserva, $valor_arriendo);
    }
    
    // Enviar notificaciones (simulado)
    enviarNotificaciones($pdo, $id_club, $id_cancha, $codigo_reserva);
    
    return ['success' => true, 'codigo_reserva' => $codigo_reserva];
}

function crearReservasRecurrentes($pdo, $id_cancha, $id_club, $id_socio, $fecha_base, $hora_inicio, $hora_fin, $tipo, $valor) {
    $fechas = [];
    $fecha_actual = new DateTime($fecha_base);
    
    if ($tipo === 'semanal') {
        // Mismo día de la semana por 4 semanas
        for ($i = 1; $i <= 4; $i++) {
            $fecha_actual->modify('+7 days');
            $fechas[] = $fecha_actual->format('Y-m-d');
        }
    } else {
        // Mismo día del mes por 4 semanas
        for ($i = 1; $i <= 4; $i++) {
            $fecha_actual->modify('+7 days');
            $fechas[] = $fecha_actual->format('Y-m-d');
        }
    }
    
    foreach ($fechas as $fecha) {
        // Verificar disponibilidad
        $stmt = $pdo->prepare("
            SELECT id_disponibilidad FROM disponibilidad_canchas 
            WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado = 'disponible'
        ");
        $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
        $disponibilidad = $stmt->fetch();
        
        if ($disponibilidad) {
            $codigo_reserva = 'R' . date('Ymd') . str_pad(rand(1000, 9999), 4, '0');
            $stmt = $pdo->prepare("
                INSERT INTO reservas (
                    id_cancha, id_club, id_socio, fecha, hora_inicio, hora_fin,
                    tipo_reserva, monto_total, estado, estado_pago, codigo_reserva
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', 'pendiente', ?)
            ");
            $stmt->execute([
                $id_cancha, $id_club, $id_socio, $fecha, $hora_inicio, $hora_fin,
                $tipo, $valor, $codigo_reserva
            ]);
            
            $id_reserva = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE disponibilidad_canchas SET estado = 'reservada', id_reserva = ? WHERE id_disponibilidad = ?");
            $stmt->execute([$id_reserva, $disponibilidad['id_disponibilidad']]);
        }
    }
}

function enviarNotificaciones($pdo, $id_club, $id_cancha, $codigo_reserva) {
    // Aquí iría la lógica real de notificaciones
    // Por ahora simulamos que se envían correctamente
    
    // Obtener email del responsable del club
    $stmt = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
    $stmt->execute([$id_club]);
    $club_email = $stmt->fetchColumn();
    
    // Obtener email del admin del recinto
    $stmt = $pdo->prepare("SELECT correo_admin FROM recintos_deportivos rd JOIN canchas c ON rd.id_recinto = c.id_recinto WHERE c.id_cancha = ?");
    $stmt->execute([$id_cancha]);
    $recinto_email = $stmt->fetchColumn();
    
    // Simular envío de correos y notificaciones
    return true;
}
?>