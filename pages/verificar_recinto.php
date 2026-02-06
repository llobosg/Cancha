<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

$error = '';
$success = '';

// Verificar si hay un recinto pendiente
if (!isset($_SESSION['pending_recinto_id']) || !isset($_SESSION['pending_recinto_email'])) {
    header('Location: registro_recinto.php');
    exit;
}

$pending_recinto_id = $_SESSION['pending_recinto_id'];
$pending_recinto_email = $_SESSION['pending_recinto_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo = $_POST['codigo'] ?? '';
        
        if (empty($codigo) || strlen($codigo) !== 4) {
            throw new Exception('Código inválido. Debe tener 4 dígitos.');
        }
        
        // Verificar código
        $stmt = $pdo->prepare("
            SELECT id_recinto FROM recintos_deportivos 
            WHERE id_recinto = ? AND email = ? AND verification_code = ?
        ");
        $stmt->execute([$pending_recinto_id, $pending_recinto_email, $codigo]);
        $recinto = $stmt->fetch();
        
        if (!$recinto) {
            throw new Exception('Código de verificación incorrecto.');
        }
        
        // Activar el recinto
        $stmt = $pdo->prepare("UPDATE recintos_deportivos SET email_verified = 1, verification_code = NULL WHERE id_recinto = ?");
        $stmt->execute([$pending_recinto_id]);
        
        // Establecer sesión del administrador
        $_SESSION['id_recinto'] = $pending_recinto_id;
        $_SESSION['recinto_rol'] = 'admin_recinto';
        
        // Limpiar variables de sesión temporales
        unset($_SESSION['pending_recinto_id']);
        unset($_SESSION['pending_recinto_email']);
        
        // Redirigir al dashboard
        header('Location: recinto_dashboard.php');
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
  <title>Verificar Recinto | Cancha</title>
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
      max-width: 500px;
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

    .verification-form {
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

    .email-info {
      background: #e3f2fd;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      text-align: center;
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
      text-align: center;
    }

    .codigo-input {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
    }

    .codigo-input input {
      width: 50px;
      height: 50px;
      text-align: center;
      font-size: 1.5rem;
      font-weight: bold;
      border: 2px solid #071289;
      border-radius: 8px;
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

    .resend-link {
      text-align: center;
      margin-top: 1rem;
    }

    .resend-link a {
      color: #071289;
      text-decoration: underline;
      font-size: 0.9rem;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      
      .verification-form {
        padding: 1.5rem;
      }
      
      .codigo-input input {
        width: 45px;
        height: 45px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="registro_recinto.php" class="back-btn">← Volver al registro</a>
    
    <div class="verification-form">
      <h2 class="form-title">🔐 Verificar Recinto</h2>
      
      <div class="email-info">
        <p>Hemos enviado un código de verificación a:</p>
        <strong><?= htmlspecialchars($pending_recinto_email) ?></strong>
      </div>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="verificationForm">
        <div class="form-group">
          <label for="codigo">Ingresa el código de 4 dígitos</label>
          <div class="codigo-input">
            <input type="text" maxlength="1" data-index="0" oninput="moveToNext(this, 1)">
            <input type="text" maxlength="1" data-index="1" oninput="moveToNext(this, 2)">
            <input type="text" maxlength="1" data-index="2" oninput="moveToNext(this, 3)">
            <input type="text" maxlength="1" data-index="3" oninput="submitForm()">
          </div>
          <input type="hidden" id="codigoCompleto" name="codigo" required>
        </div>
        
        <button type="submit" class="btn-submit">Verificar Código</button>
      </form>
      
      <div class="resend-link">
        <a href="#" onclick="resendCode()">¿No recibiste el código? Reenviar</a>
      </div>
    </div>
  </div>

  <script>
    function moveToNext(currentInput, nextIndex) {
      if (currentInput.value.length === 1 && nextIndex < 4) {
        document.querySelector(`input[data-index="${nextIndex}"]`).focus();
      }
    }
    
    function submitForm() {
      const inputs = document.querySelectorAll('.codigo-input input');
      let codigo = '';
      let allFilled = true;
      
      inputs.forEach(input => {
        if (input.value === '') {
          allFilled = false;
        }
        codigo += input.value;
      });
      
      if (allFilled) {
        document.getElementById('codigoCompleto').value = codigo;
        document.getElementById('verificationForm').submit();
      }
    }
    
    function resendCode() {
      // Aquí iría la lógica para reenviar el código
      alert('Funcionalidad de reenvío en desarrollo. Por favor, verifica tu bandeja de spam.');
    }
    
    // Manejar teclas de retroceso
    document.querySelectorAll('.codigo-input input').forEach((input, index) => {
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value === '' && index > 0) {
          document.querySelector(`input[data-index="${index - 1}"]`).focus();
        }
      });
    });
  </script>
</body>
</html>