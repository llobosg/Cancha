<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Incluir la clase Brevo
require_once __DIR__ . '/../includes/BrevoMail.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    
    if (empty($email)) {
        throw new Exception('Email es requerido');
    }
    
    // Verificar que el socio exista
    $stmt = $pdo->prepare("
        SELECT s.id_socio, s.alias, c.nombre as club_nombre
        FROM socios s
        JOIN clubs c ON s.id_club = c.id_club
        WHERE s.email = ? AND s.password_hash IS NOT NULL
    ");
    $stmt->execute([$email]);
    $socio = $stmt->fetch();
    
    if (!$socio) {
        // No revelar si el email existe o no (seguridad)
        echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
        exit;
    }
    
    // Generar token de recuperación
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (id_socio, token, expires_at, used) 
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$socio['id_socio'], $token, $expires]);
    
    // URL de reseteo
    $reset_link = "https://cancha-web.up.railway.app/pages/reset_password.php?token=" . $token;
    
    // === ENVIAR CORREO CON BREVO ===
    try {
        $mail = new BrevoMail();
        $mail->setTo($email, $socio['alias']);
        $mail->setSubject('Recupera tu contraseña en Cancha');
        $mail->setHtmlContent("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #003366; color: white; padding: 20px; text-align: center;'>
                    <h2>⚽ Cancha</h2>
                    <p>Tu club deportivo, sin fricción</p>
                </div>
                <div style='padding: 20px; background: #f9f9f9;'>
                    <h3>Hola {$socio['alias']}</h3>
                    <p>Hemos recibido una solicitud para restablecer tu contraseña en el club <strong>{$socio['club_nombre']}</strong>.</p>
                    <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='{$reset_link}' 
                           style='background: #071289; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>
                            Restablecer Contraseña
                        </a>
                    </div>
                    <p style='font-size: 12px; color: #666;'>
                        Este enlace expirará en 1 hora. Si no solicitaste este cambio, ignora este correo.
                    </p>
                </div>
                <div style='padding: 15px; background: #eee; text-align: center; font-size: 12px; color: #666;'>
                    © " . date('Y') . " Cancha - Tu club deportivo, sin fricción
                </div>
            </div>
        ");
        
        $result = $mail->send();
        
        if (!$result) {
            error_log("Error al enviar correo Brevo: " . json_encode($mail->getLastError()));
            throw new Exception('Error al enviar el correo de recuperación');
        }
        
    } catch (Exception $e) {
        error_log("Excepción Brevo: " . $e->getMessage());
        // Continuar igual - no revelar errores internos
    }
    
    echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
    
} catch (Exception $e) {
    error_log("Error recuperar_password: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
?>