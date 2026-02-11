<?php
// api/reservas_club.php - Versión robusta

// Prevenir cualquier salida antes del JSON
if (ob_get_level() === 0) {
    ob_start();
}

// Manejo de errores silencioso
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar sesión
    $id_socio = $_SESSION['id_socio'] ?? null;
    $club_id = $_SESSION['club_id'] ?? null;
    
    // Fallback con cookies
    if (!$id_socio || !$club_id) {
        $id_socio = $_COOKIE['cancha_id_socio'] ?? null;
        $club_id = $_COOKIE['cancha_club_id'] ?? null;
    }
    
    if (!$id_socio || !$club_id) {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    // Cargar configuración de base de datos
    require_once __DIR__ . '/../includes/config.php';
    
    // Verificar que la conexión exista
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Error de conexión a la base de datos', 500);
    }
    
    // Verificar que el socio exista
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt->execute([$id_socio, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Socio no válido', 401);
    }
    
    // Obtener parámetros de la solicitud
    $action = $_GET['action'] ?? '';
    $deporte = $_POST['deporte'] ?? '';
    $recinto = $_POST['recinto'] ?? '';
    $rango = $_POST['rango'] ?? 'semana';
    
    // Simular datos de disponibilidad (reemplazar con lógica real)
    $disponibilidad = [
        [
            'id_cancha' => 1,
            'nro_cancha' => 'Cancha A',
            'id_deporte' => 'futbol',
            'valor_arriendo' => '15000',
            'fecha' => date('Y-m-d'),
            'hora_inicio' => '19:00',
            'hora_fin' => '20:00',
            'recinto_nombre' => 'Recinto Prueba',
            'estado' => 'disponible'
        ],
        [
            'id_cancha' => 2,
            'nro_cancha' => 'Cancha B',
            'id_deporte' => 'futbolito',
            'valor_arriendo' => '12000',
            'fecha' => date('Y-m-d', strtotime('+1 day')),
            'hora_inicio' => '20:00',
            'hora_fin' => '21:00',
            'recinto_nombre' => 'Recinto Prueba',
            'estado' => 'disponible'
        ]
    ];
    
    // Filtrar según parámetros (simplificado)
    if ($deporte) {
        $disponibilidad = array_filter($disponibilidad, function($item) use ($deporte) {
            return $item['id_deporte'] === $deporte;
        });
    }
    
    echo json_encode(array_values($disponibilidad));
    
} catch (Exception $e) {
    // Limpiar cualquier salida previa
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Registrar error en logs
    error_log("API Reservas Error: " . $e->getMessage());
    
    // Devolver respuesta JSON limpia
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Finalizar buffer de salida
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>