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
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
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

function procesarPagoParcial($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $monto_pagado = (float)($data['monto_pagado'] ?? 0);
    $monto_total_original = (float)($data['monto_total_original'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    $notas_pago = trim($data['notas_pago'] ?? '');
    
    if (!$id_reserva || !$metodo_pago || $monto_pagado <= 0) {
        throw new Exception('Datos incompletos o inválidos');
    }
    
    // Verificar reserva
    $stmt_check = $pdo->prepare("SELECT id_reserva, estado_pago, monto_total FROM reservas WHERE id_reserva = ?");
    $stmt_check->execute([$id_reserva]);
    $reserva = $stmt_check->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Determinar nuevo estado de pago
    $nuevo_estado_pago = 'pendiente'; // Por defecto
    
    // Si el monto pagado cubre el total (o más), marcamos como pagado
    if ($monto_pagado >= $monto_total_original) {
        $nuevo_estado_pago = 'pagado';
    } 
    // Opcional: Si tu tabla tiene estado 'parcial', úsalo aquí
    // elseif ($monto_pagado > 0 && $monto_pagado < $monto_total_original) {
    //     $nuevo_estado_pago = 'parcial'; 
    // }

    // Actualizar la reserva
    // NOTA: Aquí sumamos el monto pagado a un campo acumulado si tuvieras uno, 
    // o simplemente actualizamos el estado y guardamos las notas en el campo 'notas' de la reserva.
    // Si necesitas un historial de pagos, deberías insertar en una tabla 'historial_pagos'.
    // Por ahora, actualizaremos la reserva principal.
    
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            notas = CONCAT(IFNULL(notas, ''), '\n[PAGO REGISTRADO]: ' . ?), -- Agregamos notas al final
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    
    $nota_formateada = "Pago de $" . number_format($monto_pagado, 0) . " vía " . $metodo_pago . ". Detalle: " . $notas_pago;
    
    $stmt_update->execute([$nuevo_estado_pago, $metodo_pago, $transaccion_id, $nota_formateada, $id_reserva]);
    
    // Opcional: Aquí podrías insertar un registro en una tabla 'movimientos_caja' o 'historial_pagos'
    // para llevar el control de quién pagó qué parte exactamente.
    
    return [
        'success' => true,
        'message' => 'Pago registrado. Estado actualizado a: ' . $nuevo_estado_pago,
        'estado_nuevo' => $nuevo_estado_pago
    ];
}
?>