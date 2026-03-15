<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

// === FUNCIÓN DE VERIFICACIÓN HMAC (solo una vez) ===
function verificarFirmaHMAC($payload, $signature, $secret) {
    if (!$signature || !$secret) return false;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Usa tu Webhook Secret (la IPN que te dio Mercado Pago)
$webhook_secret = 'e8f35ec458e3db7c104198628f25b0906d90babc91b0743b74a97d982baa0ddd';

if (!verificarFirmaHMAC($payload, $signature, $webhook_secret)) {
    error_log("⚠️ Webhook: firma HMAC inválida");
    http_response_code(403);
    exit;
}

// === PROCESAR NOTIFICACIÓN ===
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

} elseif (in_array($estado_pago, ['rejected', 'cancelled'])) {
    $pdo->prepare("
        UPDATE cuotas 
        SET estado = 'fallido', transaccion_id = ?
        WHERE id_cuota = ? AND estado = 'pendiente'
    ")->execute([$payment_id, $id_cuota]);
}

http_response_code(200);
?>