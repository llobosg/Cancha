<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    
    if (empty($correo)) {
        throw new Exception('Correo es requerido');
    }
    
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
    
    // Enviar correo usando tu clase BrevoMailer
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($correo, 'CEO Cancha');
    $mail->setSubject('🔐 Código de recuperación - Cancha CEO');
    $mail->setHtmlBody("
        <h2>Recuperación de contraseña</h2>
        <p>Tu código de recuperación es:</p>
        <h1 style='color:#009966;'>$codigo</h1>
        <p>Ingresa este código para restablecer tu contraseña.</p>
        <p>El código es válido por <strong>15 minutos</strong>.</p>
    ");
    
    if (!$mail->send()) {
        error_log("Error al enviar correo de recuperación a: $correo");
        throw new Exception('Error al enviar el código por correo');
    }
    
    echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    
} catch (Exception $e) {
    error_log("Error en recuperar_contraseña.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar el código']);
}
?>