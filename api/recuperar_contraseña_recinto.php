<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    
    if (empty($correo)) {
        throw new Exception('Correo es requerido');
    }
    
    // Verificar si el correo existe en administradores de recintos
    $stmt = $pdo->prepare("
        SELECT id_admin FROM admin_recintos WHERE email = ?
    ");
    $stmt->execute([$correo]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // No revelar si el correo existe o no
        echo json_encode(['success' => true, 'message' => 'Si el correo está registrado, recibirás un código']);
        exit;
    }
    
    // Generar código de 4 dígitos
    $codigo = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO recuperacion_recintos (id_admin, codigo) 
        VALUES (?, ?)
    ");
    $stmt->execute([$admin['id_admin'], $codigo]);
    
    // Enviar email con Brevo
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($correo, 'Administrador Recinto');
    $mail->setSubject('🔐 Código de recuperación - Cancha Recintos');
    $mail->setHtmlBody("
        <h2>Recuperación de contraseña</h2>
        <p>Tu código de recuperación es: <strong>$codigo</strong></p>
        <p>Este código es válido por <strong>15 minutos</strong>.</p>
    ");
    $mail->send();
    
    echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>