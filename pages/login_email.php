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
    $email_input = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';

    error_log("🔍 [LOGIN] Intento de login para: " . $email_input);

    if (empty($email_input) || empty($password_input)) {
        $error = 'Email y contraseña son requeridos';
        error_log("❌ [LOGIN] Campos vacíos");
    } else {
        // 1. Buscar socio
        try {
            $stmt = $pdo->prepare("
                SELECT id_socio, password_hash, nombre, es_responsable, datos_completos, email_verified, activo
                FROM socios 
                WHERE email = ? AND password_hash IS NOT NULL
                LIMIT 1
            ");
            $stmt->execute([$email_input]);
            $socio = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$socio) {
                error_log("❌ [LOGIN] Usuario NO encontrado en BD para email: " . $email_input);
                $error = 'Credenciales incorrectas (Usuario no encontrado)';
            } else {
                error_log("✅ [LOGIN] Usuario encontrado: ID=" . $socio['id_socio'] . " | Verificado: " . $socio['email_verified'] . " | Activo: " . $socio['activo']);

                // 2. Validar Contraseña
                $pass_valid = password_verify($password_input, $socio['password_hash']);
                
                if (!$pass_valid) {
                    error_log("❌ [LOGIN] Contraseña INCORRECTA para usuario: " . $socio['id_socio']);
                    // Debug: Mostrar hash parcial para verificar que existe
                    error_log("🔑 [DEBUG] Hash en BD: " . substr($socio['password_hash'], 0, 20) . "...");
                    error_log("🔑 [DEBUG] Pass ingresada: " . $password_input);
                    
                    $error = 'Credenciales incorrectas (Contraseña inválida)';
                } else {
                    error_log("✅ [LOGIN] Contraseña CORRECTA!");

                    // 3. Verificar estado del usuario (Activo / Verificado)
                    if ($socio['activo'] !== 'Si') {
                        error_log("⚠️ [LOGIN] Usuario encontrado pero INACTIVO.");
                        $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                    } 
                    // Opcional: Si quieres forzar verificación de email también
                    /*
                    elseif ($socio['email_verified'] == 0) {
                         error_log("️ [LOGIN] Usuario encontrado pero EMAIL NO VERIFICADO.");
                         $error = 'Debes verificar tu correo electrónico.';
                    }
                    */
                    else {
                        // ✅ LOGIN EXITOSO - Iniciar Sesión
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }

                        $_SESSION['id_socio'] = $socio['id_socio'];
                        $_SESSION['user_email'] = $email_input;
                        $_SESSION['nombre'] = $socio['nombre'];
                        $_SESSION['es_responsable'] = $socio['es_responsable'];
                        
                        error_log("🚀 [LOGIN] Sesión iniciada para ID: " . $socio['id_socio']);

                        // 4. Obtener Club
                        $stmt_club = $pdo->prepare("
                            SELECT c.id_club, c.email_responsable, sc.estado
                            FROM socio_club sc
                            JOIN clubs c ON sc.id_club = c.id_club
                            WHERE sc.id_socio = ? AND sc.estado = 'activo'
                            LIMIT 1
                        ");
                        $stmt_club->execute([$socio['id_socio']]);
                        $club_data = $stmt_club->fetch();

                                                // ... (código anterior donde obtienes $club_data y generas $club_slug) ...

                        if ($club_data) {
                            $_SESSION['club_id'] = $club_data['id_club'];
                            $club_slug = substr(md5($club_data['id_club'] . $club_data['email_responsable']), 0, 8);
                            $_SESSION['current_club'] = $club_slug;
                            
                            error_log("🏟️ [LOGIN] Club asignado: ID=" . $club_data['id_club'] . " | Slug=" . $club_slug);
                            
                            // Forzar escritura de sesión (útil en algunos entornos como Railway/FrankenPHP)
                            session_write_close(); 
                        } else {
                            error_log("⚠️ [LOGIN] Usuario válido pero SIN CLUB asociado activo.");
                            // Opcional: Redirigir a página de error o selección de club
                            header('Location: ../index.php?error=no_club_found');
                            exit;
                        }

                        // 5. Redirección FINAL
                        if ($socio['datos_completos'] == 0) {
                            error_log("➡️ [LOGIN] Redirigiendo a COMPLETAR PERFIL");
                            // Pasamos el club_slug también a completar perfil
                            header('Location: completar_perfil.php?first_time=1&id_club=' . $club_slug);
                            exit;
                        } elseif (!empty($_SESSION['current_club'])) {
                            // REDIRECCIÓN CORREGIDA: Usar la variable $club_slug generada arriba
                            $redirect_url = 'dashboard_socio.php?id_club=' . $club_slug;
                            error_log("➡️ [LOGIN] Redirigiendo a DASHBOARD: " . $redirect_url);
                            
                            header('Location: ' . $redirect_url);
                            exit;
                        } else {
                            error_log("⚠️ [LOGIN] Fallback a INDEX");
                            header('Location: ../index.php?msg=sin_club_session');
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("💥 [LOGIN] ERROR EXCEPTION: " . $e->getMessage());
            $error = 'Error interno del servidor';
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