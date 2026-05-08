<?php
// api/mover_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$input = json_decode(file_get_contents('php://input'), true);

$id_reserva = (int)($input['id_reserva'] ?? 0);
$nueva_cancha_id = (int)($input['id_cancha'] ?? 0);
$nueva_fecha = $input['fecha'] ?? '';
$nueva_hora_inicio = $input['hora_inicio'] ?? ''; // Formato HH:MM:SS o HH:MM

try {
    if (!$id_reserva || !$nueva_cancha_id || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception("Faltan datos obligatorios");
    }

    // 1. Obtener datos actuales de la reserva para saber su duración
    $stmt_actual = $pdo->prepare("SELECT id_cancha, hora_inicio, hora_fin FROM reservas WHERE id_reserva = ?");
    $stmt_actual->execute([$id_reserva]);
    $reserva_actual = $stmt_actual->fetch();

    if (!$reserva_actual) {
        throw new Exception("Reserva no encontrada");
    }

    // Calcular duración en minutos para mantenerla constante al mover
    $h_ini_actual = strtotime($reserva_actual['hora_inicio']);
    $h_fin_actual = strtotime($reserva_actual['hora_fin']);
    $duracion_minutos = ($h_fin_actual - $h_ini_actual) / 60;

    // Definir nuevo horario exacto
    $nuevo_inicio = date('H:i:s', strtotime($nueva_fecha . ' ' . $nueva_hora_inicio));
    $nuevo_fin = date('H:i:s', strtotime($nuevo_inicio . ' +' . $duracion_minutos . ' minutes'));

    // 2. VALIDACIÓN DE COLISIÓN (CRÍTICO)
    // Buscar reservas en la NUEVA CANCHA y NUEVA FECHA que se superpongan con el nuevo horario
    // Una colisión ocurre si: (InicioExistente < NuevoFin) AND (FinExistente > NuevoInicio)
    
    $stmt_colision = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM reservas 
        WHERE id_cancha = ? 
        AND fecha = ? 
        AND estado != 'cancelada'
        AND id_reserva != ? -- Excluir la propia reserva que estamos moviendo
        AND hora_inicio < ? -- Comienza antes de que termine la nueva
        AND hora_fin > ?    -- Termina después de que comience la nueva
    ");
    
    $stmt_colision->execute([
        $nueva_cancha_id, 
        $nueva_fecha, 
        $id_reserva, 
        $nuevo_fin, 
        $nuevo_inicio
    ]);
    
    $colisiones = $stmt_colision->fetchColumn();

    if ($colisiones > 0) {
        throw new Exception("⚠️ No se puede mover: Hay otra reserva ocupando ese horario o parte de él.");
    }

    // 3. Ejecutar el movimiento si no hay colisiones
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");
    
    $stmt_update->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $nuevo_inicio,
        $nuevo_fin,
        $id_reserva
    ]);

    echo json_encode(['success' => true, 'message' => 'Reserva movida correctamente']);

} catch (Exception $e) {
    error_log("❌ Error mover reserva: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>