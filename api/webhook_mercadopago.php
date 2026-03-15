<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

$payload = file_get_contents('php://input');

// === OBTENER ID DE PAGO ===
$payment_id = null;

// Caso 1: Notificación real (POST + JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($payload, true);
    if ($data && isset($data['data']['id'])) {
        $payment_id = $data['data']['id'];
    }
}
// Caso 2: Prueba manual (GET)
elseif (isset($_GET['topic']) && $_GET['topic'] === 'payment' && isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
}

if (!$payment_id) {
    http_response_code(400);
    exit;
}

// === VALIDAR FIRMA SOLO EN PRODUCCIÓN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && MERCADOPAGO_ACCESS_TOKEN !== 'TEST-...') {
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $webhook_secret = 'e8f35ec458e3db7c104198628f25b0906d90babc91b0743b74a97d982baa0ddd';
    
    if (!hash_equals(hash_hmac('sha256', $payload, $webhook_secret), $signature)) {
        error_log("⚠️ Webhook: firma HMAC inválida");
        http_response_code(403);
        exit;
    }
}

// === CONSULTAR PAGO EN MERCADO PAGO ===
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
    error_log("Webhook: respuesta inválida de MP para payment_id=$payment_id");
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