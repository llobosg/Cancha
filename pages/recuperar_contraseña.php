<?php
// pages/recuperar_contraseña.php
require_once __DIR__ . '/../includes/config.php';

$mensaje = '';
$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    
    if (empty($identificador)) {
        $error = 'Ingresa tu email, usuario o alias';
    } else {
        // Llamamos a la API interna para procesar
        // Nota: En producción, esto podría ser un fetch AJAX, pero aquí lo hacemos directo para simplicidad
        require_once __DIR__ . '/../api/solicitar_reset_password.php';
        
        // La API imprime JSON, así que capturamos la salida si queremos mostrar mensaje en esta misma página
        // O mejor aún, redirigimos a una página de "Enviado" o mostramos el mensaje directamente aquí.
        // Para mantenerlo simple, vamos a ejecutar la lógica aquí mismo copiando la esencia de la API.
        
        try {
            // 1. Buscar en SOCIOS
            $stmt_socio = $pdo->prepare("SELECT id_socio, email, nombre FROM socios WHERE email = ? OR alias = ? LIMIT 1");
            $stmt_socio->execute([$identificador, $identificador]);
            $user = $stmt_socio->fetch();
            
            if ($user) {
                // Generar token para socio
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar en tabla password_reset_tokens (asumiendo que existe)
                $stmt_token = $pdo->prepare("INSERT INTO password_reset_tokens (id_socio, token, expires_at, used) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE token=?, expires_at=?");
                $stmt_token->execute([$user['id_socio'], $token, $expires, $token, $expires]);
                
                // Enviar correo
                require_once __DIR__ . '/../includes/reserva_mailer.php';
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password.php?token=" . $token;
                
                $email_body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                    <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                        <h2 style='margin:0;'>🔐 Recuperación de Contraseña</h2>
                    </div>
                    <p style='font-size:1.1rem;'>Hola <strong>" . htmlspecialchars($user['nombre']) . "</strong>,</p>
                    <p>Recibimos una solicitud para restablecer tu contraseña de CanchaSport.</p>
                    
                    <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #AB47BC;margin:20px 0;text-align:center;'>
                        <a href='" . $reset_link . "' 
                           style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold;font-size:1rem;'>
                            🔗 Restablecer mi contraseña
                        </a>
                    </div>
                    
                    <p style='font-size:0.9rem;color:#666;'><strong>⏰ Este enlace expira en 1 hora</strong>.</p>
                    <p style='font-size:0.9rem;color:#666;'>Si no solicitaste este cambio, ignora este mensaje.</p>
                </div>";
                
                $mail = new BrevoMailer();
                $mail->setTo($user['email'], $user['nombre'])
                     ->setSubject('🔐 Restablece tu contraseña - CanchaSport')
                     ->setHtmlBody($email_body)
                     ->send();
                     
                $exito = true;
                $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email.";
            } else {
                // 2. Buscar en ADMIN_RECINTOS
                $stmt_admin = $pdo->prepare("SELECT id_admin, email, nombre_completo FROM admin_recintos WHERE email = ? OR usuario = ? LIMIT 1");
                $stmt_admin->execute([$identificador, $identificador]);
                $admin = $stmt_admin->fetch();
                
                if ($admin) {
                    // Generar token para admin
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Actualizar tabla admin_recintos (asumiendo columnas reset_token y reset_token_expires)
                    $update = $pdo->prepare("UPDATE admin_recintos SET reset_token = ?, reset_token_expires = ? WHERE id_admin = ?");
                    $update->execute([$token, $expires, $admin['id_admin']]);
                    
                    // Enviar correo
                    require_once __DIR__ . '/../includes/reserva_mailer.php';
                    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password_admin.php?token=" . $token;
                    
                    $email_body = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                        <div style='text-align:center;background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                            <h2 style='margin:0;'>🔐 Recuperación Admin</h2>
                        </div>
                        <p style='font-size:1.1rem;'>Hola <strong>" . htmlspecialchars($admin['nombre_completo']) . "</strong>,</p>
                        <p>Recibimos una solicitud para restablecer tu contraseña de administrador.</p>
                        
                        <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #AB47BC;margin:20px 0;text-align:center;'>
                            <a href='" . $reset_link . "' 
                               style='background:#AB47BC;color:white;padding:14px 32px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold;font-size:1rem;'>
                                🔗 Restablecer mi contraseña
                            </a>
                        </div>
                        
                        <p style='font-size:0.9rem;color:#666;'><strong>⏰ Este enlace expira en 1 hora</strong>.</p>
                    </div>";
                    
                    $mail = new BrevoMailer();
                    $mail->setTo($admin['email'], $admin['nombre_completo'])
                         ->setSubject('🔐 Restablece tu contraseña Admin - CanchaSport')
                         ->setHtmlBody($email_body)
                         ->send();
                         
                    $exito = true;
                    $mensaje = "✅ Si existe una cuenta de admin asociada, recibirás un enlace en tu email.";
                } else {
                    // No encontrado en ninguna tabla, pero por seguridad decimos que se envió
                    $exito = true;
                    $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email.";
                }
            }
        } catch (Exception $e) {
            error_log("Error reset password: " . $e->getMessage());
            $error = "Ocurrió un error interno. Intenta más tarde.";
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