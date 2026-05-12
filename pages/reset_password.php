<?php
// pages/reset_password.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Si ya hay un token en GET, lo pre-cargamos para evitar errores visuales si se recarga
if (!empty($token)) {
    // Validar existencia básica antes de mostrar el formulario (opcional, pero bueno para UX)
    $stmt_check = $pdo->prepare("SELECT id_socio FROM password_reset_tokens WHERE token = ? AND used = 0");
    $stmt_check->execute([$token]);
    if (!$stmt_check->fetch()) {
        $error = 'Token inválido o expirado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        $error = 'Todos los campos son requeridos';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($new_password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Validar token
        $stmt = $pdo->prepare("
            SELECT id_socio, expires_at 
            FROM password_reset_tokens 
            WHERE token = ? AND used = 0
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();
        
        if (!$token_data) {
            $error = 'Token inválido o ya utilizado';
        } elseif (strtotime($token_data['expires_at']) < time()) {
            $error = 'El token ha expirado. Por favor solicita uno nuevo.';
        } else {
            try {
                // Actualizar contraseña
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE socios SET password_hash = ? WHERE id_socio = ?");
                $stmt_update->execute([$password_hash, $token_data['id_socio']]);
                
                // Marcar token como usado
                $stmt_used = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                $stmt_used->execute([$token]);
                
                $success = '✅ Contraseña actualizada correctamente. Ahora puedes iniciar sesión.';
                
                // Opcional: Enviar correo de confirmación de cambio
                // require_once __DIR__ . '/../includes/reserva_mailer.php';
                // ... lógica de mail ...

            } catch (Exception $e) {
                error_log("Error reset password: " . $e->getMessage());
                $error = 'Error interno al actualizar la contraseña.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Restablecer Contraseña - CanchaSport</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-start: #CE93D8; 
            --primary-end: #AB47BC;
            --text-dark: #2D3748; 
            --text-light: #718096;
            --bg-light: #F7FAFC; 
            --card-glass: rgba(255,255,255,0.95);
            --shadow-soft: 0 4px 20px rgba(171,71,188,0.15);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: 
                linear-gradient(rgba(0,20,10,0.65), rgba(0,30,15,0.75)),
                url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            background-blend-mode: multiply;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            color: white;
        }
        .login-card {
            background: var(--card-glass);
            border-radius: 24px;
            padding: 2rem;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            color: var(--text-dark);
            position: relative;
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .login-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .login-subtitle {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 0.3rem;
        }
        
        /* Formularios */
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
            color: var(--text-dark);
        }
        .form-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 2px solid #E2E8F0;
            font-size: 1rem;
            transition: border-color 0.2s;
            font-family: 'Poppins', sans-serif;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-end);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.9rem;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
            color: white;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 0.5rem;
        }
        .btn-login:active { transform: scale(0.98); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }
        
        .error-msg {
            background: #FEE2E2;
            color: #991B1B;
            padding: 0.75rem;
            border-radius: 10px;
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 1rem;
            border-left: 4px solid #EF4444;
        }
        
        .success-msg {
            background: #ECFDF5;
            color: #065F46;
            padding: 0.75rem;
            border-radius: 10px;
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 1rem;
            border-left: 4px solid #10B981;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-end);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: opacity 0.2s;
        }
        .back-link:hover { opacity: 0.8; }

        @media (max-width: 480px) {
            .login-card { padding: 1.5rem; }
            .login-title { font-size: 1.2rem; }
        }
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
            <div class="login-logo">🔐</div>
            <h1 class="login-title">Contraseña actualizada</h1>
            <p class="login-subtitle">tu nueva contraseña está OK!</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
            <a href="../index.php" class="back-link">← Volver al inicio de sesión</a>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
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
                
                <button type="submit" class="btn-login">Actualizar Contraseña</button>
            </form>
            
            <a href="../index.php" class="back-link">← Cancelar y volver al inicio</a>
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