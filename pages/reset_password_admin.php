<?php
// pages/reset_password_admin.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = null;
$success = false;
$admin_id = null;

if (empty($token)) {
    $error = "Enlace inválido o no proporcionado.";
} else {
    // 1. Validar Token en admin_recintos
    $stmt = $pdo->prepare("SELECT id_admin, reset_token_expires FROM admin_recintos WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $error = "Token no encontrado o ya fue utilizado.";
    } elseif (new DateTime() > new DateTime($admin['reset_token_expires'])) {
        $error = "El enlace ha expirado (válido por 1 hora). Solicita uno nuevo.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Admin Recinto</title>
    <style>
        :root {
            --primary: #071289;
            --secondary: #AB47BC;
        }
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            /* Imagen corporativa de fondo */
            background: linear-gradient(rgba(7, 18, 137, 0.7), rgba(171, 71, 188, 0.6)), url('../assets/img/cancha_pasto2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-title {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.5px;
        }
        .subtitle {
            color: #555;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.4;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .form-group {
            margin-bottom: 1.2rem;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }
        input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        input:focus {
            border-color: var(--secondary);
            outline: none;
        }
        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(7, 18, 137, 0.3);
        }
        .error {
            color: #C62828;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            background: #FFEBEE;
            padding: 0.8rem;
            border-radius: 8px;
            border-left: 4px solid #C62828;
        }
        .success {
            color: #2E7D32;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            display: block;
            margin-top: 1.5rem;
            font-weight: 600;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <!-- Título Corporativo -->
        <h1 class="brand-title">CanchaSport</h1>
        
        <!-- Subtítulo Explicativo -->
        <p class="subtitle">
            Recuperación de Contraseña exclusiva para<br>
            <strong>Administrador o Dueño de Recinto Deportivo</strong>
        </p>

        <?php if ($success): ?>
            <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
            <h2 style="color: var(--primary); margin-bottom: 0.5rem;">¡Contraseña Actualizada!</h2>
            <p class="success">Ya puedes iniciar sesión con tu nueva clave.</p>
            <a href="../index.php">Ir al Login Unificado</a>
        
        <?php elseif ($error): ?>
            <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
            <h2 style="color: var(--primary); margin-bottom: 0.5rem;">Error</h2>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <a href="../index.php">Volver al Login</a>
        
        <?php else: ?>
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
            <h2 style="color: var(--primary); margin-bottom: 1.5rem; font-size: 1.2rem;">Define tu Nueva Contraseña</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
                </div>
                
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <input type="password" name="password_confirm" placeholder="Repite la contraseña" required>
                </div>
                
                <button type="submit">Guardar Cambios</button>
            </form>
            
            <a href="../index.php">Cancelar y Volver</a>
        <?php endif; ?>
    </div>
</body>
</html>