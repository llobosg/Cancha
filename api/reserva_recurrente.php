<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_recinto = (int)$_SESSION['id_recinto'];

try {
    // Validar datos básicos
    $required = ['id_cancha', 'hora_inicio', 'hora_fin', 'repeat_day', 'start_date', 'end_date'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $hora_fin = $input['hora_fin'];
    $repeat_day = (int)$input['repeat_day']; // 0=Dom, 1=Lun, ..., 6=Sáb
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $id_socio = $input['id_socio'] ?? null;
    $monto_total = floatval($input['monto_total'] ?? 0);
    $jugadores = intval($input['jugadores_esperados'] ?? 4);
    
    // Verificar que la cancha pertenece al recinto
    $stmt = $pdo->prepare("SELECT id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    if ($stmt->fetchColumn() != $id_recinto) {
        throw new Exception('Cancha no pertenece a este recinto');
    }
    
    // === GENERAR FECHAS VÁLIDAS ===
    $fechas = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    while ($current <= $end) {
        if ((int)$current->format('N') % 7 === $repeat_day) { // Ajuste: format('N') devuelve 1-7
            $fecha_str = $current->format('Y-m-d');
            // Verificar disponibilidad antes de agregar
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservas 
                WHERE id_cancha = ? AND fecha = ? 
                AND hora_inicio = ? AND estado != 'cancelada'
            ");
            $stmt->execute([$id_cancha, $fecha_str, $hora_inicio]);
            if ($stmt->fetchColumn() == 0) {
                $fechas[] = $fecha_str;
            }
        }
        $current->modify('+1 day');
    }
    
    if (empty($fechas)) {
        echo json_encode(['success' => false, 'message' => 'No hay fechas disponibles en el rango seleccionado']);
        exit;
    }
    
    // === CREAR RESERVAS ===
    $created = 0;
    $skipped = 0;
    $pdo->beginTransaction();
    
    foreach ($fechas as $fecha) {
        try {
            // Verificar disponibilidad nuevamente (doble-check para concurrencia)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservas 
                WHERE id_cancha = ? AND fecha = ? 
                AND hora_inicio = ? AND estado != 'cancelada'
            ");
            $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
            
            if ($stmt->fetchColumn() > 0) {
                $skipped++;
                continue;
            }
            
            // Insertar reserva
            $stmt = $pdo->prepare("
                INSERT INTO reservas (
                    id_cancha, id_socio, fecha, hora_inicio, hora_fin,
                    monto_total, jugadores_esperados, estado_pago, estado, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())
            ");
            $stmt->execute([
                $id_cancha, $id_socio, $fecha, $hora_inicio, $hora_fin,
                $monto_total, $jugadores
            ]);
            $created++;
            
        } catch (Exception $e) {
            $skipped++;
            error_log("[Recurrente] Error en fecha $fecha: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'created' => $created,
        'skipped' => $skipped,
        'fechas' => array_slice($fechas, 0, 5) // Solo primeras 5 para no saturar response
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Recurrente] ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>