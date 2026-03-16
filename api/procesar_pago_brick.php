<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Datos inválidos'
    ]);
    exit;
}

$id_cuota = (int)($data['id_cuota'] ?? 0);

if (
    empty($data['token']) ||
    empty($data['paymentMethodId']) ||
    empty($data['payer']['email']) ||
    !$id_cuota
) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Datos incompletos'
    ]);
    exit;
}

try {

    // Obtener datos de la cuota desde DB (seguridad)
    $stmt = $pdo->prepare("
        SELECT monto, estado
        FROM cuotas
        WHERE id_cuota = ?
        LIMIT 1
    ");
    $stmt->execute([$id_cuota]);
    $cuota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuota) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cuota no encontrada'
        ]);
        exit;
    }

    // Evitar pagar cuotas ya pagadas
    if ($cuota['estado'] === 'pagado') {
        echo json_encode([
            'status' => 'approved',
            'message' => 'La cuota ya fue pagada'
        ]);
        exit;
    }

    $monto = (float)$cuota['monto'];

    $payment_client = new PaymentClient();

    $payment_data = [
        "transaction_amount" => $monto,
        "token" => $data['token'],
        "description" => $data['description'] ?? 'Pago cuota',
        "installments" => (int)($data['installments'] ?? 1),
        "payment_method_id" => $data['paymentMethodId'],
        "payer" => [
            "email" => $data['payer']['email']
        ],
        "external_reference" => "cuota_" . $id_cuota
    ];

    // issuer_id necesario para algunas tarjetas
    if (!empty($data['issuerId'])) {
        $payment_data["issuer_id"] = $data['issuerId'];
    }

    $payment = $payment_client->create(
        $payment_data,
        [
            "X-Idempotency-Key" => uniqid('mp_', true)
        ]
    );

    $estado = $payment->status;

    // Actualizar estado según resultado
    if ($estado === 'approved') {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'pagado',
                fecha_pago = NOW(),
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    } elseif (in_array($estado, ['pending','in_process'])) {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'procesando',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    } elseif (in_array($estado, ['rejected','cancelled'])) {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'fallido',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);
    }

    echo json_encode([
        'status' => $estado,
        'message' => $payment->status_detail ?? '',
        'payment_id' => $payment->id
    ]);

} catch (MPApiException $e) {

    http_response_code(500);

    $apiResponse = $e->getApiResponse();

    echo json_encode([
        'status' => 'error',
        'message' => $apiResponse ? $apiResponse->getContent() : $e->getMessage()
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>