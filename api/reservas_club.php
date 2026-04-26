<?php
if (ob_get_level() === 0) {
    ob_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    
    // Validación básica de sesión
    $id_socio = $_POST['id_socio'] ?? ($_SESSION['id_socio'] ?? null);
    
    if (!$id_socio) {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    require_once __DIR__ . '/../includes/config.php';
    
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error de conexión a la base de datos', 500);
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_disponibilidad':
            handleGetDisponibilidad($pdo, $_POST);
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_log("API Reservas Error: " . $e->getMessage());
    $code = is_numeric($e->getCode()) ? (int)$e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

if (ob_get_level() > 0) {
    ob_end_flush();
}

// === FUNCIÓN PARA OBTENER DISPONIBILIDAD (PLANILLA) ===
function handleGetDisponibilidad($pdo, $post) {
    $deporte = $post['deporte'] ?? '';
    $recinto = $post['recinto'] ?? '';
    $fecha = $post['fecha'] ?? date('Y-m-d');
    
    // 1. Obtener Canchas Disponibles según filtros
    $sql_canchas = "SELECT c.id_cancha, c.nro_cancha, c.nombre_cancha, c.id_deporte, c.valor_arriendo, c.duracion_bloque, r.nombre as recinto_nombre 
                    FROM canchas c 
                    JOIN recintos_deportivos r ON c.id_recinto = r.id_recinto 
                    WHERE c.activa = 1 AND r.email_verified = 1";
    
    $params_canchas = [];
    
    if (!empty($deporte)) {
        $sql_canchas .= " AND c.id_deporte = :deporte";
        $params_canchas[':deporte'] = $deporte;
    }
    
    if (!empty($recinto)) {
        $sql_canchas .= " AND c.id_recinto = :recinto";
        $params_canchas[':recinto'] = $recinto;
    }
    
    $stmt_canchas = $pdo->prepare($sql_canchas);
    $stmt_canchas->execute($params_canchas);
    $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($canchas)) {
        echo json_encode(['canchas' => [], 'reservas' => []]);
        return;
    }
    
    // 2. Obtener RESERVAS CONFIRMADAS solo para la FECHA seleccionada y esas canchas
    $ids_canchas = array_column($canchas, 'id_cancha');
    
    // Crear placeholders para IN (?, ?, ...)
    $placeholders = implode(',', array_fill(0, count($ids_canchas), '?'));
    
    $sql_reservas = "SELECT id_cancha, 
                            TIME_FORMAT(hora_inicio, '%H:%i:%s') as hora_inicio, 
                            TIME_FORMAT(hora_fin, '%H:%i:%s') as hora_fin, 
                            estado, estado_pago, id_reserva 
                    FROM reservas 
                    WHERE fecha = ? 
                    AND id_cancha IN ($placeholders) 
                    AND estado != 'cancelada'";
    
    // Unir fecha con IDs de canchas para los parámetros
    $params_reservas = array_merge([$fecha], $ids_canchas);
    
    $stmt_reservas = $pdo->prepare($sql_reservas);
    $stmt_reservas->execute($params_reservas);
    $reservas = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Devolver estructura separada para facilitar el renderizado JS
    echo json_encode([
        'canchas' => $canchas,
        'reservas' => $reservas
    ]);
}
?>