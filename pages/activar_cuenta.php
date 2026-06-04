<?php
// pages/activar_cuenta.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = null;
$socio_nombre = '';
$socio_id = null; // Guardamos el ID para usarlo después

if (empty($token)) {
    $error = "Token inválido.";
} else {
    // Verificar token
    $stmt = $pdo->prepare("SELECT id_socio, nombre, token_expires_at FROM socios WHERE activation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $socio = $stmt->fetch();

    if (!$socio) {
        $error = "Token no encontrado o ya fue usado.";
    } elseif (new DateTime() > new DateTime($socio['token_expires_at'])) {
        $error = "El enlace ha expirado. Solicita uno nuevo.";
    } else {
        $socio_nombre = $socio['nombre'];
        $socio_id = $socio['id_socio'];
        
        // Procesar formulario
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
                    
                    // Actualizar socio
                    $stmt_update = $pdo->prepare("
                        UPDATE socios 
                        SET password = ?, 
                            activation_token = NULL, 
                            token_expires_at = NULL,
                            email_verified = 1 
                        WHERE id_socio = ?
                    ");
                    $stmt_update->execute([$hash, $socio['id_socio']]);
                    
                    // === REDIRECCIÓN AUTOMÁTICA AL DASHBOARD ===
                    // Iniciamos sesión manualmente para que entre directo
                    session_start();
                    $_SESSION['id_socio'] = $socio['id_socio'];
                    $_SESSION['nombre_completo'] = $socio['nombre'];
                    // Si tienes otras variables de sesión necesarias, agrégolas aquí
                    
                    header('Location: dashboard_socio.php');
                    exit; // Importante detener la ejecución
                    
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
    <title>Activar Cuenta - CanchaSport</title>
    <style>
        /* ... tus estilos existentes ... */
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { color: #071289; margin-bottom: 1rem; }
        input { width: 100%; padding: 0.8rem; margin: 0.5rem 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 0.8rem; background: #071289; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #050d66; }
        .error { color: red; font-size: 0.9rem; margin-top: 0.5rem; }
        a { color: #071289; text-decoration: none; font-size: 0.9rem; display: block; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($error): ?>
            <div style="font-size: 3rem;">⚠️</div>
            <h2>Error</h2>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <a href="../index.php">Volver al Inicio</a>
        <?php else: ?>
            <div style="font-size: 3rem;">🔐</div>
            <h2>Hola, <?= htmlspecialchars($socio_nombre) ?></h2>
            <p style="color: #666; font-size: 0.9rem;">Crea una contraseña para acceder a tu cuenta.</p>
            
            <form method="POST">
                <input type="password" name="password" placeholder="Nueva Contraseña" required>
                <input type="password" name="password_confirm" placeholder="Confirmar Contraseña" required>
                <button type="submit">Activar Cuenta</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>