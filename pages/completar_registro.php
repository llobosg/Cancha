<?php
// pages/completar_registro.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$exito = false;
$nombre = '';

// 1. Validar Token
if ($token) {
    $stmt = $pdo->prepare("SELECT nombre FROM socios WHERE registro_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) $nombre = $user['nombre'];
    else $error = "Enlace inválido o expirado.";
}

// 2. Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $pass = $_POST['password'];
    $pass_confirm = $_POST['password_confirm'];

    if ($pass !== $pass_confirm) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // ✅ FIX: Usar columna password_hash
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // Actualizar user y borrar token
        $stmt = $pdo->prepare("UPDATE socios SET password_hash = ?, registro_token = NULL WHERE registro_token = ?");
        if ($stmt->execute([$hash, $token])) {
            $exito = true;
        } else {
            $error = "Error al guardar. Intenta de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Contraseña | CanchaSport</title>
    <style>
        body {
            margin: 0; padding: 0;
            background: linear-gradient(rgba(0, 20, 10, 0.7), rgba(0, 30, 15, 0.8)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh;
        }
        .card {
            width: 100%; max-width: 400px;
            padding: 2.5rem;
            border-radius: 16px;
            /* Fondo Anis Degrade Semi-transparente */
            background: linear-gradient(135deg, rgba(255, 251, 240, 0.95) 0%, rgba(245, 245, 220, 0.95) 100%);
            backdrop-filter: blur(10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .header { margin-bottom: 1.5rem; }
        .header h1 { margin: 0; color: #071289; font-size: 1.8rem; }
        .header h2 { margin: 5px 0 0; color: #666; font-size: 1.2rem; font-weight: 400; }
        
        .input-group {
            position: relative;
            margin-bottom: 1.2rem;
            text-align: left;
        }
        .input-group label {
            display: block; font-size: 0.85rem; font-weight: bold; color: #444; margin-bottom: 5px; margin-left: 4px;
        }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%; padding: 12px 40px 12px 15px;
            border: 2px solid #ddd; border-radius: 10px;
            background: rgba(255,255,255,0.8);
            font-size: 1rem; box-sizing: border-box;
            transition: border 0.3s;
        }
        .input-wrapper input:focus { border-color: #AB47BC; outline: none; }
        
        /* Ojo de contraseña */
        .toggle-eye {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            cursor: pointer; opacity: 0.6; transition: opacity 0.2s;
        }
        .toggle-eye:hover { opacity: 1; }
        
        button {
            width: 100%; padding: 12px;
            background: #AB47BC; border: none; color: white;
            font-weight: bold; border-radius: 10px; cursor: pointer;
            margin-top: 10px; font-size: 1rem; transition: background 0.3s;
        }
        button:hover { background: #8E24AA; }
        
        .error { color: #e74c3c; font-size: 0.9rem; margin-bottom: 10px; background: #fde8e8; padding: 8px; border-radius: 6px; }
        .success { color: #2ecc71; font-size: 1rem; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($exito): ?>
            <div class="success">
                <div style="font-size: 3rem; margin-bottom: 10px;">🎉</div>
                <strong>¡Contraseña Creada!</strong><br>
                Ya puedes iniciar sesión.
            </div>
            <button onclick="window.location.href='../index.php'">Ir al Login</button>
        <?php else: ?>
            <div class="header">
                <h1>CanchaSport 🏟️</h1>
                <h2>Hola, <?php echo htmlspecialchars($nombre ?: 'Usuario'); ?></h2>
                <p style="color:#888; font-size:0.9rem;">Define tu contraseña para acceder</p>
            </div>

            <?php if ($error): ?> <div class="error">❌ <?php echo $error; ?></div> <?php endif; ?>

            <?php if ($token): ?>
                <form method="POST" onsubmit="return validarPasswords()">
                    <div class="input-group">
                        <label>Contraseña</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••">
                            <span class="toggle-eye" onclick="toggleVisibility('password', this)">👁️</span>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Confirmar Contraseña</label>
                        <div class="input-wrapper">
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="6" placeholder="••••••••">
                            <span class="toggle-eye" onclick="toggleVisibility('password_confirm', this)">👁️</span>
                        </div>
                    </div>
                    <button type="submit">Crear Contraseña</button>
                </form>
            <?php else: ?>
                <div class="error">Enlace no válido. Solicita uno nuevo.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.textContent = "🙈"; // Mono tapado
            } else {
                input.type = "password";
                icon.textContent = "👁️"; // Ojo abierto
            }
        }
        function validarPasswords() {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('password_confirm').value;
            if(p1 !== p2) {
                alert("Las contraseñas no coinciden.");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>