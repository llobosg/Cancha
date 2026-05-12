<?php
// pages/reset_password_admin.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$admin = null;

// Validar token de Admin
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
            $error = 'Enlace inválido o expirado.';
        }
    } catch (PDOException $e) {
        error_log("❌ [RESET_ADMIN] Error: " . $e->getMessage());
        $error = 'Error en el sistema.';
    }
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($new_password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update = $pdo->prepare("
                UPDATE admin_recintos 
                SET contraseña = ?, reset_token = NULL, reset_token_expires = NULL 
                WHERE id_admin = ?
            ");
            $update->execute([$hash, $admin['id_admin']]);
            
            $success = '✅ Contraseña actualizada correctamente.';
            error_log("✅ [RESET_ADMIN] Contraseña actualizada para admin_id: " . $admin['id_admin']);
        } catch (PDOException $e) {
            error_log("❌ [RESET_ADMIN_UPDATE] Error: " . $e->getMessage());
            $error = 'Error al actualizar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nueva Contraseña Admin - CanchaSport</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mismos estilos que reset_password.php */
        :root { --primary-start: #CE93D8; --primary-end: #AB47BC; --text-dark: #2D3748; --text-light: #718096; --card-glass: rgba(255,255,255,0.95); }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed; background-blend-mode: multiply; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 1rem; color: white; }
        .login-card { background: var(--card-glass); border-radius: 24px; padding: 2rem; max-width: 420px; width: 100%; box-shadow: 0 15px 40px rgba(0,0,0,0.25); color: var(--text-dark); }
        .login-header { text-align: center; margin-bottom: 1.5rem; }
        .login-logo { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .login-title { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.4rem; color: var(--text-dark); }
        .form-group input { width: 100%; padding: 0.85rem 1rem; border-radius: 12px; border: 2px solid #E2E8F0; font-size: 1rem; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: var(--primary-end); }
        .btn-login { width: 100%; padding: 0.9rem; border-radius: 14px; background: linear-gradient(135deg, var(--primary-start), var(--primary-end)); color: white; border: none; font-weight: 600; font-size: 1rem; cursor: pointer; transition: transform 0.2s; margin-top: 0.5rem; }
        .btn-login:active { transform: scale(0.98); }
        .error-msg { background: #FEE2E2; color: #991B1B; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; text-align: center; margin-bottom: 1rem; border-left: 4px solid #EF4444; }
        .success-msg { background: #ECFDF5; color: #065F46; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; text-align: center; margin-bottom: 1rem; border-left: 4px solid #10B981; }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: var(--primary-end); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        /* Wrapper para input de contraseña con icono */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 45px; /* Espacio para el icono SVG */
        }
        .toggle-password-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #718096; /* Color gris suave */
            transition: color 0.2s;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-icon:hover {
            color: #AB47BC; /* Color morado al hover */
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">🔑</div>
            <h1 class="login-title">Nueva Contraseña Admin</h1>
        </div>
        
        <?php if ($error && !$success): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <a href="recuperar_contraseña.php" class="back-link">← Solicitar nuevo enlace</a>
            
        <?php elseif ($success): ?>
            <div class="success-msg"><?= $success ?></div>
            <a href="login_recintos.php" class="back-link">← Ir al login de Admin</a>
            
        <?php elseif (!$admin): ?>
            <div class="error-msg">Enlace inválido o expirado.</div>
            <a href="recuperar_contraseña.php" class="back-link">← Solicitar nuevo enlace</a>
            
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="new_password">Nueva Contraseña *</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" required placeholder="Mínimo 6 caracteres">
                        <!-- Icono Toggle -->
                        <span class="toggle-password-icon" 
                            onclick="togglePasswordVisibility('new_password', this)"
                            data-eye='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
                            data-hide='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'>
                            <!-- Icono inicial: Ojo abierto -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repite la contraseña">
                        <!-- Icono Toggle -->
                        <span class="toggle-password-icon" 
                            onclick="togglePasswordVisibility('confirm_password', this)"
                            data-eye='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
                            data-hide='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'>
                            <!-- Icono inicial: Ojo abierto -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn-login">💾 Guardar nueva contraseña</button>
            </form>
            <a href="login_recintos.php" class="back-link">← Cancelar</a>
        <?php endif; ?>
    </div>
    <script>
    // Toggle visibilidad de contraseña con SVGs
    function togglePasswordVisibility(inputId, iconElement) {
        const input = document.getElementById(inputId);
        if (!input || !iconElement) return;
        
        // Obtener los SVGs guardados en los atributos data
        const eyeSvg = iconElement.getAttribute('data-eye');
        const hideSvg = iconElement.getAttribute('data-hide');
        
        if (input.type === 'password') {
            // Mostrar contraseña → Cambiar a tipo texto y poner icono "Ojo"
            input.type = 'text';
            iconElement.innerHTML = eyeSvg;
        } else {
            // Ocultar contraseña → Volver a password y poner icono "Tachado"
            input.type = 'password';
            iconElement.innerHTML = hideSvg;
        }
    }
    </script>
</body>
</html>