<?php
// pages/reset_password_admin.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = null;
$success = false;
$admin_id = null;

if (empty($token)) {
    $error = "Enlace inválido.";
} else {
    // 1. Validar Token en admin_recintos
    $stmt = $pdo->prepare("SELECT id_admin, reset_token_expires FROM admin_recintos WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $error = "Token no encontrado. Solicita uno nuevo.";
    } elseif (new DateTime() > new DateTime($admin['reset_token_expires'])) {
        $error = "El enlace ha expirado (válido por 1 hora).";
    } else {
        $admin_id = $admin['id_admin'];
        
        // 2. Procesar Cambio de Contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pass = $_POST['password'] ?? '';
            $pass_confirm = $_POST['password_confirm'] ?? '';
            
            if (strlen($pass) < 6) {
                $error = "La contraseña debe tener al menos 6 caracteres.";
            } elseif ($pass !== $pass_confirm) {
                $error = "Las contraseñas no coinciden.";
            } else {
                try {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    
                    // Actualizar contraseña y limpiar token
                    $stmt_upd = $pdo->prepare("UPDATE admin_recintos SET contraseña = ?, reset_token = NULL, reset_token_expires = NULL WHERE id_admin = ?");
                    $stmt_upd->execute([$hash, $admin_id]);
                    
                    $success = true;
                } catch (Exception $e) {
                    $error = "Error al guardar: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña - Admin</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { color: #071289; margin-bottom: 1rem; }
        input { width: 100%; padding: 0.8rem; margin: 0.5rem 0; border: 2px solid #eee; border-radius: 10px; box-sizing: border-box; }
        button { width: 100%; padding: 0.8rem; background: #071289; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #050d66; }
        .error { color: #C62828; font-size: 0.9rem; margin-top: 0.5rem; background: #FFEBEE; padding: 0.5rem; border-radius: 6px; }
        .success { color: #2E7D32; font-weight: bold; margin-bottom: 1rem; }
        a { color: #071289; text-decoration: none; font-size: 0.9rem; display: block; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <div style="font-size: 3rem;">🎉</div>
            <h2>¡Contraseña Actualizada!</h2>
            <p class="success">Ya puedes iniciar sesión con tu nueva clave.</p>
            <a href="../index.php">Ir al Login</a>
        <?php elseif ($error): ?>
            <div style="font-size: 3rem;">⚠️</div>
            <h2>Error</h2>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <a href="../index.php">Volver al Login</a>
        <?php else: ?>
            <div style="font-size: 3rem;">🔐</div>
            <h2>Nueva Contraseña</h2>
            <p style="color: #666; font-size: 0.9rem;">Define tu nueva clave de acceso.</p>
            
            <form method="POST">
                <!-- Campo Nueva Contraseña -->
                <div class="form-group" style="position: relative; margin-bottom: 1rem;">
                    <input type="password" name="password" id="pass1" placeholder="Nueva Contraseña" required 
                        style="width: 100%; padding: 0.8rem; padding-right: 40px; border: 2px solid #eee; border-radius: 10px; box-sizing: border-box;">
                    
                    <!-- Ojito -->
                    <button type="button" onclick="togglePassword('pass1', this)" 
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; font-size: 1.2rem; padding: 0;">
                        👁️
                    </button>
                </div>

                <!-- Campo Confirmar Contraseña -->
                <div class="form-group" style="position: relative; margin-bottom: 1rem;">
                    <input type="password" name="password_confirm" id="pass2" placeholder="Confirmar Contraseña" required 
                        style="width: 100%; padding: 0.8rem; padding-right: 40px; border: 2px solid #eee; border-radius: 10px; box-sizing: border-box;">
                    
                    <!-- Ojito -->
                    <button type="button" onclick="togglePassword('pass2', this)" 
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; font-size: 1.2rem; padding: 0;">
                        👁️
                    </button>
                </div>

                <button type="submit">Guardar Cambios</button>
            </form>

            <script>
            function togglePassword(inputId, btn) {
                const input = document.getElementById(inputId);
                if (input.type === "password") {
                    input.type = "text";
                    btn.textContent = "🙈"; // Icono de "ocultar"
                } else {
                    input.type = "password";
                    btn.textContent = "👁️"; // Icono de "ver"
                }
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>