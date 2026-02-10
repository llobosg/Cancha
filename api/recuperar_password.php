<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// LOGGING DETALLADO
error_log("=== INICIO RECUPERAR_PASSWORD ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("RAW INPUT: " . file_get_contents('php://input'));

try {
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("JSON DECODED: " . print_r($input, true));
    
    $email = trim($input['email'] ?? '');
    error_log("EMAIL EXTRAÍDO: '$email'");
    
    if (empty($email)) {
        error_log("ERROR: Email vacío");
        throw new Exception('Email es requerido');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("ERROR: Email inválido");
        throw new Exception('Correo electrónico inválido');
    }
    
    // Verificar que el socio exista
    error_log("BUSCANDO SOCIO CON EMAIL: $email");
    $stmt = $pdo->prepare("
        SELECT s.id_socio, s.alias, c.nombre as club_nombre
        FROM socios s
        JOIN clubs c ON s.id_club = c.id_club
        WHERE s.email = ? AND s.password_hash IS NOT NULL
    ");
    $stmt->execute([$email]);
    $socio = $stmt->fetch();
    error_log("RESULTADO SOCIO: " . print_r($socio, true));
    
    if (!$socio) {
        error_log("SOCIO NO ENCONTRADO - RESPUESTA GENÉRICA");
        echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
        exit;
    }
    
    // Generar token de recuperación
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    error_log("TOKEN GENERADO: $token");
    error_log("EXPIRA: $expires");
    
    // Guardar en base de datos
    error_log("INSERTANDO TOKEN EN BASE DE DATOS");
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (id_socio, token, expires_at, used) 
        VALUES (?, ?, ?, 0)
    ");
    $result = $stmt->execute([$socio['id_socio'], $token, $expires]);
    error_log("RESULTADO INSERT: " . ($result ? 'SUCCESS' : 'FAILED'));
    
    // === ENVIAR CORREO CON BREVO - MISMO MÉTODO QUE enviar_codigo_socio.php ===
    error_log("INICIANDO ENVÍO DE CORREO A: " . $email);
    require_once __DIR__ . '/../includes/brevo_mailer.php';
    
    try {
        error_log("CREANDO INSTANCIA BrevoMailer");
        $mail = new BrevoMailer();
        error_log("INSTANCIA CREADA EXITOSAMENTE");
        
        error_log("CONFIGURANDO DESTINATARIO: " . $email . " - " . $socio['alias']);
        $mail->setTo($email, $socio['alias']);
        error_log("DESTINATARIO CONFIGURADO");
        
        $mail->setSubject('Recupera tu contraseña en Cancha');
        error_log("ASUNTO CONFIGURADO");
        
        $reset_link = "https://cancha-web.up.railway.app/pages/reset_password.php?token=" . $token;
        error_log("RESET LINK: " . $reset_link);
        
        $mail->setHtmlBody("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #003366; color: white; padding: 20px; text-align: center;'>
                    <h2>⚽ Cancha</h2>
                    <p>Tu club deportivo, sin fricción</p>
                </div>
                <div style='padding: 20; background: #f9f9f9;'>
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
        error_log("HTML BODY CONFIGURADO");
        
        error_log("ENVIANDO CORREO...");
        $send_result = $mail->send();
        error_log("RESULTADO ENVÍO: " . ($send_result ? 'SUCCESS' : 'FAILED'));
        
        if (!$send_result) {
            error_log("ERROR AL ENVIAR CORREO - OBTENIENDO ERROR DETALLADO");
            $last_error = method_exists($mail, 'getLastError') ? $mail->getLastError() : 'Método getLastError no disponible';
            error_log("DETALLE ERROR: " . print_r($last_error, true));
            error_log("CORREO FALLIDO PARA: " . $email);
        } else {
            error_log("✅ CORREO ENVIADO EXITOSAMENTE");
        }
        
    } catch (Exception $mailer_exception) {
        error_log("EXCEPCIÓN EN MAILER: " . $mailer_exception->getMessage());
        error_log("TRACE: " . $mailer_exception->getTraceAsString());
    }
    
    error_log("RESPUESTA FINAL: Éxito");
    echo json_encode(['success' => true, 'message' => 'Si el email está registrado, recibirás un enlace de recuperación']);
    
} catch (Exception $e) {
    error_log("EXCEPCIÓN PRINCIPAL: " . $e->getMessage());
    error_log("TRACE: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

error_log("=== FIN RECUPERAR_PASSWORD ===");
?>