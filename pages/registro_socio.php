<!-- pages/registro_socio.php -->
<?php
require_once __DIR__ . '/../includes/config.php';
$slug = $_GET['club'] ?? '';
if (!$slug) {
    header('Location: buscar_club.php');
    exit;
}

// Evitar problemas de headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener club desde URL
$club_slug_from_url = $_GET['club'] ?? '';

if (!$club_slug_from_url || strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
    header('Location: ../index.php');
    exit;
}

// Obtener todos los clubs verificados
$stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
$stmt_club->execute();
$clubs = $stmt_club->fetchAll();

$club_id = null;
$club_nombre = '';
$club_logo = '';

// Encontrar el club usando MD5 en PHP (no en SQL)
foreach ($clubs as $c) {
    $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    if ($generated_slug === $club_slug_from_url) {
        $club_id = (int)$c['id_club'];
        $club_nombre = $c['nombre'];
        $club_logo = $c['logo'] ?? '';
        break;
    }
}

if (!$club_id) {
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscríbete - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    /* Fondo corporativo */
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

    /* Submodal en web */
    .form-container {
      width: 95%;
      max-width: 900px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
    }

    /* Submodal en web */
    .form-container {
      width: 95%;
      max-width: 900px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
    }

    /* En móvil: pantalla completa sin submodal */
    @media (max-width: 768px) {
      body {
        background: white !important; /* Fondo blanco en móvil */
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
        position: relative;
      }
      
      /* Ocultar fondo corporativo en móvil */
      .form-container::before,
      .form-container::after {
        display: none;
      }
    }

    /* Logo ⚽ en esquinas */
    .form-container::before,
    .form-container::after {
      content: "⚽";
      position: absolute;
      font-size: 2.2rem;
      color: #003366;
      opacity: 0.65;
      z-index: 2;
    }
    .form-container::before { top: 22px; left: 22px; }
    .form-container::after { bottom: 22px; right: 22px; }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.6rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .club-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 1.5rem;
    }

    .club-logo {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #666;
      font-size: 1.2rem;
    }

    .club-name {
      font-size: 1.1rem;
      color: #333;
    }

    /* Botón de cierre */
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

    .close-btn:hover {
      opacity: 1;
    }

    /* Formulario */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 0.8rem 1.2rem;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin: 0;
    }

    .form-group label {
      text-align: right;
      padding-right: 0.5rem;
      display: block;
      font-size: 0.85rem;
      color: #333;
      font-weight: normal;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.85rem;
      color: #071289;
      background: #fafcff;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #071289;
      box-shadow: 0 0 0 2px rgba(7, 18, 137, 0.15);
    }

    .col-span-2 {
      grid-column: span 2;
    }

    .submit-section {
      grid-column: 1 / -1;
      text-align: center;
      margin-top: 1.8rem;
    }

    .btn-submit {
      width: auto;
      min-width: 220px;
      padding: 0.65rem 1.8rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 0.95rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: #050d66;
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

    /* Mobile layout: 4 columnas x 7 filas */
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.7rem;
      }
      .form-group label {
        text-align: left;
        padding-right: 0;
      }
      .col-span-2 {
        grid-column: span 2;
      }
      .logo-section,
      .submit-section {
        grid-column: 1 / -1;
      }
      .form-group {
        margin: 0.5rem 0;
      }
      .form-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
      }
      .form-group {
        grid-column: span 2;
      }
      .form-group:nth-child(1),
      .form-group:nth-child(2) { grid-column: span 2; }
      .form-group:nth-child(3),
      .form-group:nth-child(4) { grid-column: span 2; }
      .form-group:nth-child(5),
      .form-group:nth-child(6) { grid-column: span 2; }
      .form-group:nth-child(7),
      .form-group:nth-child(8) { grid-column: span 2; }
      .form-group:nth-child(9),
      .form-group:nth-child(10) { grid-column: span 2; }
      .form-group:nth-child(12) { grid-column: span 4; }
      .submit-section {
        grid-column: span 4;
      }
    }

    @media (max-width: 768px) {
      .mobile-full {
        grid-column: span 2 !important;
      }
    }

    /* Encabezado centrado */
    .header-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.8rem;
      position: relative;
    }

    .header-container h2 {
      text-align: center;
      color: #003366;
      font-weight: 700;
      font-size: 1.4rem;
      margin: 0;
    }

    .club-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      background: rgba(0, 51, 102, 0.05);
      padding: 0.6rem 1.2rem;
      border-radius: 12px;
      border: 1px solid rgba(0, 51, 102, 0.1);
    }

    .club-logo {
      width: 45px;
      height: 45px;
      border-radius: 8px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #666;
      font-size: 1.1rem;
    }

    .club-name {
      font-size: 1.2rem;
      font-weight: 600;
      color: #003366;
      white-space: nowrap;
    }

    /* En móviles */
    @media (max-width: 768px) {
      .header-container h2 {
        font-size: 1.3rem;
      }
      
      .club-name {
        font-size: 1.1rem;
      }
      
      .club-logo {
        width: 40px;
        height: 40px;
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <!-- Botón de cierre -->
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>

    <div class="header-container">
      <h2>Inscríbete a:</h2>
      <div class="club-header">
        <div class="club-logo">
          <?php if ($club['logo']): ?>
            <img src="../uploads/logos/<?= htmlspecialchars($club['logo']) ?>" alt="Logo" style="width:100%;height:100%;border-radius:8px;">
          <?php else: ?>
            ⚽
          <?php endif; ?>
        </div>
        <div class="club-name"><?= htmlspecialchars($club['nombre']) ?></div>
      </div>
    </div>

    <?php if ($_GET['error'] ?? ''): ?>
      <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form id="registroForm" enctype="multipart/form-data">
      <input type="hidden" name="club_slug" value="<?= htmlspecialchars($slug) ?>">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

      <div class="form-grid">
      <!-- Fila 1 -->
      <div class="form-group"><label for="nombre">Nombre</label></div>
      <div class="form-group"><input type="text" id="nombre" name="nombre" required></div>
      <div class="form-group"><label for="alias">Alias</label></div>
      <div class="form-group"><input type="text" id="alias" name="alias" required></div>
      <div class="form-group"><label for="rol">Rol</label></div>
      <div class="form-group">
            <select id="rol" name="rol" required>
              <option value="">Seleccionar</option>
              <option value="Jugador">Jugador</option>
              <option value="Capitán">Galleta</option>
              <option value="Entrenador">Amigo del club</option>
              <option value="Tesorero">Tesorero</option>
              <option value="Director">Director</option>
              <option value="Delegado">Delegado</option>
              <option value="Profe">Profe</option>
              <option value="Kine">Kine</option>
              <option value="Preparador Físico">Preparador Físico</option>
              <option value="Utilero">Utilero</option>
            </select>
          </div>

      <!-- Fila 2 -->
      <div class="form-group"><label for="fecha_nac">Fecha Nac.</label></div>
      <div class="form-group"><input type="date" id="fecha_nac" name="fecha_nac"></div>
      <div class="form-group"><label for="celular">Celular</label></div>
      <div class="form-group"><input type="tel" id="celular" name="celular"></div>

      <!-- Género en su propia fila en móviles -->
      <div class="form-group mobile-full">
        <label for="genero">Género</label>
      </div>
      <div class="form-group mobile-full">
        <select id="genero" name="genero" required>
          <option value="">Seleccionar</option>
          <option value="Femenino">Femenino</option>
          <option value="Masculino">Masculino</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <!-- Fila 3 -->
      <div class="form-group"><label for="email">Correo</label></div>
      <div class="form-group"><input type="email" id="email" name="email" required></div>
      <div class="form-group"><label for="id_puesto">Puesto</label></div>
      <div class="form-group">
        <select id="id_puesto" name="id_puesto">
          <option value="">Seleccionar</option>
          <!-- Los valores se cargarán dinámicamente -->
        </select>
      </div>
      <div class="form-group"><label for="habilidad">Habilidad</label></div>
      <div class="form-group">
        <select id="habilidad" name="habilidad">
          <option value="">Seleccionar</option>
          <option value="Básica">Básica</option>
          <option value="Intermedia">Intermedia</option>
          <option value="Avanzada">Avanzada</option>
        </select>
      </div>

      <!-- Fila 4 -->
      <div class="form-group"><label for="direccion">Dirección</label></div>
      <div class="form-group col-span-2"><input type="text" id="direccion" name="direccion"></div>
      <div></div>
      <div></div>
      <div></div>
      
      <!-- Fila 5 -->
      <div class="form-group"><label for="foto">Foto</label></div>
      <div class="form-group col-span-2"><input type="file" id="foto" name="foto" accept="image/*"></div>
    </div>

        <!-- Botón -->
        <div class="submit-section">
          <button type="submit" class="btn-submit">Enviar código de verificación</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Toast de notificaciones -->
  <div id="toast" class="toast" style="display:none;">
    <span>ℹ️</span>
    <span id="toast-message">Mensaje</span>
  </div>

  <script>
    // === FUNCIONES DE NOTIFICACIÓN ===
    function mostrarNotificacion(mensaje, tipo = 'info') {
      const tipoMap = {
        'exito': 'success',
        'error': 'error',
        'advertencia': 'warning',
        'info': 'info'
      };
      const claseTipo = tipoMap[tipo] || 'info';

      const toast = document.getElementById('toast');
      const msg = document.getElementById('toast-message');
      if (!toast || !msg) return;

      msg.textContent = mensaje;
      toast.className = 'toast ' + claseTipo;
      toast.style.display = 'flex';
      void toast.offsetWidth;
      toast.classList.add('show');

      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.style.display = 'none', 400);
      }, 5000);
    }

    function exito(msg) { mostrarNotificacion(msg, 'exito'); }
    function error(msg) { mostrarNotificacion(msg, 'error'); }

    // Manejo del formulario
    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      const btn = e.submitter;
      const originalText = btn.innerHTML;
      
      btn.innerHTML = 'Enviando...';
      btn.disabled = true;

      try {
        const response = await fetch('../api/enviar_codigo_socio.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          exito('Código enviado a tu correo');
          setTimeout(() => window.location.href = `verificar_socio.php?id=${data.id_socio}`, 1500);
        } else {
          error(data.message || 'Error al enviar código');
        }
      } catch (err) {
        console.error('Error:', err);
        error('Error de conexión. Revisa la consola.');
      } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });

    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW registrado:', reg.scope))
          .catch(err => console.log('Error SW:', err));
      });
    }

    // Cargar puestos al iniciar
    document.addEventListener('DOMContentLoaded', () => {
      fetch('../api/get_puestos.php')
        .then(r => r.json())
        .then(puestos => {
          const select = document.getElementById('id_puesto');
          puestos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id_puesto;
            opt.textContent = p.puesto;
            select.appendChild(opt);
          });
        })
        .catch(() => {
          console.warn('No se pudieron cargar los puestos');
        });
    });
  </script>
</body>
</html>