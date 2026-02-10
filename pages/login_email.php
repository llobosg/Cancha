<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Si ya hay sesión activa, redirigir al dashboard
if (isset($_SESSION['id_socio']) && isset($_SESSION['club_id'])) {
    // Redirigir al dashboard del club correspondiente
    $stmt = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
    $stmt->execute([$_SESSION['club_id']]);
    $club_data = $stmt->fetch();
    
    if ($club_data) {
        $club_slug = substr(md5($_SESSION['club_id'] . $club_data['email_responsable']), 0, 8);
        header('Location: dashboard_socio.php?id_club=' . $club_slug);
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $club_slug = $_POST['club_slug'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son requeridos';
    } elseif (empty($club_slug) || strlen($club_slug) !== 8) {
        $error = 'Club no válido';
    } else {
        // Encontrar el club correspondiente al slug
        $stmt_club = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE email_verified = 1");
        $stmt_club->execute();
        $clubs = $stmt_club->fetchAll();
        
        $club_id = null;
        foreach ($clubs as $c) {
            $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
            if ($generated_slug === $club_slug) {
                $club_id = (int)$c['id_club'];
                break;
            }
        }
        
        if (!$club_id) {
            $error = 'Club no encontrado';
        } else {
            // Verificar credenciales del socio
            $stmt = $pdo->prepare("
                SELECT id_socio, password_hash 
                FROM socios 
                WHERE email = ? AND id_club = ? AND password_hash IS NOT NULL
            ");
            $stmt->execute([$email, $club_id]);
            $socio = $stmt->fetch();
            
            if ($socio && password_verify($password, $socio['password_hash'])) {
                // Login exitoso
                $_SESSION['id_socio'] = $socio['id_socio'];
                $_SESSION['club_id'] = $club_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['current_club'] = $club_slug;
                
                // Redirigir al dashboard
                header('Location: dashboard_socio.php?id_club=' . $club_slug);
                exit;
            } else {
                $error = 'Credenciales incorrectas o contraseña no configurada';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login Socio | Cancha</title>
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

    .login-container {
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

    /* Responsive móvil */
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      
      .login-container {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="login-container">
      <h2 class="form-title">⚽ Login Socio</h2>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="club_slug" value="<?= htmlspecialchars($_GET['club'] ?? '') ?>">
        
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" required 
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="password">Contraseña *</label>
          <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn-submit">Iniciar Sesión</button>
      </form>
      
      <a href="../index.php" class="back-link">← Volver al inicio</a>
      
      <?php if (!empty($_GET['club'])): ?>
        <p style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
          Club: <?= htmlspecialchars($_GET['club']) ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>