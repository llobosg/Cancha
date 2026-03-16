<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

header("Content-Type: application/json");

// Detectar formato de webhook
$type = $_GET['type'] ?? $_GET['topic'] ?? '';
$payment_id = $_GET['data.id'] ?? $_GET['id'] ?? '';

if ($type !== 'payment' || !$payment_id) {
    http_response_code(200);
    exit;
}

// Consultar pago en MercadoPago
$ch = curl_init("https://api.mercadopago.com/v1/payments/$payment_id");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . MERCADOPAGO_ACCESS_TOKEN
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$payment = json_decode($response, true);

if (!$payment || empty($payment['status'])) {
    error_log("Webhook MP: respuesta inválida para payment_id=$payment_id");
    http_response_code(500);
    exit;
}

// Obtener referencia externa
$external_ref = $payment['external_reference'] ?? '';

if (!preg_match('/^cuota_(\d+)$/', $external_ref, $matches)) {
    error_log("Webhook MP: referencia inválida $external_ref");
    http_response_code(200);
    exit;
}

$id_cuota = (int)$matches[1];
$status = $payment['status'];

// Buscar estado actual
$stmt = $pdo->prepare("SELECT estado FROM cuotas WHERE id_cuota = ?");
$stmt->execute([$id_cuota]);
$estado_actual = $stmt->fetchColumn();

if (!$estado_actual) {
    error_log("Webhook MP: cuota no encontrada $id_cuota");
    http_response_code(200);
    exit;
}

// Evitar reprocesar pagos ya confirmados
if ($estado_actual === 'pagado') {
    http_response_code(200);
    exit;
}

switch ($status) {

    case 'approved':

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'pagado',
                fecha_pago = NOW(),
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment_id, $id_cuota]);

        break;

    case 'pending':
    case 'in_process':

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'procesando',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment_id, $id_cuota]);

        break;

    case 'rejected':
    case 'cancelled':

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'fallido',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment_id, $id_cuota]);

        break;

    case 'refunded':
    case 'charged_back':

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'reembolsado',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment_id, $id_cuota]);

        break;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);