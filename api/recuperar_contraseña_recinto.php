<?php
// api/recuperar_contraseña_recinto.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $correo = $input['correo'] ?? '';
    
    if (empty($correo)) {
        throw new Exception('Correo es requerido');
    }
    
    // 1. Buscar admin
    $stmt = $pdo->prepare("SELECT id_admin, nombre_completo FROM admin_recintos WHERE email = ?");
    $stmt->execute([$correo]);
    $admin = $stmt->fetch();
    
    // Seguridad: No revelar si existe o no
    $msg_exito = ['success' => true, 'message' => 'Si el correo está registrado, recibirás las instrucciones'];
    
    if (!$admin) {
        echo json_encode($msg_exito);
        exit;
    }
    
    // 2. Generar Token Seguro
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Válido 1 hora
    
    // 3. Guardar en BD (usando tus columnas reset_token y reset_token_expires)
    $stmt = $pdo->prepare("UPDATE admin_recintos SET reset_token = ?, reset_token_expires = ? WHERE id_admin = ?");
    $stmt->execute([$token, $expires, $admin['id_admin']]);
    
    // 4. Enviar Email con LINK
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    $mail = new BrevoMailer();
    $mail->setTo($correo, $admin['nombre_completo']);
    $mail->setSubject('🔐 Recuperar Contraseña - CanchaSport Recintos');
    
    // IMPORTANTE: Ajusta esta URL a tu dominio real
    $link_recuperacion = "https://canchasport.com/pages/reset_password_admin.php?token=" . $token;
    
    $mail->setHtmlBody("
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #071289;'>Hola {$admin['nombre_completo']}</h2>
            <p>Has solicitado recuperar tu contraseña de acceso al recinto.</p>
            <p>Haz clic en el siguiente enlace para crear una nueva contraseña (válido por 1 hora):</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$link_recuperacion}' 
                   style='background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                    🔐 Crear Nueva Contraseña
                </a>
            </div>
            
            <p style='font-size: 0.9rem; color: #666;'>Si no solicitaste esto, ignora este correo.</p>
        </div>
    ");
    $mail->send();
    
    echo json_encode($msg_exito);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>