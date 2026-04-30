<?php
// pages/completar_registro.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$exito = false;
$socio = null;

// Validar token
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM socios WHERE registro_token = ? AND registro_token_expires > NOW() AND registro_completado = 0 LIMIT 1");
    $stmt->execute([$token]);
    $socio = $stmt->fetch();
    
    if (!$socio) {
        $error = 'Enlace inválido o expirado. Solicita uno nuevo desde la app.';
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $socio) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $celular = trim($_POST['celular'] ?? $socio['celular'] ?? '');
    
    if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        // Hash de contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Actualizar socio
        $stmt = $pdo->prepare("UPDATE socios SET password = ?, celular = ?, registro_completado = 1, registro_token = NULL, registro_token_expires = NULL, updated_at = NOW() WHERE id_socio = ?");
        $stmt->execute([$password_hash, $celular, $socio['id_socio']]);
        
        $exito = true;
        error_log("✅ Socio {$socio['id_socio']} completó registro");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completa tu Perfil - CanchaSport</title>
    <style>
        body { background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { width: 95%; max-width: 420px; background: rgba(255,255,255,0.95); padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .header { text-align: center; margin-bottom: 1.5rem; }
        .header h1 { color: #AB47BC; margin: 0; font-size: 1.8rem; }
        .header p { color: #666; margin: 0.3rem 0 0 0; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
        .form-group input { width: 100%; padding: 0.9rem; border: 2px solid #E2E8F0; border-radius: 10px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #AB47BC; }
        .btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #CE93D8, #AB47BC); color: white; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; }
        .btn:hover { opacity: 0.95; }
        .alert { padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .info-box { background: #F3E5F5; border-left: 4px solid #AB47BC; padding: 1rem; border-radius: 8px; margin: 1rem 0; font-size: 0.9rem; color: #4A148C; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎾 CanchaSport</h1>
            <p>Completa tu perfil</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <a href="../index.php" style="display:block; text-align:center; color:#AB47BC; text-decoration:none;">← Volver al inicio</a>
        <?php elseif ($exito): ?>
            <div class="alert alert-success">✅ ¡Perfil completado! Ahora puedes iniciar sesión.</div>
            <a href="login_socios.php" class="btn" style="margin-top:1rem;">🔐 Iniciar sesión</a>
        <?php elseif ($socio): ?>
            <div class="info-box">
                <strong>👋 Hola, <?= htmlspecialchars($socio['nombre']) ?></strong><br>
                <small>Solo falta establecer tu contraseña</small>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?= htmlspecialchars($socio['email']) ?>" disabled style="background:#f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>Teléfono (opcional)</label>
                    <input type="tel" name="celular" placeholder="+56 9 1234 5678" value="<?= htmlspecialchars($socio['celular'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                
                <div class="form-group">
                    <label>Confirmar Contraseña *</label>
                    <input type="password" name="password_confirm" required minlength="6" placeholder="Repite tu contraseña">
                </div>
                
                <button type="submit" class="btn">✨ Completar registro</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error">❌ Enlace no válido</div>
            <p style="text-align:center; color:#666;">Solicita un nuevo enlace desde la app CanchaSport.</p>
            <a href="../index.php" style="display:block; text-align:center; color:#AB47BC; text-decoration:none; margin-top:1rem;">← Volver al inicio</a>
        <?php endif; ?>
    </div>
</body>
</html>