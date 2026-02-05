<?php
require_once __DIR__ . '/../includes/config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener club desde URL
$club_slug_from_url = $_GET['id_club'] ?? '';

// Validar slug básico
if (!$club_slug_from_url || strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
    header('Location: ../index.php');
    exit;
}

// Buscar todos los clubs verificados
$stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
$stmt_club->execute();
$clubs = $stmt_club->fetchAll();

$club_id = null;
$club_nombre = '';
$club_logo = '';
$club_slug = null;

// Encontrar el club que coincide con el slug usando la lógica correcta
foreach ($clubs as $c) {
    $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    if ($generated_slug === $club_slug_from_url) {
        $club_id = (int)$c['id_club'];
        $club_nombre = $c['nombre'];
        $club_logo = $c['logo'] ?? '';
        $club_slug = $generated_slug;
        break;
    }
}

if (!$club_id) {
    header('Location: ../index.php');
    exit;
}

// Guardar en sesión
$_SESSION['current_club'] = $club_slug;
$_SESSION['club_id'] = $club_id;

// Obtener datos del socio actual para verificar si el perfil está completo
$socio_actual = null;
if (isset($_SESSION['id_socio'])) {
    $stmt_socio = $pdo->prepare("SELECT datos_completos FROM socios WHERE id_socio = ?");
    $stmt_socio->execute([$_SESSION['id_socio']]);
    $socio_actual = $stmt_socio->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($club_nombre) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="manifest" href="/manifest.json">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      color: white;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
      text-align: center;
    }
    
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
      text-align: left;
    }
    
    .club-logo {
      width: 70px;
      height: 70px;
      border-radius: 12px;
      object-fit: cover;
      background: rgba(255,255,255,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
    }
    
    .club-info h1 {
      margin: 0;
      font-size: 2rem;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    /* Mensaje de bienvenida */
    .welcome-message {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    .welcome-message h3 {
      margin-bottom: 1rem;
      font-size: 1.3rem;
    }
    
    .welcome-message ul {
      margin: 1rem 0;
      padding-left: 1.5rem;
    }
    
    .welcome-message li {
      margin-bottom: 0.5rem;
    }
    
    .btn-primary {
      background: #FF6B35;
      color: white;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
      display: inline-block;
    }
    
    .btn-primary:hover {
      background: #E55A2B;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
      justify-content: center;
    }
    
    .stat-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .stat-card h3 {
      margin-bottom: 0.5rem;
      opacity: 0.9;
    }
    
    .stat-card .number {
      font-size: 2rem;
      font-weight: bold;
    }
    
    .actions {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .actions h2 {
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.5rem;
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-action {
      padding: 0.8rem 1.5rem;
      background: #00cc66;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .btn-action:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }
    
    .logout {
      text-align: center;
      margin-top: 2.5rem;
    }
    
    .logout a {
      color: #ffcc00;
      text-decoration: none;
      font-weight: bold;
      font-size: 1.1rem;
    }
    
    .logout a:hover {
      text-decoration: underline;
    }

    /* Share section */
    .share-section {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 14px;
      margin-top: 2rem;
      text-align: center;
    }

    .qr-code {
      margin: 1rem auto;
      width: 180px;
      height: 180px;
      background: white;
      padding: 10px;
      border-radius: 8px;
    }

    .share-link {
      background: #e9ecef;
      padding: 0.8rem;
      border-radius: 6px;
      margin: 1rem 0;
      word-break: break-all;
      font-family: monospace;
      font-size: 0.9rem;
    }

    .copy-btn {
      background: #071289;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 0.5rem;
    }

    /* Botón Actualizar Perfil */
    .update-profile-btn {
      background: #071289;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
      margin: 2rem auto;
      display: block;
      text-decoration: none;
      width: fit-content;
    }

    .update-profile-btn:hover {
      background: #050d6b;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Header -->
    <div class="header">
      <div style="display: flex; align-items: center; gap: 1.2rem;">
        <div class="club-logo">
          <?php if ($club_logo): ?>
            <img src="../uploads/logos/<?= htmlspecialchars($club_logo) ?>" alt="Logo" style="width:100%;height:100%;border-radius:12px;">
          <?php else: ?>
            ⚽
          <?php endif; ?>
        </div>
        <div class="club-info">
          <h1><?= htmlspecialchars($club_nombre) ?></h1>
          <p>Tu cancha está lista</p>
        </div>
      </div>
    </div>

    <!-- Mensaje de bienvenida para socio fundador -->
    <?php if (!$socio_actual || !$socio_actual['datos_completos']): ?>
      <div class="welcome-message">
        <h3>👋 ¡Bienvenido, Responsable!</h3>
        <p>Como fundador de este club, te invitamos a <strong>completar tu perfil</strong> para acceder a todas las funcionalidades:</p>
        <ul>
          <li>📞 Teléfono de contacto</li>
          <li>🏠 Dirección completa</li>
          <li>👤 Información adicional</li>
        </ul>
        <a href="completar_perfil.php?club=<?= htmlspecialchars($club_slug) ?>" class="btn-primary">
          Completar mi perfil ahora
        </a>
      </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Socios activos</h3>
        <div class="number">24</div>
      </div>
      <div class="stat-card">
        <h3>Eventos</h3>
        <div class="number">8</div>
      </div>
      <div class="stat-card">
        <h3>Próximo partido</h3>
        <div class="number">Sáb 15:00</div>
      </div>
    </div>

    <!-- Acciones -->
    <div class="actions">
      <h2>Acciones rápidas</h2>
      <div class="action-buttons">
        <button class="btn-action" onclick="window.location.href='convocatoria.php?id=<?= $club_slug ?>'">Crear convocatoria</button>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= $club_slug ?>'">Gestionar socios</button>
        <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= $club_slug ?>'">Eventos</button>
      </div>
    </div>

    <!-- Botón Actualizar Perfil -->
    <a href="mantenedor_socios.php" class="update-profile-btn">
      👤 Actualizar mi perfil
    </a>

    <!-- Share section -->
    <div class="share-section">
      <h3>📱 Comparte tu club</h3>
      <p>Envía este enlace a tus compañeros para que se inscriban fácilmente:</p>
      
      <?php
      $share_url = "https://cancha-web.up.railway.app/pages/registro_socio.php?club=" . $club_slug;
      ?>
      
      <div class="qr-code" id="qrCode"></div>
      <div class="share-link" id="shareLink"><?= htmlspecialchars($share_url) ?></div>
      <button class="copy-btn" onclick="copyLink()">📋 Copiar enlace</button>
    </div>

    <!-- Cerrar sesión -->
    <div class="logout">
      <a href="../index.php" onclick="limpiarSesion()">Cerrar sesión</a>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    // Generar QR
    const shareUrl = '<?= htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8') ?>';
    new QRCode(document.getElementById("qrCode"), {
      text: shareUrl,
      width: 160,
      height: 160,
      colorDark: "#003366",
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.H
    });

    function copyLink() {
      const link = document.getElementById('shareLink').textContent;
      navigator.clipboard.writeText(link).then(() => {
        alert('¡Enlace copiado al portapapeles!');
      });
    }

    // Guardar sesión en dispositivo
    const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
    localStorage.setItem('cancha_device', deviceId);
    localStorage.setItem('cancha_session', 'active');
    localStorage.setItem('cancha_club', '<?= htmlspecialchars($club_slug) ?>');

    function limpiarSesion() {
      localStorage.removeItem('cancha_session');
      localStorage.removeItem('cancha_club');
    }

    // Registrar PWA
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
          .then(registration => {
            console.log('SW registered: ', registration);
          })
          .catch(registrationError => {
            console.log('SW registration failed: ', registrationError);
          });
      });
    }

    // Solicitar permiso para notificaciones
    function requestNotificationPermission() {
      if (!('Notification' in window)) {
        return;
      }
      
      if (Notification.permission === 'granted') {
        subscribeToPush();
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
          if (permission === 'granted') {
            subscribeToPush();
          }
        });
      }
    }

    // Suscribir al servicio de push
    function subscribeToPush() {
      console.log('Usuario suscrito a notificaciones');
    }

    // Solicitar notificaciones al cargar
    requestNotificationPermission();
  </script>
</body>
</html>