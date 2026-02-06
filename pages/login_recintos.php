<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contraseña = $_POST['contraseña'] ?? '';
    
    if (empty($usuario) || empty($contraseña)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        $stmt = $pdo->prepare("
            SELECT ar.*, rd.nombre as nombre_recinto 
            FROM admin_recintos ar 
            JOIN recintos_deportivos rd ON ar.id_recinto = rd.id_recinto 
            WHERE ar.usuario = ?
        ");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($contraseña, $admin['contraseña'])) {
            $_SESSION['id_recinto'] = $admin['id_recinto'];
            $_SESSION['id_admin_recinto'] = $admin['id_admin'];
            $_SESSION['recinto_rol'] = 'admin_recinto';
            header('Location: recinto_dashboard.php');
            exit;
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
      position: relative;
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
      transition: background 0.2s;
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
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>
    
    <h2>🏟️ Login Recintos</h2>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="usuario">Usuario *</label>
        <input type="text" id="usuario" name="usuario" required>
      </div>
      
      <div class="form-group">
        <label for="contraseña">Contraseña *</label>
        <input type="password" id="contraseña" name="contraseña" required>
      </div>
      
      <button type="submit" class="btn-submit">Ingresar</button>
    </form>
    
    <div class="forgot-password">
  <a href="#" onclick="openRecoveryModal()">¿Olvidaste tu contraseña?</a>
</div>

<!-- Submodal recuperación contraseña -->
<div id="recoveryModal" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; justify-content:center; align-items:center; z-index:1001;">
  <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:400px;">
    <span class="close-modal" onclick="closeRecoveryModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
    <h3>Recuperar Contraseña</h3>
    <p>Ingresa tu email registrado y te enviaremos un código de 4 dígitos.</p>
    
    <form id="recoveryForm">
      <div class="form-group">
        <label for="recoveryEmail">Email *</label>
        <input type="email" id="recoveryEmail" name="correo" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;">
      </div>
      <button type="submit" class="btn-submit" style="width:100%;">Enviar código</button>
    </form>
  </div>
</div>

<!-- Submodal para ingresar código -->
<div id="codeModal" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; justify-content:center; align-items:center; z-index:1001;">
  <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:400px;">
    <span class="close-modal" onclick="closeCodeModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
    <h3>Ingresa Código de Recuperación</h3>
    <p>Ingresa el código de 4 dígitos que recibiste en tu correo.</p>
    
    <form id="codeForm">
      <input type="hidden" id="codeEmail" name="correo">
      <div class="form-group">
        <label for="recoveryCode">Código de 4 dígitos *</label>
        <input type="text" id="recoveryCode" name="codigo" maxlength="4" required 
               style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289; text-align:center; font-size:1.5rem;">
      </div>
      
      <div class="form-group">
        <label for="newPassword">Nueva contraseña *</label>
        <input type="password" id="newPassword" name="nueva_contraseña" required 
               style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;">
      </div>
      
      <div class="form-group">
        <label for="confirmPassword">Confirmar contraseña *</label>
        <input type="password" id="confirmPassword" name="confirmar_contraseña" required 
               style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;">
      </div>
      
      <button type="submit" class="btn-submit" style="width:100%;">Cambiar Contraseña</button>
    </form>
  </div>
</div>

<script>
function openRecoveryModal() {
  document.getElementById('recoveryModal').style.display = 'flex';
}

function closeRecoveryModal() {
  document.getElementById('recoveryModal').style.display = 'none';
}

function openCodeModal() {
  document.getElementById('recoveryModal').style.display = 'none';
  document.getElementById('codeModal').style.display = 'flex';
}

function closeCodeModal() {
  document.getElementById('codeModal').style.display = 'none';
}

// Enviar código
document.getElementById('recoveryForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const email = document.getElementById('recoveryEmail').value;
  
  fetch('../api/recuperar_contraseña_recinto.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({correo: email})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('Código enviado a tu correo. Revisa tu bandeja de entrada.');
      document.getElementById('codeEmail').value = email;
      openCodeModal();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error al enviar el código');
  });
});

// Verificar código y cambiar contraseña
document.getElementById('codeForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const email = document.getElementById('codeEmail').value;
  const codigo = document.getElementById('recoveryCode').value;
  const nuevaPass = document.getElementById('newPassword').value;
  const confirmPass = document.getElementById('confirmPassword').value;
  
  if (nuevaPass !== confirmPass) {
    alert('Las contraseñas no coinciden');
    return;
  }
  
  fetch('../api/verificar_codigo_recinto.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      correo: email,
      codigo: codigo,
      nueva_contraseña: nuevaPass
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      alert('Contraseña actualizada correctamente. Puedes iniciar sesión ahora.');
      closeCodeModal();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error al verificar el código');
  });
});
</script>
  </div>
</body>
</html>