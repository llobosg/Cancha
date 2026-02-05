<?php
header('Content-Type: application/json; charset=utf-8');

// Evitar salida de errores HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Leer datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    
    if (empty($correo)) {
        throw new Exception('Correo es requerido');
    }
    
    // Simular conexión a base de datos (ajusta según tu config)
    require_once __DIR__ . '/../includes/config.php';
    
    // Verificar si el correo existe
    $stmt = $pdo->prepare("SELECT id_ceo FROM ceocancha WHERE correo = ?");
    $stmt->execute([$correo]);
    $ceo = $stmt->fetch();
    
    if (!$ceo) {
        // No revelar si el correo existe
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
    
    // Enviar email con Brevo (HTTP directo)
    $apiKey = $_ENV['BREVO_API_KEY'] ?? '';
    
    if (!empty($apiKey)) {
        $data = json_encode([
            'sender' => [
                'email' => ($_ENV['BREVO_SENDER_EMAIL'] ?? 'no-reply@cancha-sport.cl'),
                'name' => ($_ENV['BREVO_SENDER_NAME'] ?? 'Cancha CEO')
            ],
            'to' => [['email' => $correo]],
            'subject' => 'Código de recuperación - Cancha CEO',
            'htmlContent' => "<h2>Recuperación de contraseña</h2><p>Tu código de recuperación es: <strong>$codigo</strong></p><p>Válido por 15 minutos.</p>"
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.brevo.com/v3/smtp/email',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Registrar en logs si hay error
        if ($httpCode !== 201) {
            error_log("Error Brevo HTTP $httpCode: " . $response);
        }
    } else {
        // Modo desarrollo: mostrar código en logs
        error_log("MODO DESARROLLO - Código de recuperación para $correo: $codigo");
    }
    
    echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    
} catch (Exception $e) {
    error_log("Error en recuperar_contraseña.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>