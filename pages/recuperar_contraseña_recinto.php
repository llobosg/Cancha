<?php
// pages/recuperar_contraseña_recinto.php
// Flujo de recuperación de contraseña para admin de recinto
require_once __DIR__ . '/../includes/config.php';

$mensaje = '';
$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? ''); // email o usuario
    
    if (empty($identificador)) {
        $error = 'Ingresa tu email o usuario';
    } else {
        try {
            // Buscar admin por email O usuario
            $stmt = $pdo->prepare("
                SELECT id_admin, email, nombre_completo, id_recinto 
                FROM admin_recintos 
                WHERE email = ? OR usuario = ?
                LIMIT 1
            ");
            $stmt->execute([$identificador, $identificador]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // ✅ Generar token seguro
                $token = bin2hex(random_bytes(32)); // 64 caracteres hex
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Válido por 1 hora
                
                // Guardar token en BD
                $update = $pdo->prepare("
                    UPDATE admin_recintos 
                    SET reset_token = ?, reset_token_expires = ? 
                    WHERE id_admin = ?
                ");
                $update->execute([$token, $expires, $admin['id_admin']]);
                
                // ✅ Generar enlace de reset
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/reset_password_recinto.php?token=" . $token;
                
                // === ENVIAR EMAIL CON BREVO API (Reemplaza la función mail()) ===
                $brevo_key = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?? '';

                if (empty($brevo_key)) {
                    error_log("❌ [BREVO] API Key no configurada en Railway");
                    $exito = true; // Mantenemos mensaje genérico por seguridad
                } else {
                    $email_payload = [
                        "sender" => ["name" => "CanchaSport", "email" => "contacto@canchasport.com"],
                        "to" => [["email" => $admin['email'], "name" => $admin['nombre_completo']]],
                        "subject" => "🔐 Restablece tu contraseña - CanchaSport",
                        "htmlContent" => $email_body
                    ];

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => "https://api.brevo.com/v3/smtp/email",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($email_payload),
                        CURLOPT_HTTPHEADER => [
                            "accept: application/json",
                            "api-key: " . $brevo_key,
                            "content-type: application/json"
                        ]
                    ]);

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code >= 200 && $http_code < 300) {
                        $exito = true;
                        $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email. Revisa también spam.";
                        error_log("✅ [BREVO] Email enviado a: {$admin['email']} | Response: $response");
                    } else {
                        error_log("❌ [BREVO] Error $http_code: $response");
                        // Por seguridad, no revelamos si el email existe ni si falló el envío
                        $exito = true;
                        $mensaje = "✅ Si existe una cuenta asociada, recibirás un enlace en tu email.";
                    }
                }
        } catch (PDOException $e) {
            error_log("❌ [RESET] Error DB: " . $e->getMessage());
            $error = 'Error en el sistema. Intenta más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar Contraseña - CanchaSport</title>
  <style>
    body {
      background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh; display: flex; justify-content: center; align-items: center; color: white;
    }
    .container { width: 95%; max-width: 420px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
      padding: 2rem; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .title { color: #FFD700; text-align: center; margin-bottom: 1rem; font-size: 1.4rem; }
    .subtitle { text-align: center; margin-bottom: 1.5rem; font-size: 0.95rem; opacity: 0.95; }
    .alert { padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; }
    .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: white; }
    .form-group input { width: 100%; padding: 0.9rem; border: 2px solid rgba(255,255,255,0.3);
      border-radius: 8px; font-size: 1rem; background: rgba(255,255,255,0.95); color: #333; }
    .form-group input:focus { outline: none; border-color: #AB47BC; }
    .btn { width: 100%; padding: 1rem; background: #071289; color: white; border: none;
      border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
    .btn:hover { background: #050d6b; }
    .btn-secondary { background: transparent; border: 2px solid white; margin-top: 0.75rem; }
    .btn-secondary:hover { background: rgba(255,255,255,0.1); }
    .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #FFD700;
      text-decoration: none; font-size: 0.9rem; }
    .back-link:hover { text-decoration: underline; }
    @media (max-width: 480px) { .container { padding: 1.5rem; } }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="title">🔐 Recuperar Contraseña</h1>
    <p class="subtitle">Ingresa tu email o usuario de admin y te enviaremos un enlace para restablecer tu acceso.</p>
    
    <?php if ($error): ?>
      <div class="alert alert-error">❌ <?= $error ?></div>
    <?php elseif ($exito): ?>
      <div class="alert alert-success"><?= $mensaje ?></div>
      <a href="login_recintos.php" class="btn btn-secondary">← Volver al login</a>
    <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label for="identificador">Email o Usuario *</label>
          <input type="text" id="identificador" name="identificador" 
                 placeholder="ej: admin@club.com o tu_usuario" required autofocus>
        </div>
        <button type="submit" class="btn">📧 Enviar enlace de recuperación</button>
      </form>
      <a href="login_recintos.php" class="back-link">← Volver al login</a>
    <?php endif; ?>
  </div>
</body>
</html>