<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Si ya hay sesión activa, redirigir al dashboard
if (isset($_SESSION['id_recinto']) && isset($_SESSION['recinto_rol'])) {
    header('Location: recinto_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contraseña = $_POST['contraseña'] ?? '';
    
    if (empty($usuario) || empty($contraseña)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        // Verificar credenciales
        $stmt = $pdo->prepare("
            SELECT ar.*, rd.nombre as nombre_recinto, rd.email_verified
            FROM admin_recintos ar 
            JOIN recintos_deportivos rd ON ar.id_recinto = rd.id_recinto 
            WHERE ar.usuario = ?
        ");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($contraseña, $admin['contraseña'])) {
            if (!$admin['email_verified']) {
                $error = 'Tu recinto no ha sido verificado. Por favor, revisa tu email.';
            } else {
                // Iniciar sesión
                $_SESSION['id_recinto'] = $admin['id_recinto'];
                $_SESSION['id_admin_recinto'] = $admin['id_admin'];
                $_SESSION['recinto_rol'] = 'admin_recinto';
                header('Location: recinto_dashboard.php');
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login Recintos - Cancha</title>
  <style>
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
                 url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
    }

    .login-container {
      width: 95%;
      max-width: 400px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
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
    }

    .forgot-password {
      text-align: center;
      margin-top: 1rem;
    }

    .forgot-password a {
      color: #071289;
      text-decoration: underline;
      font-size: 0.9rem;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>🏟️ Login Recintos</h2>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="usuario">Usuario recintos</label>
        <input type="text" id="usuario" name="usuario" required>
      </div>
      
      <div class="form-group">
        <label for="contraseña">Contraseña *</label>
        <input type="password" id="contraseña" name="contraseña" required>
      </div>
      
      <button type="submit" class="btn-submit">Ingresar</button>
    </form>
    
    <div class="forgot-password">
      <a href="recuperar_contraseña_recinto.php">¿Olvidaste tu contraseña?</a>
    </div>
  </div>
</body>
</html>