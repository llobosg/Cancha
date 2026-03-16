<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

$data = json_decode(file_get_contents('php://input'), true);
$payment_client = new PaymentClient();

try {
    $payment = $payment_client->create([
        "transaction_amount" => (float)$data['transactionAmount'],
        "token" => $data['token'],
        "description" => $data['description'],
        "installments" => (int)$data['installments'],
        "payment_method_id" => $data['paymentMethodId'],
        "payer" => [
            "email" => $data['payer']['email']
        ],
        "external_reference" => "cuota_" . $data['id_cuota']
    ]);

    // Actualizar estado en DB
    $pdo->prepare("UPDATE cuotas SET estado = 'pagado', fecha_pago = NOW(), transaccion_id = ? WHERE id_cuota = ?")
        ->execute([$payment->id, $data['id_cuota']]);

    echo json_encode(['status' => $payment->status, 'message' => $payment->status_detail]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'rejected', 'message' => $e->getMessage()]);
}
?>