<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';
session_start();

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

try {
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Usuario no autenticado');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id_cuota = (int)($data['id_cuota'] ?? 0);
    $monto = (float)($data['monto'] ?? 0);
    $descripcion = trim($data['descripcion'] ?? '');

    if ($id_cuota <= 0 || $monto <= 0 || empty($descripcion)) {
        throw new Exception('Datos incompletos');
    }

    // Verificar que la cuota pertenece al socio
    $stmt = $pdo->prepare("
        SELECT id_cuota, monto, estado 
        FROM cuotas 
        WHERE id_cuota = ? AND id_socio = ?
    ");
    $stmt->execute([$id_cuota, $_SESSION['id_socio']]);
    $cuota = $stmt->fetch();
    if (!$cuota || $cuota['estado'] !== 'pendiente') {
        throw new Exception('Cuota no válida o ya pagada');
    }

    // Configurar SDK
    MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

    // Crear preferencia
    $client = new PreferenceClient();
    $preference = $client->create([
        "items" => [
            [
                "title" => $descripcion,
                "quantity" => 1,
                "unit_price" => $monto,
                "currency_id" => "CLP"
            ]
        ],
        "payer" => [
            "email" => $_SESSION['user_email'] ?? ''
        ],
        "back_urls" => [
            "success" => "https://canchasport.com/pago_exitoso.php?id_cuota=" . $id_cuota,
            "failure" => "https://canchasport.com/pago_fallido.php",
            "pending" => "https://canchasport.com/pago_pendiente.php"
        ],
        "auto_return" => "approved",
        "notification_url" => "https://canchasport.com/api/webhook_mercadopago.php",
        "external_reference" => "cuota_" . $id_cuota
    ]);

    echo json_encode(['init_point' => $preference->init_point]);

} catch (Exception $e) {
    error_log("Error crear_pago: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar el pago']);
}
?>