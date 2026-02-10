<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        $error = 'Todos los campos son requeridos';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
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
            $error = 'El token ha expirado';
        } else {
            // Actualizar contraseña
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE socios SET password_hash = ? WHERE id_socio = ?");
            $stmt->execute([$password_hash, $token_data['id_socio']]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Restablecer Contraseña | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
                 url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      color: white;
    }

    .container {
      width: 95%;
      max-width: 400px;
      margin: 0 auto;
      padding: 2rem;
    }

    .reset-form {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .form-title {
      color: #003366;
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.5rem;
    }

    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .success {
      background: #e8f5e8;
      color: #2e7d32;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .form-group input {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
    }

    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: #050d6b;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #071289;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="reset-form">
      <h2 class="form-title">🔐 Restablecer Contraseña</h2>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <a href="../index.php" class="back-link">Ir al inicio de sesión</a>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          
          <div class="form-group">
            <label for="new_password">Nueva Contraseña *</label>
            <input type="password" id="new_password" name="new_password" required>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirmar Contraseña *</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
          
          <button type="submit" class="btn-submit">Restablecer Contraseña</button>
        </form>
        
        <a href="../index.php" class="back-link">← Volver al inicio</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>