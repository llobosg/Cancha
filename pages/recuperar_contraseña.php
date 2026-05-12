<?php
// pages/recuperar_contraseña.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // Asegurar que BrevoMailer esté disponible

$mensaje = '';
$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    
    if (empty($identificador)) {
        $error = 'Ingresa tu email, usuario o alias';
    } else {
        try {
            $usuario_encontrado = false;
            $tipo_usuario = ''; // 'socio' o 'admin'
            $nombre_destinatario = '';
            $email_destino = '';
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // 1. BUSCAR EN SOCIOS (Email o Alias)
            $stmt_socio = $pdo->prepare("SELECT id_socio, email, nombre FROM socios WHERE email = ? OR alias = ? LIMIT 1");
            $stmt_socio->execute([$identificador, $identificador]);
            $user = $stmt_socio->fetch();
            
            if ($user) {
                $usuario_encontrado = true;
                $tipo_usuario = 'socio';
                $nombre_destinatario = $user['nombre'];
                $email_destino = $user['email'];
                
                // Guardar token en tabla password_reset_tokens
                // Usamos INSERT ... ON DUPLICATE KEY UPDATE por si ya había un token pendiente
                $stmt_token = $pdo->prepare("
                    INSERT INTO password_reset_tokens (id_socio, token, expires_at, used) 
                    VALUES (?, ?, ?, 0) 
                    ON DUPLICATE KEY UPDATE token=?, expires_at=?
                ");
                $stmt_token->execute([$user['id_socio'], $token, $expires, $token, $expires]);
                
                // Link de recuperación para SOCIO
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password.php?token=" . $token;
            } 
            
            // 2. SI NO ES SOCIO, BUSCAR EN ADMIN_RECINTOS (Email o Usuario)
            if (!$usuario_encontrado) {
                $stmt_admin = $pdo->prepare("SELECT id_admin, email, nombre_completo FROM admin_recintos WHERE email = ? OR usuario = ? LIMIT 1");
                $stmt_admin->execute([$identificador, $identificador]);
                $admin = $stmt_admin->fetch();
                
                if ($admin) {
                    $usuario_encontrado = true;
                    $tipo_usuario = 'admin';
                    $nombre_destinatario = $admin['nombre_completo'];
                    $email_destino = $admin['email'];
                    
                    // Actualizar token en tabla admin_recintos
                    $update = $pdo->prepare("UPDATE admin_recintos SET reset_token = ?, reset_token_expires = ? WHERE id_admin = ?");
                    $update->execute([$token, $expires, $admin['id_admin']]);
                    
                    // Link de recuperación para ADMIN
                    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password_admin.php?token=" . $token;
                }
            }

            // 3. ENVIAR CORREO SI SE ENCONTRÓ ALGUIEN
            if ($usuario_encontrado) {
                // Plantilla HTML del correo
                $titulo_email = ($tipo_usuario === 'admin') ? '🔐 Recuperación Admin - CanchaSport' : '🔐 Restablece tu contraseña - CanchaSport';
                $cuerpo_html = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                    <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                        <h2 style='margin:0;'>🔐 Recuperación de Contraseña</h2>
                    </div>
                    <p style='font-size:1.1rem;'>Hola <strong>" . htmlspecialchars($nombre_destinatario) . "</strong>,</p>
                    <p>Recibimos una solicitud para restablecer tu contraseña de CanchaSport.</p>
                    
                    <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #AB47BC;margin:20px 0;text-align:center;'>
                        <a href='" . $reset_link . "' 
                           style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold;font-size:1rem;'>
                            🔗 Restablecer mi contraseña
                        </a>
                    </div>
                    
                    <p style='font-size:0.9rem;color:#666;'><strong>⏰ Este enlace expira en 1 hora</strong> por seguridad.</p>
                    <p style='font-size:0.9rem;color:#666;'>Si no solicitaste este cambio, ignora este mensaje.</p>
                    
                    <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
                    <p style='text-align:center;font-size:0.9rem;color:#888;'>
                        ¿Necesitas ayuda? <a href='mailto:contacto@canchasport.com' style='color:#AB47BC;'>contacto@canchasport.com</a>
                    </p>
                </div>";

                // Enviar usando BrevoMailer
                $mail = new BrevoMailer();
                $sent = $mail
                    ->setTo($email_destino, $nombre_destinatario)
                    ->setSubject($titulo_email)
                    ->setReplyTo('contacto@canchasport.com', 'Soporte CanchaSport')
                    ->setHtmlBody($cuerpo_html)
                    ->send();
                
                // Por seguridad, siempre decimos que se envió si la cuenta existe, 
                // pero si falla el mail, logueamos el error sin mostrarlo al usuario
                if (!$sent) {
                    error_log("⚠️ [RESET] Fallo al enviar email a: {$email_destino}");
                } else {
                    error_log("✅ [RESET] Email enviado a: {$email_destino} (Tipo: {$tipo_usuario})");
                }
                
                $exito = true;
                $mensaje = "✅ Si existe una cuenta asociada a ese identificador, recibirás un enlace en tu email. Revisa también la carpeta de Spam.";
            } else {
                // Usuario no encontrado en ninguna tabla
                // Mensaje genérico por seguridad (no revelar si existe o no la cuenta)
                $exito = true;
                $mensaje = "✅ Si existe una cuenta asociada a ese identificador, recibirás un enlace en tu email.";
                error_log("ℹ️ [RESET] Intento con identificador no encontrado: " . substr($identificador, 0, 10) . "...");
            }

        } catch (Exception $e) {
            error_log("❌ Error en recuperación contraseña: " . $e->getMessage());
            $error = "Ocurrió un error interno. Por favor intenta más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recuperar Contraseña - CanchaSport</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-start: #CE93D8; --primary-end: #AB47BC;
            --text-dark: #2D3748; --text-light: #718096;
            --bg-light: #F7FAFC; --card-glass: rgba(255,255,255,0.95);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            background-blend-mode: multiply;
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 1rem; color: white;
        }
        .login-card {
            background: var(--card-glass);
            border-radius: 24px;
            padding: 2rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            color: var(--text-dark);
        }
        .login-header { text-align: center; margin-bottom: 1.5rem; }
        .login-logo { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .login-title { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); }
        .login-subtitle { font-size: 0.9rem; color: var(--text-light); margin-top: 0.3rem; }
        
        .form-group label { display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.4rem; color: var(--text-dark); }
        .form-group input {
            width: 100%; padding: 0.85rem 1rem; border-radius: 12px; border: 2px solid #E2E8F0;
            font-size: 1rem; transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: var(--primary-end); }
        
        .btn-login {
            width: 100%; padding: 0.9rem; border-radius: 14px;
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
            color: white; border: none; font-weight: 600; font-size: 1rem; cursor: pointer;
            transition: transform 0.2s; margin-top: 0.5rem;
        }
        .btn-login:active { transform: scale(0.98); }
        
        .error-msg { background: #FEE2E2; color: #991B1B; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; text-align: center; margin-bottom: 1rem; border-left: 4px solid #EF4444; }
        .success-msg { background: #ECFDF5; color: #065F46; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; text-align: center; margin-bottom: 1rem; border-left: 4px solid #10B981; }
        
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: var(--primary-end); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .back-link:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">🔐</div>
            <h1 class="login-title">Recuperar Acceso</h1>
            <p class="login-subtitle">Ingresa tu email, usuario o alias</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($exito): ?>
            <div class="success-msg"><?= $mensaje ?></div>
            <a href="../index.php" class="back-link">← Volver al inicio</a>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="identificador">Email, Usuario o Alias *</label>
                    <input type="text" id="identificador" name="identificador" required placeholder="ej: juan@email.com o admin_club">
                </div>
                <button type="submit" class="btn-login">📧 Enviar enlace de recuperación</button>
            </form>
            <a href="../index.php" class="back-link">← Cancelar y volver al inicio</a>
        <?php endif; ?>
    </div>
</body>
</html>