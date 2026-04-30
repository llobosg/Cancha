<?php
// pages/completar_registro.php
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$exito = false;
$nombre = '';

// Verificar Token
if ($token) {
    $stmt = $pdo->prepare("SELECT nombre FROM socios WHERE registro_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) $nombre = $user['nombre'];
    else $error = "Enlace inválido o expirado.";
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $pass = $_POST['password'];
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Actualizar user y borrar token (para que no se use de nuevo)
    $stmt = $pdo->prepare("UPDATE socios SET password = ?, registro_token = NULL WHERE registro_token = ?");
    if ($stmt->execute([$hash, $token])) {
        $exito = true;
    } else {
        $error = "Error al guardar. Intenta de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Contraseña | CanchaSport</title>
    <style>
        body { background: #f0f2f5; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        h2 { color: #AB47BC; margin-top: 0; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #4ECDC4; border: none; color: white; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 10px; font-size: 16px; }
        button:hover { background: #3dbdb4; }
        .error { color: #e74c3c; font-size: 14px; margin-bottom: 10px; }
        .success { color: #2ecc71; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($exito): ?>
            <h2>🎉 ¡Listo!</h2>
            <p class="success">Tu contraseña ha sido creada correctamente.</p>
            <p style="margin-top:20px; color:#666;">Ya puedes iniciar sesión con tu email y contraseña en la App.</p>
        <?php else: ?>
            <h2>🔐 Hola <?php echo htmlspecialchars($nombre ?: 'Usuario'); ?></h2>
            <p style="color:#666;">Crea una contraseña para acceder a tu perfil.</p>
            
            <?php if ($error): ?> <div class="error">❌ <?php echo $error; ?></div> <?php endif; ?>

            <?php if ($token): ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Nueva contraseña" required minlength="6">
                    <button type="submit">Guardar Contraseña</button>
                </form>
            <?php else: ?>
                <p style="color:#999;">Si no ves el formulario, vuelve a pedir el enlace desde la recepción.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>