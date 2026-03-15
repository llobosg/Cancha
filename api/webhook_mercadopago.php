<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

// Validar firma (opcional pero recomendado)
// https://www.mercadopago.cl/developers/es/guides/notifications/webhooks#bookmark_seguridad_del_webhook

try {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    if (!$data || !isset($data['data']['id'])) {
        http_response_code(400);
        exit;
    }

    $payment_id = $data['data']['id'];
    $action = $data['action'] ?? '';

    if ($action !== 'payment.created' && $action !== 'payment.updated') {
        http_response_code(200);
        exit;
    }

    // Consultar estado del pago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$payment_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($response, true);
    if (!$payment || !isset($payment['status'])) {
        error_log("Webhook: respuesta inválida de MP");
        http_response_code(500);
        exit;
    }

    // Extraer ID de cuota
    $external_ref = $payment['external_reference'] ?? '';
    if (!preg_match('/^cuota_(\d+)$/', $external_ref, $matches)) {
        error_log("Webhook: referencia externa inválida: $external_ref");
        http_response_code(400);
        exit;
    }
    $id_cuota = (int)$matches[1];

    // Actualizar estado según resultado
    $estado_pago = $payment['status'];
    if ($estado_pago === 'approved') {
        $pdo->prepare("
            UPDATE cuotas 
            SET estado = 'pagado', fecha_pago = NOW(), transaccion_id = ?
            WHERE id_cuota = ? AND estado = 'pendiente'
        ")->execute([$payment_id, $id_cuota]);

        // Enviar email de confirmación (opcional)
        // enviarEmailConfirmacion($id_cuota);

    } elseif (in_array($estado_pago, ['rejected', 'cancelled'])) {
        $pdo->prepare("
            UPDATE cuotas 
            SET estado = 'fallido', transaccion_id = ?
            WHERE id_cuota = ? AND estado = 'pendiente'
        ")->execute([$payment_id, $id_cuota]);
    }

    http_response_code(200);

} catch (Exception $e) {
    error_log("Error webhook MP: " . $e->getMessage());
    http_response_code(500);
}
?>