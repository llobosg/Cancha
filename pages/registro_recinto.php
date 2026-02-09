<?php
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Chile');
        $region = trim($_POST['region'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $comuna = trim($_POST['comuna'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $sitioweb = trim($_POST['sitioweb'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $nombre_admin = trim($_POST['nombre_admin'] ?? '');
        $correo_admin = trim($_POST['correo_admin'] ?? '');
        $telefono_admin = trim($_POST['telefono_admin'] ?? '');
        $usuario_admin = trim($_POST['usuario_admin'] ?? '');
        $contrasena_admin = $_POST['contrasena_admin'] ?? '';
        $contrasena_admin_confirm = $_POST['contrasena_admin_confirm'] ?? '';
        
        // Validaciones
        if (empty($nombre) || empty($ciudad) || empty($comuna) || empty($direccion) || 
            empty($email) || empty($nombre_admin) || empty($correo_admin) ||
            empty($usuario_admin) || empty($contrasena_admin)) {
            throw new Exception('Todos los campos marcados con * son requeridos');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($correo_admin, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Emails inválidos');
        }
        
        if (!empty($sitioweb) && !filter_var($sitioweb, FILTER_VALIDATE_URL)) {
            throw new Exception('Sitio web inválido');
        }
        
        if ($contrasena_admin !== $contrasena_admin_confirm) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($contrasena_admin) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        // Verificar si el email ya está registrado
        $stmt = $pdo->prepare("SELECT id_recinto FROM recintos_deportivos WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este email ya está registrado');
        }
        
        // Verificar si el usuario de administrador ya existe
        $stmt = $pdo->prepare("SELECT id_admin FROM admin_recintos WHERE usuario = ?");
        $stmt->execute([$usuario_admin]);
        if ($stmt->fetch()) {
            throw new Exception('El usuario de administrador ya existe');
        }
        
        // Manejar el logo del recinto
        $logorecinto_filename = null;
        if (!empty($_FILES['logorecinto']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/logos_recintos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['logorecinto']['name'], PATHINFO_EXTENSION);
            $logorecinto_filename = 'recinto_' . uniqid() . '.' . strtolower($file_extension);
            $file_path = $upload_dir . $logorecinto_filename;
            
            if (!move_uploaded_file($_FILES['logorecinto']['tmp_name'], $file_path)) {
                throw new Exception('Error al subir el logo del recinto');
            }
        }
        
        // Generar código de verificación
        $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Preparar datos para guardar en pendientes
        $datos_recinto = json_encode([
            'nombre' => $nombre,
            'pais' => $pais,
            'region' => $region,
            'ciudad' => $ciudad,
            'comuna' => $comuna,
            'direccion' => $direccion,
            'sitioweb' => $sitioweb,
            'email' => $email,
            'telefono' => $telefono,
            'nombre_admin' => $nombre_admin,
            'correo_admin' => $correo_admin,
            'telefono_admin' => $telefono_admin,
            'logorecinto' => $logorecinto_filename
        ]);
        
        $datos_admin = json_encode([
            'usuario_admin' => $usuario_admin,
            'contrasena_admin' => password_hash($contrasena_admin, PASSWORD_DEFAULT),
            'nombre_completo' => $nombre_admin
        ]);
        
        // Guardar en tabla temporal
        $stmt = $pdo->prepare("
            INSERT INTO recintos_pendientes (datos_recinto, datos_admin, email_verificacion, codigo_verificacion)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$datos_recinto, $datos_admin, $email, $verification_code]);
        
        // Enviar código de verificación
        require_once __DIR__ . '/../includes/brevo_mailer.php';
        $mail = new BrevoMailer();
        $mail->setTo($email, $nombre_admin);
        $mail->setSubject('🔐 Código de verificación - Cancha Recintos');
        $mail->setHtmlBody("
            <h2>¡Bienvenido a Cancha!</h2>
            <p>Tu código de verificación para <strong>{$nombre}</strong> es:</p>
            <h1 style='color:#009966;'>{$verification_code}</h1>
            <p>Ingresa este código para activar tu recinto deportivo.</p>
            <p>El código es válido por 30 minutos.</p>
        ");
        $mail->send();
        
        // Guardar en sesión para la verificación
        session_start();
        $_SESSION['pending_email'] = $email;
        
        header('Location: verificar_recinto.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registra tu Recinto Deportivo | Cancha</title>
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
      color: white;
    }

    .container {
      width: 95%;
      max-width: 800px;
      margin: 0 auto;
      padding: 2rem;
    }

    .back-btn {
      color: white;
      text-decoration: none;
      margin-bottom: 1.5rem;
      display: inline-block;
      font-weight: bold;
    }

    .registration-form {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .form-title {
      color: #003366;
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.8rem;
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

    .form-section {
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid #eee;
    }

    .section-title {
      color: #071289;
      margin-bottom: 1.5rem;
      font-size: 1.3rem;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
    }

    .required {
      color: #c62828;
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

    /* Responsive móvil */
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      
      .registration-form {
        padding: 1.5rem;
      }
      
      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="../index.php" class="back-btn">← Volver al inicio</a>
    
    <div class="registration-form">
      <h1 class="form-title">🏟️ Registra tu Recinto Deportivo</h1>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <!-- Información del Recinto -->
        <div class="form-section">
          <h2 class="section-title">Información del Recinto</h2>
          
          <div class="form-row">
            <div class="form-group">
              <label for="nombre">Nombre del Recinto <span class="required">*</span></label>
              <input type="text" id="nombre" name="nombre" required>
            </div>
            
            <div class="form-group">
              <label for="pais">País</label>
              <select id="pais" name="pais">
                <option value="Chile">Chile</option>
                <option value="Argentina">Argentina</option>
                <option value="Perú">Perú</option>
                <option value="Colombia">Colombia</option>
                <option value="México">México</option>
              </select>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="region">Región</label>
              <input type="text" id="region" name="region">
            </div>
            
            <div class="form-group">
              <label for="ciudad">Ciudad <span class="required">*</span></label>
              <input type="text" id="ciudad" name="ciudad" required>
            </div>
            
            <div class="form-group">
              <label for="comuna">Comuna <span class="required">*</span></label>
              <input type="text" id="comuna" name="comuna" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="direccion">Dirección <span class="required">*</span></label>
            <textarea id="direccion" name="direccion" rows="2" required></textarea>
          </div>
          
          <div class="form-group">
            <label for="sitioweb">Sitio Web</label>
            <input type="url" id="sitioweb" name="sitioweb">
          </div>
          
          <div class="form-group">
            <label for="logorecinto">Logo del Recinto</label>
            <input type="file" id="logorecinto" name="logorecinto" accept="image/*">
          </div>
        </div>

        <!-- Contacto del Recinto -->
        <div class="form-section">
          <h2 class="section-title">Contacto del Recinto</h2>
          
          <div class="form-row">
            <div class="form-group">
              <label for="email">Email <span class="required">*</span></label>
              <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
              <label for="telefono">Teléfono</label>
              <input type="tel" id="telefono" name="telefono">
            </div>
          </div>
        </div>

        <!-- Administrador -->
        <div class="form-section">
          <h2 class="section-title">Administrador del Recinto</h2>
          
          <div class="form-group">
            <label for="nombre_admin">Nombre Completo <span class="required">*</span></label>
            <input type="text" id="nombre_admin" name="nombre_admin" required>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="correo_admin">Email <span class="required">*</span></label>
              <input type="email" id="correo_admin" name="correo_admin" required>
            </div>
            
            <div class="form-group">
              <label for="telefono_admin">Teléfono</label>
              <input type="tel" id="telefono_admin" name="telefono_admin">
            </div>
          </div>
          
          <div class="form-group">
            <label for="usuario_admin">Usuario de Administrador <span class="required">*</span></label>
            <input type="text" id="usuario_admin" name="usuario_admin" required>
          </div>
          
          <div class="form-row">
            <div class "form-group">
              <label for="contrasena_admin">Contraseña <span class="required">*</span></label>
              <input type="password" id="contrasena_admin" name="contrasena_admin" required minlength="6">
            </div>
            
            <div class="form-group">
              <label for="contrasena_admin_confirm">Confirmar Contraseña <span class="required">*</span></label>
              <input type="password" id="contrasena_admin_confirm" name="contrasena_admin_confirm" required>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">Registrar Recinto</button>
      </form>
    </div>
  </div>
</body>
</html>