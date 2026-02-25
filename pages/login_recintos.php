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
  <title>Login Recintos - CanchaSport</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
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
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .form-title {
      color: #FFD700;
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

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: white;
      margin-bottom: 0.5rem;
      text-align: left;
    }

    .form-group input {
      width: 100%;
      padding: 0.9rem;
      border: 2px solid #ccc;
      border-radius: 8px;
      color: #071289;
      font-size: 1rem;
      background: white;
    }

    .btn-submit {
      width: 100%;
      padding: 1rem;
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

    .forgot-password {
      text-align: center;
      margin-top: 1rem;
    }

    .forgot-password a {
      color: #FFD700;
      text-decoration: underline;
      font-size: 0.9rem;
    }

    .close-btn {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #FFD700;
      text-decoration: underline;
      font-size: 0.9rem;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .login-container {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2 class="form-title">🔐 Login Centros Deportivos</h2>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="usuario">Usuario Alias*</label>
        <input type="text" id="usuario" name="usuario" placeholder="Ej: Luis, lucho, admin, jefe" required>
      </div>
      
      <div class="form-group">
        <label for="contraseña">Contraseña *</label>
        <input type="password" id="contraseña" name="contraseña" required>
      </div>
      
      <button type="submit" class="btn-submit">Iniciar Sesión</button>
    </form>
    
    <div class="forgot-password">
      <a href="recuperar_contraseña_recinto.php">¿Olvidaste tu contraseña?</a>
    </div>
    
    <a href="../index.php" class="close-btn">Cerrar</a>
  </div>
</body>
</html>