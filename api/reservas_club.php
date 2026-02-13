<?php
if (ob_get_level() === 0) {
    ob_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $id_socio = $_POST['id_socio'] ?? ($_SESSION['id_socio'] ?? ($_COOKIE['cancha_id_socio'] ?? null));
    $club_id = $_POST['club_id'] ?? ($_SESSION['club_id'] ?? ($_COOKIE['cancha_club_id'] ?? null));
    
    if (!$id_socio || !$club_id) {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/disponibilidad.php';
    
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error de conexión a la base de datos', 500);
    }
    
    // Verificar socio
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt->execute([$id_socio, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Socio no válido', 401);
    }
    
    // Calcular rango de fechas y horarios
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = date('Y-m-d', strtotime('+7 days'));
    $hora_actual = date('H:i:s'); // Hora actual del servidor

    switch ($_POST['rango'] ?? 'semana') {
        case 'hoy':
            $fecha_fin = $fecha_inicio;
            // Para hoy, filtrar solo horarios futuros
            $condicion_hora = "AND dc.hora_inicio > ?";
            $params_hora = [$hora_actual];
            break;
            
        case 'mañana':
            $fecha_inicio = date('Y-m-d', strtotime('+1 day'));
            $fecha_fin = $fecha_inicio;
            $condicion_hora = "";
            $params_hora = [];
            break;
            
        case 'mes':
            $fecha_fin = date('Y-m-d', strtotime('+30 days'));
            $condicion_hora = "";
            $params_hora = [];
            break;
            
        default: // semana
            // Para "semana", mostrar desde HOY (no desde ayer)
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime('+7 days'));
            $condicion_hora = "";
            $params_hora = [];
            break;
    }

    // Construir consulta base
    $sql = "
        SELECT 
            dc.id_disponibilidad,
            dc.id_cancha,
            c.nombre_cancha as nro_cancha,
            c.id_deporte,
            c.valor_arriendo,
            dc.fecha,
            dc.hora_inicio,
            dc.hora_fin,
            r.nombre as recinto_nombre,
            dc.estado
        FROM disponibilidad_canchas dc
        JOIN canchas c ON dc.id_cancha = c.id_cancha
        JOIN recintos_deportivos r ON c.id_recinto = r.id_recinto
        WHERE dc.fecha BETWEEN ? AND ?
        AND dc.estado = 'disponible'
    ";

    $params = [$fecha_inicio, $fecha_fin];

    // Agregar condición de hora para "hoy"
    if (!empty($condicion_hora)) {
        $sql .= " " . $condicion_hora;
        $params = array_merge($params, $params_hora);
    }

    $where_conditions = [];
    // Filtros adicionales
    if (!empty($_POST['deporte']) && $_POST['deporte'] !== '') {
        $where_conditions[] = "c.id_deporte = ?";
        $params[] = $_POST['deporte'];
    }

    if (!empty($_POST['recinto']) && $_POST['recinto'] !== '') {
        $where_conditions[] = "r.id_recinto = ?";
        $params[] = $_POST['recinto'];
    }

    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(" AND ", $where_conditions);
    }

    $sql .= " ORDER BY dc.fecha, dc.hora_inicio";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $disponibilidad = $stmt->fetchAll();

    echo json_encode($disponibilidad);

   
    // Manejar diferentes acciones
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_disponibilidad':
            // ... código de disponibilidad anterior ...
            break;
            
        case 'regenerar_disponibilidad':
        // Solo para admins
        if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
            throw new Exception('Acceso no autorizado', 403);
        }
        
        require_once __DIR__ . '/generar_disponibilidad.php';
        $resultado = generarDisponibilidad($pdo, 30);
        echo json_encode($resultado);
        break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_log("API Reservas Error: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (ob_get_level() > 0) {
    ob_end_flush();
}
?>