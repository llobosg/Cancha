<?php

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* IMPORTACIONES PHP */
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

/* DEPENDENCIAS */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

/* BUFFER para evitar HTML en JSON */
ob_start();

function json_response($data, $http = 200)
{
    http_response_code($http);
    echo json_encode($data);
    exit;
}

try {

    MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

    /* -----------------------------------------
       Leer JSON enviado por el Brick
    ------------------------------------------*/

    $input = file_get_contents("php://input");

    if (!$input) {
        json_response([
            "status" => "error",
            "message" => "Body vacío"
        ], 400);
    }

    $data = json_decode($input, true);

    if (!$data) {
        json_response([
            "status" => "error",
            "message" => "JSON inválido"
        ], 400);
    }

    error_log("Brick payload: " . json_encode($data));

    /* -----------------------------------------
       Normalizar datos del Brick
    ------------------------------------------*/

    $token = $data["token"] ?? null;

    $paymentMethodId =
        $data["paymentMethodId"] ??
        $data["payment_method_id"] ??
        null;

    $issuerId =
        $data["issuerId"] ??
        $data["issuer_id"] ??
        null;

    $installments =
        $data["installments"] ??
        1;

    $email =
        $data["payer"]["email"] ??
        null;

    $id_cuota =
        $data["id_cuota"] ??
        null;

    if (!$token || !$paymentMethodId || !$email || !$id_cuota) {

        json_response([
            "status" => "error",
            "message" => "Datos incompletos",
            "debug" => [
                "token" => !!$token,
                "paymentMethodId" => !!$paymentMethodId,
                "email" => !!$email,
                "id_cuota" => !!$id_cuota
            ]
        ], 400);
    }

    /* -----------------------------------------
       Obtener monto real desde DB
    ------------------------------------------*/

    $stmt = $pdo->prepare("
        SELECT monto, estado
        FROM cuotas
        WHERE id_cuota = ?
        LIMIT 1
    ");

    $stmt->execute([$id_cuota]);

    $cuota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuota) {

        json_response([
            "status" => "error",
            "message" => "Cuota no encontrada"
        ], 404);
    }

    if ($cuota["estado"] === "pagado") {

        json_response([
            "status" => "approved",
            "message" => "La cuota ya está pagada"
        ]);
    }

    $monto = (float)$cuota["monto"];

    /* -----------------------------------------
       Crear pago en MercadoPago
    ------------------------------------------*/

    $client = new PaymentClient();

    // Limpiar el RUT: solo números y letra K
    $rut_clean = preg_replace('/[^0-9kK]/', '', $data["payer"]["identification"]["number"]);

    $payment_data = [
        "transaction_amount" => (float)$monto,
        "token" => $token,
        "description" => $data["description"] ?? "Pago cuota CanchaSport",
        "installments" => (int)$installments,
        "payment_method_id" => $paymentMethodId,
        "issuer_id" => $data["issuer_id"] ?? null, // INDISPENSABLE si viene en el brick
        "payer" => [
            "email" => $email,
            "identification" => [
                "type" => "RUT", // Cambiado de RUN a RUT
                "number" => $rut_clean
            ]
        ],
        "external_reference" => "cuota_" . $id_cuota,
        "statement_descriptor" => "CANCHASPORT",
        "binary_mode" => true
    ];

    // Opciones para incluir la Idempotencia
    $request_options = new \MercadoPago\Resources\RequestOptions();
    $request_options->setCustomHeaders([
        "X-Idempotency-Key" => "pay_" . $id_cuota . "_" . time() 
    ]);

    try {
        $payment = $client->create($payment_data, $request_options);
        $estado = $payment->status;
    } catch (\Exception $e) {
        error_log("Error detallado MP: " . $e->getMessage());
        // Aquí verás si es un problema de credenciales o de parámetros
    }


    error_log("MP status: " . $estado);

    /* -----------------------------------------
       Actualizar estado de cuota
    ------------------------------------------*/

    if ($estado === "approved") {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'pagado',
                fecha_pago = NOW(),
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    } elseif (in_array($estado, ["pending", "in_process"])) {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'procesando',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);

    } elseif (in_array($estado, ["rejected", "cancelled"])) {

        $pdo->prepare("
            UPDATE cuotas
            SET estado = 'fallido',
                transaccion_id = ?
            WHERE id_cuota = ?
        ")->execute([$payment->id, $id_cuota]);
    }

    /* -----------------------------------------
       Respuesta al frontend
    ------------------------------------------*/

    json_response([
        "status" => $estado,
        "status_detail" => $payment->status_detail ?? null,
        "payment_id" => $payment->id ?? null
    ]);

} catch (MPApiException $e) {

    $apiResponse = $e->getApiResponse();

    $error = $e->getMessage();

    if ($apiResponse) {

        $content = $apiResponse->getContent();

        if (is_array($content)) {

            $error =
                $content["message"] ??
                $content["error"] ??
                json_encode($content);
        }
    }

    error_log("MP ERROR: " . $error);

    json_response([
        "status" => "rejected",
        "message" => $error
    ]);

} catch (Throwable $e) {

    error_log("SERVER ERROR: " . $e->getMessage());

    json_response([
        "status" => "error",
        "message" => "Error interno del servidor"
    ], 500);
}

ob_end_flush();