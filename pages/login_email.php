<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Si ya hay sesión, redirigir según contexto
if (isset($_SESSION['id_socio'])) {
    if (!empty($_SESSION['torneo_slug'])) {
        $slug = $_SESSION['torneo_slug'];
        unset($_SESSION['torneo_slug']);
        header('Location: /torneo.php?slug=' . urlencode($slug));
        exit;
    }
    if (!empty($_SESSION['current_club'])) {
        header('Location: dashboard_socio.php?id_club=' . $_SESSION['current_club']);
        exit;
    }
    header('Location: ../index.php');
    exit;
}

$error = '';
$back_url = !empty($_SESSION['torneo_slug']) 
    ? '/torneo.php?slug=' . $_SESSION['torneo_slug'] 
    : '../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son requeridos';
    } else {
        // Buscar socio sin filtrar por club (login general)
        $stmt = $pdo->prepare("
            SELECT id_socio, password_hash, id_club 
            FROM socios 
            WHERE email = ? AND password_hash IS NOT NULL
        ");
        $stmt->execute([$email]);
        $socio = $stmt->fetch();

        if ($socio && password_verify($password, $socio['password_hash'])) {
            $_SESSION['id_socio'] = $socio['id_socio'];
            $_SESSION['user_email'] = $email;
            if ($socio['id_club']) {
                $_SESSION['club_id'] = $socio['id_club'];
                // Generar club_slug
                $stmt_club = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
                $stmt_club->execute([$socio['id_club']]);
                $club_data = $stmt_club->fetch();
                if ($club_data) {
                    $club_slug = substr(md5($socio['id_club'] . $club_data['email_responsable']), 0, 8);
                    $_SESSION['current_club'] = $club_slug;
                }
            }

            // Redirigir según contexto
            if (!empty($_SESSION['torneo_slug'])) {
                $slug = $_SESSION['torneo_slug'];
                unset($_SESSION['torneo_slug']);
                header('Location: /torneo.php?slug=' . urlencode($slug));
            } elseif (!empty($_SESSION['current_club'])) {
                header('Location: dashboard_socio.php?id_club=' . $_SESSION['current_club']);
            } else {
                header('Location: ../index.php');
            }
            exit;
        } else {
            $error = 'Credenciales incorrectas o contraseña no configurada';
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
      margin: 0; padding: 0;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh; color: white;
    }
    .container { width: 95%; max-width: 400px; margin: 0 auto; padding: 2rem; }
    .login-container { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .form-title { color: #003366; text-align: center; margin-bottom: 1.5rem; font-size: 1.5rem; }
    .error { background: #ffebee; color: #c62828; padding: 0.7rem; border-radius: 6px; margin-bottom: 1.5rem; text-align: center; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: bold; color: #333; margin-bottom: 0.5rem; }
    .form-group input { width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 5px; color: #071289; }
    .btn-submit { width: 100%; padding: 0.9rem; background: #071289; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
    .btn-submit:hover { background: #050d6b; }
    .back-link { display: block; text-align: center; margin-top: 1rem; color: #071289; text-decoration: none; }
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
        <div class="form-group">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="password">Contraseña *</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn-submit">Iniciar Sesión</button>
      </form>
      <a href="<?= htmlspecialchars($back_url) ?>" class="back-link">← Volver</a>
    </div>
  </div>
</body>
</html>