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
    
    // ✅ CONSULTA BÁSICA: Obtener todas las canchas activas
    $stmt = $pdo->prepare("
        SELECT 
            c.id_cancha,
            c.nombre_cancha as nro_cancha,
            c.id_deporte,
            c.valor_arriendo,
            r.nombre as recinto_nombre
        FROM canchas c
        JOIN recintos_deportivos r ON c.id_recinto = r.id_recinto
        WHERE c.activa = 1 AND c.estado = 'operativa'
        ORDER BY c.id_cancha
    ");
    $stmt->execute();
    $canchas = $stmt->fetchAll();
    
    // ✅ SIMULAR DISPONIBILIDAD PARA HOY
    $disponibilidad = [];
    foreach ($canchas as $cancha) {
        $disponibilidad[] = [
            'id_cancha' => $cancha['id_cancha'],
            'nro_cancha' => $cancha['nro_cancha'],
            'id_deporte' => $cancha['id_deporte'],
            'valor_arriendo' => $cancha['valor_arriendo'],
            'fecha' => date('Y-m-d'),
            'hora_inicio' => '19:00',
            'hora_fin' => '20:00',
            'recinto_nombre' => $cancha['recinto_nombre'],
            'estado' => 'disponible'
        ];
    }
    
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