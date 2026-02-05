<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Incluir SDK de Brevo
require_once __DIR__ . '/../vendor/autoload.php'; // Si usas Composer

use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    
    if (empty($correo)) {
        throw new Exception('Correo es requerido');
    }
    
    // Verificar si el correo existe
    $stmt = $pdo->prepare("SELECT id_ceo FROM ceocancha WHERE correo = ?");
    $stmt->execute([$correo]);
    $ceo = $stmt->fetch();
    
    if (!$ceo) {
        // No revelar si el correo existe o no (seguridad)
        echo json_encode(['success' => true, 'message' => 'Si el correo está registrado, recibirás un código']);
        exit;
    }
    
    // Generar código de 4 dígitos
    $codigo = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO ceo_recuperacion (id_ceo, codigo) 
        VALUES (?, ?)
    ");
    $stmt->execute([$ceo['id_ceo'], $codigo]);
    
    // Enviar email con Brevo
    try {
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $_ENV['BREVO_API_KEY']);
        $apiInstance = new TransactionalEmailsApi(null, $config);
        
        $sendSmtpEmail = new SendSmtpEmail([
            'sender' => [
                'email' => $_ENV['BREVO_SENDER_EMAIL'],
                'name' => $_ENV['BREVO_SENDER_NAME']
            ],
            'to' => [[
                'email' => $correo
            ]],
            'subject' => 'Código de recuperación - Cancha CEO',
            'htmlContent' => "
                <h2>Recuperación de contraseña</h2>
                <p>Tu código de recuperación es: <strong>$codigo</strong></p>
                <p>Este código es válido por <strong>15 minutos</strong>.</p>
                <p>Si no solicitaste este código, ignora este mensaje.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>
                    Equipo Cancha CEO<br>
                    cancha-sport.cl
                </p>
            "
        ]);
        
        $apiInstance->sendTransacEmail($sendSmtpEmail);
        
    } catch (Exception $e) {
        error_log("Error Brevo: " . $e->getMessage());
        // Continuar aunque falle el email (el código está guardado)
    }
    
    echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>