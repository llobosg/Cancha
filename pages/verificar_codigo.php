<?php
require_once __DIR__ . '/../includes/config.php';

$error_message = '';
$success = false;
$email = $_GET['email'] ?? '';
$club_slug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = $_POST['email'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        
        if (empty($email) || empty($codigo)) {
            throw new Exception('Email y código son requeridos');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido');
        }
        
        if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
            throw new Exception('Código inválido. Debe ser de 4 dígitos.');
        }
        
        // Verificar código en la base de datos
        $stmt = $pdo->prepare("
            SELECT id_club, email_responsable, verification_code, email_verified, responsable 
            FROM clubs 
            WHERE email_responsable = ? AND verification_code = ?
        ");
        $stmt->execute([$email, $codigo]);
        $club = $stmt->fetch();
        
        if (!$club) {
            throw new Exception('Código de verificación incorrecto o expirado');
        }
        
        if ($club['email_verified']) {
            // Ya verificado - solo generar slug
            $club_slug = substr(md5($club['id_club'] . $club['email_responsable']), 0, 8);
                } else {
            // Activar club
            $stmt_update = $pdo->prepare("
                UPDATE clubs SET email_verified = 1, verification_code = NULL WHERE email_responsable = ?
            ");
            $stmt_update->execute([$email]);
            
            $club_id = $club['id_club'];
            
            // === CREAR SOCIO AUTOMÁTICO SI NO EXISTE ===
            // 1. Buscar si ya existe por email (en cualquier club o individual)
            $stmt_socio_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
            $stmt_socio_check->execute([$email]);
            $socio_data = $stmt_socio_check->fetch();
            
            $id_socio_creado = null;

            if (!$socio_data) {
                // Obtener alias (primer nombre)
                $alias_socio = explode(' ', trim($club['responsable']))[0];
                
                // CAMBIO CLAVE: Usar 'Director' en lugar de 'Responsable'
                // 'Director' SÍ existe en tu ENUM de roles.
                $rol_defecto = 'Director'; 

                $stmt_insert_socio = $pdo->prepare("
                    INSERT INTO socios (email, nombre, alias, rol, es_responsable, activo, created_at) 
                    VALUES (?, ?, ?, ?, 1, 'Si', NOW())
                ");
                
                $stmt_insert_socio->execute([
                    $email,
                    $club['responsable'], 
                    $alias_socio,
                    $rol_defecto
                ]);
                
                $id_socio_creado = $pdo->lastInsertId();
            } else {
                $id_socio_creado = $socio_data['id_socio'];
            }

            // 3. Vincular Socio al Club en la tabla intermedia socio_club
            // Verificar si ya existe la relación
            $stmt_link_check = $pdo->prepare("SELECT id_socio FROM socio_club WHERE id_socio = ? AND id_club = ?");
            $stmt_link_check->execute([$id_socio_creado, $club_id]);
            
            if (!$stmt_link_check->fetch()) {
                $stmt_link = $pdo->prepare("
                    INSERT INTO socio_club (id_socio, id_club, estado, fecha_registro, es_responsable) 
                    VALUES (?, ?, 'activo', NOW(), 1)
                ");
                $stmt_link->execute([$id_socio_creado, $club_id]);
            }
            
            $club_slug = substr(md5($club_id . $email), 0, 8);
        }
        
        $success = true;
        $email_verified = $email;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verificar Código - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 10, 20, 0.40), rgba(0, 15, 30, 0.50)),
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

    .form-container {
      width: 95%;
      max-width: 600px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
    }

    @media (max-width: 768px) {
      body {
        background: white !important;
        color: #333 !important;
      }
      
      .form-container {
        width: 100%;
        max-width: none;
        height: auto;
        min-height: 100vh;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        padding: 1.5rem;
        background: white !important;
      }
    }

    .close-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 2.2rem;
      color: #003366;
      text-decoration: none;
      opacity: 0.7;
      transition: opacity 0.2s;
      z-index: 10;
    }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.6rem;
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
      background: #e8f5e9;
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
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: #333;
    }

    .form-group input {
      width: 100%;
      padding: 0.8rem;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1.2rem;
      text-align: center;
      letter-spacing: 5px;
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
      background: #050d66;
    }

    .resend-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #071289;
      text-decoration: none;
      font-weight: bold;
    }

    .success-message {
      text-align: center;
    }

    .success-message h3 {
      color: #003366;
      margin-bottom: 1rem;
    }

    .dashboard-btn {
      background: #00cc66;
      padding: 0.8rem;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      width: 100%;
      margin-top: 1rem;
    }

    /* Redirección automática */
    .redirect-message {
      text-align: center;
      margin-top: 1.5rem;
      color: #071289;
      font-style: italic;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>

    <?php if ($success): ?>
      <div class="success success-message">
        <h3>✅ ¡Club verificado exitosamente!</h3>
        <p>Tu club está activo y puedes acceder a tu Cancha.</p>
        <button class="dashboard-btn" onclick="window.location.href='dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>'">
          Ir al Dashboard
        </button>
        <div class="redirect-message">
          Redirigiendo automáticamente en 3 segundos...
        </div>
      </div>
      
      <script>
        // Redirección automática
        setTimeout(() => {
          window.location.href = 'dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>';
        }, 3000);
        
        // Guardar sesión
        const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
        localStorage.setItem('cancha_device', deviceId);
        localStorage.setItem('cancha_session', 'active');
        localStorage.setItem('cancha_club', '<?= htmlspecialchars($club_slug) ?>');
      </script>
      
    <?php else: ?>
      <h2>🔐 Verificar Código</h2>
      
      <?php if ($error_message): ?>
        <div class="error"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        
        <div class="form-group">
          <label for="codigo">Código de verificación</label>
          <input type="text" id="codigo" name="codigo" maxlength="4" placeholder="0000" required>
        </div>
        
        <button type="submit" class="btn-submit">Verificar código</button>
        
        <?php if ($email): ?>
          <a href="registro_club.php" class="resend-link">¿No recibiste el código? Solicitar nuevo</a>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

  <script>
    // Auto-focus en el campo de código
    document.getElementById('codigo')?.focus();
    
    // Formateo automático del código
    document.getElementById('codigo')?.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 4) {
        value = value.substring(0, 4);
      }
      e.target.value = value;
    });
  </script>
</body>
</html>