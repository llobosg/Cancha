<?php
// pages/recuperar_contraseña_recinto.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';

$mensaje = '';
$error = '';
$exito = false;

// Verificación de columnas para recovery (Hotfix producción)
$columns_missing = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM admin_recintos LIKE 'reset_token'")->fetch();
    if (!$check) $columns_missing = true;
} catch (Exception $e) {
    $columns_missing = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$columns_missing) {
    $identificador = trim($_POST['identificador'] ?? '');
    
    if (empty($identificador)) {
        $error = 'Ingresa tu email o usuario';
    } else {
        try {
            // 1. Buscar admin por email o usuario
            $stmt = $pdo->prepare("
                SELECT id_admin, email, nombre_completo, id_recinto 
                FROM admin_recintos 
                WHERE email = ? OR usuario = ? 
                LIMIT 1
            ");
            $stmt->execute([$identificador, $identificador]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // 2. Generar token seguro
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $update = $pdo->prepare("UPDATE admin_recintos SET reset_token = ?, reset_token_expires = ? WHERE id_admin = ?");
                $update->execute([$token, $expires, $admin['id_admin']]);
                
                // 3. Generar enlace de reset
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password_recinto.php?token=" . $token;
                
                // 4. Cuerpo del email HTML
                $email_body = "
                <html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                  <div style='max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 12px;'>
                    <div style='text-align: center; padding: 15px; background: linear-gradient(135deg, #CE93D8, #AB47BC); border-radius: 8px 8px 0 0;'>
                      <h2 style='color: white; margin: 0;'>🔐 CanchaSport</h2>
                    </div>
                    <div style='padding: 25px; background: white; border-radius: 0 0 8px 8px;'>
                      <p>Hola <strong>" . htmlspecialchars($admin['nombre_completo']) . "</strong>,</p>
                      <p>Recibimos una solicitud para restablecer tu contraseña de administrador.</p>
                      <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $reset_link . "' 
                           style='background: #AB47BC; color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600;'>
                          🔗 Restablecer mi contraseña
                        </a>
                      </div>
                      <p style='font-size: 0.9rem; color: #666;'><strong>⏰ Este enlace expira en 1 hora</strong> por seguridad.</p>
                      <p style='font-size: 0.9rem; color: #666;'>Si no solicitaste este cambio, ignora este mensaje. Tu contraseña permanecerá igual.</p>
                      <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                      <p style='font-size: 0.85rem; color: #999; text-align: center;'>Equipo CanchaSport 🏟️</p>
                    </div>
                  </div>
                </body></html>";
                
                // 5. Enviar email usando BrevoMailer (tu clase existente)
                $mailer = new BrevoMailer();
                $sent = $mailer
                    ->setTo($admin['email'], $admin['nombre_completo'])
                    ->setSubject('🔐 Restablece tu contraseña - CanchaSport')
                    ->setHtmlBody($email_body)
                    ->setReplyTo('contacto@canchasport.com', 'Soporte CanchaSport')
                    ->send();
                
                if ($sent) {
                    $exito = true;
                    $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email. Revisa también la carpeta de spam.";
                    error_log("✅ [RESET] Email enviado vía BrevoMailer a: {$admin['email']}");
                } else {
                    // Por seguridad, no revelamos si falló el envío
                    $exito = true;
                    $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email.";
                    error_log("⚠️ [RESET] BrevoMailer retornó false para: {$admin['email']}");
                }
            } else {
                // Usuario no existe → mensaje genérico por seguridad (previene enumeración)
                $exito = true;
                $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email.";
                error_log("ℹ️ [RESET] Intento con identificador no encontrado: " . substr($identificador, 0, 10) . "...");
            }
        } catch (PDOException $e) {
            error_log("❌ [RESET] Error DB: " . $e->getMessage());
            $error = 'Error en el sistema. Intenta más tarde.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $columns_missing) {
    $error = 'Función en mantenimiento. Contacta al administrador para restablecer tu acceso.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar Contraseña - CanchaSport</title>
  <style>
    body { background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; color: white; }
    .container { width: 95%; max-width: 420px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 2rem; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .title { color: #FFD700; text-align: center; margin-bottom: 1rem; font-size: 1.4rem; }
    .subtitle { text-align: center; margin-bottom: 1.5rem; font-size: 0.95rem; opacity: 0.95; }
    .alert { padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; }
    .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: white; }
    .form-group input { width: 100%; padding: 0.9rem; border: 2px solid rgba(255,255,255,0.3); border-radius: 8px; font-size: 1rem; background: rgba(255,255,255,0.95); color: #333; }
    .form-group input:focus { outline: none; border-color: #AB47BC; }
    .btn { width: 100%; padding: 1rem; background: #071289; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
    .btn:hover { background: #050d6b; }
    .btn-secondary { background: transparent; border: 2px solid white; margin-top: 0.75rem; }
    .btn-secondary:hover { background: rgba(255,255,255,0.1); }
    .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #FFD700; text-decoration: none; font-size: 0.9rem; }
    .back-link:hover { text-decoration: underline; }
    @media (max-width: 480px) { .container { padding: 1.5rem; } }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="title">🔐 Recuperar Contraseña</h1>
    <p class="subtitle">Ingresa tu email o usuario de admin y te enviaremos un enlace seguro.</p>
    
    <?php if ($error): ?>
      <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php elseif ($exito): ?>
      <div class="alert alert-success"><?= $mensaje ?></div>
      <a href="login_recintos.php" class="btn btn-secondary">← Volver al login</a>
    <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label for="identificador">Email o Usuario *</label>
          <input type="text" id="identificador" name="identificador" placeholder="ej: admin@club.com o tu_usuario" required autofocus>
        </div>
        <button type="submit" class="btn">📧 Enviar enlace de recuperación</button>
      </form>
      <a href="login_recintos.php" class="back-link">← Volver al login</a>
    <?php endif; ?>
  </div>
</body>
</html>