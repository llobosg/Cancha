<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    session_start();
    
    // Verificar autenticación de admin de recinto
    if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        // ... otros casos existentes ...
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function procesarPagoReserva($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    
    if (!$id_reserva || !$metodo_pago) {
        throw new Exception('Datos incompletos para procesar pago');
    }
    
    // Verificar que la reserva pertenece al recinto del admin
    $stmt_check = $pdo->prepare("
        SELECT r.id_reserva, r.estado_pago, r.monto_total, c.id_recinto
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt_check->execute([$id_reserva, $_SESSION['id_recinto']]);
    $reserva = $stmt_check->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada o no pertenece a este recinto');
    }
    
    if ($reserva['estado_pago'] === 'pagado') {
        throw new Exception('Esta reserva ya está pagada');
    }
    
    // Actualizar estado de pago
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = 'pagado',
            metodo_pago = ?,
            transaccion_id = ?,
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt_update->execute([$metodo_pago, $transaccion_id ?: null, $id_reserva]);
    
    // Opcional: Enviar email de confirmación de pago
    // enviarEmailConfirmacionPago($reserva, $metodo_pago);
    
    return [
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'id_reserva' => $id_reserva
    ];
}
?>