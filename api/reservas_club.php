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
    
    $rango = $_POST['rango'] ?? 'semana';
    error_log("Rango recibido: $rango");
    
    switch ($rango) {
        case 'hoy':
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d');
            $filtrar_hora = true;
            break;
            
        case 'mañana':
            $fecha_inicio = date('Y-m-d', strtotime('+1 day'));
            $fecha_fin = date('Y-m-d', strtotime('+1 day'));
            $filtrar_hora = false;
            break;
            
        case 'mes':
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime('+30 days'));
            $filtrar_hora = false;
            break;
            
        default: // semana
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime('+7 days'));
            $filtrar_hora = false;
            break;
    }
    
    error_log("Fechas calculadas: $fecha_inicio - $fecha_fin");
    if ($filtrar_hora) {
        error_log("Filtrando por hora: $hora_actual");
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
    if ($filtrar_hora) {
        $sql .= " AND dc.hora_inicio > ?";
        $params[] = $hora_actual;
        error_log("Agregando condición de hora a la consulta");
    }
    
    // Filtros adicionales
    if (!empty($_POST['deporte']) && $_POST['deporte'] !== '') {
        $sql .= " AND c.id_deporte = ?";
        $params[] = $_POST['deporte'];
    }
    
    if (!empty($_POST['recinto']) && $_POST['recinto'] !== '') {
        $sql .= " AND r.id_recinto = ?";
        $params[] = $_POST['recinto'];
    }
    
    $sql .= " ORDER BY dc.fecha, dc.hora_inicio";
    
    error_log("Consulta SQL final: " . $sql);
    error_log("Parámetros: " . json_encode($params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $disponibilidad = $stmt->fetchAll();
    
    error_log("Registros encontrados: " . count($disponibilidad));
    
    echo json_encode($disponibilidad);
    
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