<?php
// pages/reset_password_recinto.php
// Procesa el token y permite establecer nueva contraseña
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$exito = false;
$admin = null;

// Validar token
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_admin, nombre_completo, reset_token_expires 
            FROM admin_recintos 
            WHERE reset_token = ? AND reset_token_expires > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            $error = 'Enlace inválido o expirado. Solicita uno nuevo.';
        }
    } catch (PDOException $e) {
        error_log("❌ [RESET_VERIFY] Error: " . $e->getMessage());
        $error = 'Error en el sistema.';
    }
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    
    if (strlen($nueva) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Hash seguro de la nueva contraseña
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            
            // Actualizar contraseña y limpiar token
            $update = $pdo->prepare("
                UPDATE admin_recintos 
                SET contraseña = ?, reset_token = NULL, reset_token_expires = NULL 
                WHERE id_admin = ?
            ");
            $update->execute([$hash, $admin['id_admin']]);
            
            $exito = true;
            error_log("✅ [RESET] Contraseña actualizada para admin_id: " . $admin['id_admin']);
        } catch (PDOException $e) {
            error_log("❌ [RESET_UPDATE] Error: " . $e->getMessage());
            $error = 'Error al actualizar. Intenta más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nueva Contraseña - CanchaSport</title>
  <style>
    body {
      background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh; display: flex; justify-content: center; align-items: center; color: white;
    }
    .container { width: 95%; max-width: 420px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
      padding: 2rem; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .title { color: #FFD700; text-align: center; margin-bottom: 1rem; font-size: 1.4rem; }
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
    .password-requirements { font-size: 0.8rem; color: rgba(255,255,255,0.85); margin-top: 0.3rem; }
    @media (max-width: 480px) { .container { padding: 1.5rem; } }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="title">🔑 Nueva Contraseña</h1>
    
    <?php if ($error && !$exito): ?>
      <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
      <a href="recuperar_contraseña_recinto.php" class="btn btn-secondary">🔄 Solicitar nuevo enlace</a>
      
    <?php elseif ($exito): ?>
      <div class="alert alert-success">✅ ¡Contraseña actualizada correctamente!</div>
      <p style="text-align:center; margin-bottom:1.5rem;">Ya puedes iniciar sesión con tu nueva contraseña.</p>
      <a href="login_recintos.php" class="btn">🔐 Ir al login</a>
      
    <?php elseif (!$admin): ?>
      <div class="alert alert-error">❌ <?= $error ?: 'Token no válido.' ?></div>
      <a href="recuperar_contraseña_recinto.php" class="btn btn-secondary">🔄 Solicitar enlace de recuperación</a>
      
    <?php else: ?>
      <p style="text-align:center; margin-bottom:1.5rem;">Hola <strong><?= htmlspecialchars($admin['nombre_completo']) ?></strong>, establece tu nueva contraseña:</p>
      
      <form method="POST">
        <div class="form-group">
          <label for="nueva">Nueva Contraseña *</label>
          <input type="password" id="nueva" name="nueva" required minlength="6" autocomplete="new-password">
          <div class="password-requirements">Mínimo 6 caracteres</div>
        </div>
        <div class="form-group">
          <label for="confirmar">Confirmar Contraseña *</label>
          <input type="password" id="confirmar" name="confirmar" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn">💾 Guardar nueva contraseña</button>
      </form>
      <a href="login_recintos.php" class="btn btn-secondary" style="margin-top:1rem;">Cancelar</a>
    <?php endif; ?>
  </div>
</body>
</html>