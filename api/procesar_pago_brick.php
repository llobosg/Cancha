<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

header('Content-Type: application/json');

MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$id_cuota = (int)($data['id_cuota'] ?? 0);

if (
    empty($data['token']) ||
    empty($data['paymentMethodId']) ||
    empty($data['transactionAmount']) ||
    empty($data['payer']['email']) ||
    !$id_cuota
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {

    $payment_client = new PaymentClient();

    $request = [
        "transaction_amount" => (float)$data['transactionAmount'],
        "token" => $data['token'],
        "description" => $data['description'] ?? 'Pago cuota',
        "installments" => (int)$data['installments'],
        "payment_method_id" => $data['paymentMethodId'],
        "payer" => [
            "email" => $data['payer']['email']
        ],
        "external_reference" => "cuota_" . $id_cuota
    ];

    // issuer_id (necesario para algunas tarjetas)
    if (!empty($data['issuerId'])) {
        $request["issuer_id"] = $data['issuerId'];
    }

    $payment = $payment_client->create(
        $request,
        [
            "X-Idempotency-Key" => uniqid('mp_', true)
        ]
    );

    $estado = $payment->status;

    // Actualizar cuota SOLO si el pago fue aprobado
    if ($estado === 'approved') {

        $pdo->prepare("
            UPDATE cuotas 
            SET estado = 'pagado',
                fecha_pago = NOW(),
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    } elseif ($estado === 'pending' || $estado === 'in_process') {

        $pdo->prepare("
            UPDATE cuotas 
            SET estado = 'procesando',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    }

    echo json_encode([
        'status' => $estado,
        'message' => $payment->status_detail,
        'payment_id' => $payment->id
    ]);

} catch (MPApiException $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getApiResponse()->getContent()
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}