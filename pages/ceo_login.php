<?php
require_once __DIR__ . '/../includes/config.php';


$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contraseña = $_POST['contraseña'] ?? '';
    
    if (empty($usuario) || empty($contraseña)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM ceocancha WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $ceo = $stmt->fetch();
        
        if ($ceo && password_verify($contraseña, $ceo['contraseña'])) {
            $_SESSION['ceo_id'] = $ceo['id_ceo'];
            $_SESSION['ceo_rol'] = 'ceo_cancha';
            header('Location: ceo_dashboard.php');
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
  <title>Login CEO - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
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
    
    <h2>🔐 Login CEO</h2>
    
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
  </div>

  <!-- Submodal recuperación contraseña -->
  <div id="recoveryModal" class="submodal" style="display:none;">
    <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:400px;">
      <span class="close-modal" onclick="closeRecoveryModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
      <h3>Recuperar Contraseña</h3>
      <p>Ingresa tu correo registrado y te enviaremos un código de 4 dígitos.</p>
      
      <!-- ¡Asegúrate de que este formulario tenga el ID correcto! -->
      <form id="recoveryForm">
        <div class="form-group">
          <label for="recoveryEmail">Correo electrónico *</label>
          <input type="email" id="recoveryEmail" name="correo" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;">
        </div>
        <button type="submit" class="btn-submit" style="width:100%;">Enviar código</button>
      </form>
    </div>
  </div>

  <script>
// Funciones para abrir/cerrar modales
function openRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    if (modal) {
        modal.style.display = 'flex';
        // Animación de entrada
        modal.style.opacity = '0';
        modal.style.transform = 'translateY(20px)';
        setTimeout(() => {
            modal.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            modal.style.opacity = '1';
            modal.style.transform = 'translateY(0)';
        }, 10);
    }
}

function closeRecoveryModal() {
    const modal = document.getElementById('recoveryModal');
    if (modal) {
        // Animación de salida
        modal.style.opacity = '0';
        modal.style.transform = 'translateY(20px)';
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.opacity = '1';
            modal.style.transform = 'translateY(0)';
        }, 300);
    }
}

function openCodeModal() {
    closeRecoveryModal();
    setTimeout(() => {
        const modal = document.getElementById('codeModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            modal.style.transform = 'translateY(20px)';
            setTimeout(() => {
                modal.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                modal.style.opacity = '1';
                modal.style.transform = 'translateY(0)';
            }, 10);
        }
    }, 300);
}

function closeCodeModal() {
    const modal = document.getElementById('codeModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.style.transform = 'translateY(20px)';
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.opacity = '1';
            modal.style.transform = 'translateY(0)';
        }, 300);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM cargado - buscando formularios...');
    
    // Verificar y configurar formulario de recuperación
    const recoveryForm = document.getElementById('recoveryForm');
    if (recoveryForm) {
        console.log('Formulario recoveryForm encontrado');
        recoveryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('recoveryEmail')?.value;
            
            if (!email) {
                alert('Por favor ingresa tu correo');
                return;
            }
            
            fetch('../api/recuperar_contraseña.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({correo: email})
            })
            .then(response => {
                console.log('Respuesta API:', response.status);
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos API:', data);
                if (data.success) {
                    alert('Código enviado a tu correo. Revisa tu bandeja de entrada.');
                    const codeEmail = document.getElementById('codeEmail');
                    if (codeEmail) codeEmail.value = email;
                    openCodeModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en recuperación:', error);
                alert('Error al enviar el código: ' + error.message);
            });
        });
    } else {
        console.warn('Formulario recoveryForm NO encontrado');
    }
    
    // Verificar y configurar formulario de código
    const codeForm = document.getElementById('codeForm');
    if (codeForm) {
        console.log('Formulario codeForm encontrado');
        codeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('codeEmail')?.value;
            const codigo = document.getElementById('recoveryCode')?.value;
            const nuevaPass = document.getElementById('newPassword')?.value;
            const confirmPass = document.getElementById('confirmPassword')?.value;
            
            if (!email || !codigo || !nuevaPass || !confirmPass) {
                alert('Todos los campos son requeridos');
                return;
            }
            
            if (nuevaPass !== confirmPass) {
                alert('Las contraseñas no coinciden');
                return;
            }
            
            fetch('../api/verificar_codigo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    correo: email,
                    codigo: codigo,
                    nueva_contraseña: nuevaPass
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Contraseña actualizada correctamente. Puedes iniciar sesión ahora.');
                    closeCodeModal();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en verificación:', error);
                alert('Error al verificar el código: ' + error.message);
            });
        });
    } else {
        console.warn('Formulario codeForm NO encontrado');
    }
});

// Estilos para animaciones
const style = document.createElement('style');
style.textContent = `
.submodal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 1001;
    opacity: 1;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.submodal-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    max-width: 400px;
    width: 90%;
    position: relative;
    transform: translateY(0);
}
`;
document.head.appendChild(style);
</script>

<!-- Submodal para ingresar código -->
<div id="codeModal" class="submodal" style="display:none;">
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
</body>
</html>