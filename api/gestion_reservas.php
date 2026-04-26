<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    
    // 1. Verificar autenticación básica
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Sesión no iniciada', 401);
    }

    // 2. Verificar Rol (ACTUALIZADO PARA NUEVOS ROLES)
    $rol_actual = $_SESSION['recinto_rol'] ?? '';
    $roles_permitidos = ['admin', 'asistente']; // Aceptamos ambos roles

    if (!in_array($rol_actual, $roles_permitidos)) {
        error_log("❌ [API GESTIÓN RESERVAS] Acceso denegado. Rol actual: '$rol_actual'. Roles permitidos: " . implode(', ', $roles_permitidos));
        throw new Exception('Acceso no autorizado: Rol inválido', 401);
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Log de auditoría para saber qué acción se intenta
    error_log("🎯 [API GESTIÓN RESERVAS] Acción: $action | Usuario: " . ($_SESSION['recinto_usuario'] ?? 'Desconocido'));

    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
        // Agrega aquí otros casos si los tienes (anular, cancelar, etc.)
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function procesarPagoReserva($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    $monto_total = (float)($data['monto_total'] ?? 0); // Asumimos pago total
    
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
    
    // Actualizar estado de pago (PAGO COMPLETO)
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = 'pagado',
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?, -- Actualizamos el monto recaudado
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt_update->execute([$metodo_pago, $transaccion_id ?: null, $monto_total, $id_reserva]);
    
    // NOTA: Función enviarEmailConfirmacionPago eliminada porque no existe.
    // Si necesitas enviar emails, debes crear esa función o usar mail() directamente.
    
    return [
        'success' => true,
        'message' => 'Pago total registrado correctamente',
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
    
    // 1. Obtener datos actuales (incluyendo monto_recaudacion actual si hubo pagos previos)
    $stmt_check = $pdo->prepare("SELECT id_reserva, estado_pago, monto_total, monto_recaudacion, notas FROM reservas WHERE id_reserva = ?");
    $stmt_check->execute([$id_reserva]);
    $reserva = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Si ya está pagado completamente, bloquear
    if ($reserva['estado_pago'] === 'pagado') {
        throw new Exception('Esta reserva ya está marcada como PAGADA.');
    }
    
    // Calcular nuevo monto recaudado acumulado
    $monto_recaudado_actual = (float)($reserva['monto_recaudacion'] ?? 0);
    $nuevo_monto_recaudado = $monto_recaudado_actual + $monto_pagado;
    
    // 2. Determinar nuevo estado
    $nuevo_estado_pago = 'parcial'; // Por defecto es parcial
    
    // Si el acumulado cubre el total, marcamos como pagado
    if ($nuevo_monto_recaudado >= $monto_total_original) {
        $nuevo_estado_pago = 'pagado';
        // Ajustar al monto exacto si se pasó (opcional)
        // $nuevo_monto_recaudado = $monto_total_original; 
    }
    
    // 3. Construir el texto de notas
    $fecha_hoy = date('d/m/Y H:i');
    $nota_nueva = "\n[PAGO {$fecha_hoy}]: $" . number_format($monto_pagado, 0, ',', '.') . " vía {$metodo_pago}";
    
    if (!empty($notas_pago)) {
        $nota_nueva .= " - Obs: {$notas_pago}";
    }
    
    $notas_finales = !empty($reserva['notas']) ? $reserva['notas'] . $nota_nueva : ltrim($nota_nueva);
    
    // 4. Actualizar la reserva
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?, -- ACTUALIZADO: Guardamos el acumulado
            notas = ?,
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    
    $stmt_update->execute([
        $nuevo_estado_pago,
        $metodo_pago,
        $transaccion_id,
        $nuevo_monto_recaudado,
        $notas_finales,
        $id_reserva
    ]);
    
    return [
        'success' => true,
        'message' => 'Pago registrado correctamente. Estado: ' . $nuevo_estado_pago,
        'estado_nuevo' => $nuevo_estado_pago,
        'monto_recaudado' => $nuevo_monto_recaudado
    ];
}
?>