<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

// === SOLO USAR PARÁMETROS GET (formato real de Mercado Pago) ===
$topic = $_GET['topic'] ?? '';
$id = $_GET['id'] ?? '';

if ($topic !== 'payment' || !$id) {
    http_response_code(400);
    exit;
}

// === CONSULTAR PAGO EN MERCADO PAGO ===
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$id");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . MERCADOPAGO_ACCESS_TOKEN,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$payment = json_decode($response, true);
if (!$payment || !isset($payment['status'])) {
    error_log("Webhook: respuesta inválida de MP para payment_id=$id");
    http_response_code(500);
    exit;
}

// === EXTRAER ID DE CUOTA ===
$external_ref = $payment['external_reference'] ?? '';
if (!preg_match('/^cuota_(\d+)$/', $external_ref, $matches)) {
    error_log("Webhook: referencia externa inválida: $external_ref");
    http_response_code(400);
    exit;
}
$id_cuota = (int)$matches[1];

// === ACTUALIZAR ESTADO ===
$estado_pago = $payment['status'];
if ($estado_pago === 'approved') {
    $pdo->prepare("
        UPDATE cuotas 
        SET estado = 'pagado', fecha_pago = NOW(), transaccion_id = ?
        WHERE id_cuota = ? AND estado = 'pendiente'
    ")->execute([$id, $id_cuota]);
} elseif (in_array($estado_pago, ['rejected', 'cancelled'])) {
    $pdo->prepare("
        UPDATE cuotas 
        SET estado = 'fallido', transaccion_id = ?
        WHERE id_cuota = ? AND estado = 'pendiente'
    ")->execute([$id, $id_cuota]);
}

http_response_code(200);
?>