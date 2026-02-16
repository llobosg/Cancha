<?php
require_once __DIR__ . '/../includes/config.php';

// Configuración robusta de sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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

// 🔥 FLUJO COMPLETO DE OBTENCIÓN DE ID_SOCIO CON DATOS COMPLETOS 🔥
$id_socio = null;
$socio_actual = null;

// Verificar si ya tenemos id_socio en sesión y es válido
if (isset($_SESSION['id_socio'])) {
    $id_socio = $_SESSION['id_socio'];
    
    // Validar que el socio pertenece al club actual Y obtener sus datos completos
    $stmt_validate = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club = ?");
    $stmt_validate->execute([$id_socio, $club_id]);
    $socio_actual = $stmt_validate->fetch();
    
    if (!$socio_actual) {
        $id_socio = null; // Invalidar si no pertenece al club
        $socio_actual = null;
    }
}

// Si no tenemos id_socio válido, intentar obtenerlo del login
if (!$id_socio) {
    $user_email = null;
    
    // Obtener email del login (Google o correo)
    if (isset($_SESSION['google_email'])) {
        $user_email = $_SESSION['google_email'];
    } elseif (isset($_SESSION['user_email'])) {
        $user_email = $_SESSION['user_email'];
    }
    
    if ($user_email) {
        // Buscar socio existente con este email y club
        $stmt_socio = $pdo->prepare("
            SELECT id_socio FROM socios 
            WHERE email = ? AND id_club = ?
        ");
        $stmt_socio->execute([$user_email, $club_id]);
        $socio_data = $stmt_socio->fetch();
        
        if ($socio_data) {
            // Socio ya existe - guardar en sesión
            $id_socio = $socio_data['id_socio'];
            $_SESSION['id_socio'] = $id_socio;
        } else {
            // Socio no existe, redirigir a completar perfil
            header('Location: completar_perfil.php?id=' . $club_slug);
            exit;
        }
    } else {
        // No hay email en sesión, redirigir a login
        header('Location: ../index.php');
        exit;
    }
} else {
    // Ya tenemos id_socio válido, asegurarnos de guardarlo en sesión
    $_SESSION['id_socio'] = $id_socio;
}

// Asegurar que $socio_actual esté definida (doble verificación)
if (!$socio_actual) {
    $stmt_fallback = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club = ? LIMIT 1");
    $stmt_fallback->execute([$_SESSION['id_socio'], $club_id]);
    $socio_actual = $stmt_fallback->fetch() ?: ['datos_completos' => 0, 'nombre' => 'Usuario', 'es_responsable' => 0];
}

// Guardar en sesión (asegurar que siempre estén presentes)
$_SESSION['club_id'] = $club_id;
$_SESSION['current_club'] = $club_slug;
?>

<?php
// 🔥 CONSULTA PARA PRÓXIMO EVENTO 🔥
$stmt_evento = $pdo->prepare("
    SELECT 
        r.id_reserva,
        r.id_club,
        r.fecha,
        r.hora_inicio,
        r.id_cancha,
        c.id_deporte,
        te.players,
        te.tipoevento AS tipo_evento,
        COUNT(i.id_inscrito) AS inscritos_actuales,
        c.nombre_cancha,
        r.tipo_reserva,
        r.monto_total
    FROM reservas r
    JOIN canchas c ON r.id_cancha = c.id_cancha
    JOIN tipoeventos te ON c.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
    LEFT JOIN inscritos i ON r.id_reserva = i.id_evento
    WHERE 
        r.id_club = ? 
        AND r.fecha >= CURDATE()
        AND r.estado = 'confirmada'
    GROUP BY 
        r.id_reserva,
        r.id_club,
        r.fecha,
        r.hora_inicio,
        r.id_cancha,
        c.id_deporte,
        te.players,
        te.tipoevento,
        c.nombre_cancha,
        r.tipo_reserva,
        r.monto_total
    ORDER BY r.fecha ASC, r.hora_inicio ASC
    LIMIT 1
");
$stmt_evento->execute([$_SESSION['club_id']]);
$proximo_evento = $stmt_evento->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($club_nombre) ?> | Cancha</title>
  <link rel="icon" href="image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
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
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
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

    /* NUEVO LAYOUT DIVIDIDO */
    .dashboard-upper {
      display: flex;
      height: 65vh;
      gap: 2rem;
      margin-bottom: 2rem;
    }

    .upper-left {
      flex: 0 0 70%;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.5rem;
      overflow-y: auto;
      margin-left: 20px;
    }

    .upper-right {
      flex: 0 0 30%;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
      margin-right: 20px;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      min-height: 120px;
    }

    .stat-card h3 {
      margin-bottom: 0.5rem;
      opacity: 0.9;
    }

    .btn-action {
      padding: 0.8rem 1.5rem;
      background: #00cc66;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
      min-width: 180px;
    }

    .btn-action:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }

    /* MITAD INFERIOR */
    .dashboard-lower {
      height: 35vh;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 14px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      overflow-y: auto;
      margin: 0 auto;
      max-width: 1400px;
    }

    .dashboard-lower h3 {
      margin-bottom: 1rem;
      text-align: left;
      font-size: 1.3rem;
    }

    /* FILTROS */
    .filters {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .filter-btn {
      padding: 0.4rem 0.8rem;
      background: rgba(255,255,255,0.2);
      color: white;
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .filter-btn:hover {
      background: rgba(255,255,255,0.3);
    }

    .filter-btn.active {
      background: #667eea;
      border-color: #667eea;
    }

    /* TABLA DINÁMICA */
    .dynamic-table-container {
      overflow-x: auto;
    }

    .dynamic-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }

    .dynamic-table th,
    .dynamic-table td {
      padding: 0.6rem;
      text-align: left;
      border-bottom: 1px solid rgba(255,255,255,0.2);
    }

    .dynamic-table th {
      background: rgba(102, 126, 234, 0.3);
      position: sticky;
      top: 0;
    }

    /* Share section y logout */
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

    /* Mobile responsive */
    @media (max-width: 768px) {
      .dashboard-upper {
        flex-direction: column;
        height: auto;
        margin-bottom: 1rem;
      }
      
      .upper-left {
        flex: 1;
        grid-template-columns: 1fr;
        height: auto;
        margin-left: 0;
      }
      
      .upper-right {
        flex: 1;
        flex-direction: row;
        flex-wrap: wrap;
        height: auto;
        margin-right: 0;
      }
      
      .btn-action {
        flex: 1;
        min-width: 120px;
      }
      
      .dashboard-lower {
        height: auto;
        margin-top: 1rem;
      }
      
      .filters {
        justify-content: center;
      }
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
          <?php 
          $logo_path = __DIR__ . '/../uploads/logos/' . $club_logo;
          if (file_exists($logo_path)): 
          ?>
            <img src="../uploads/logos/<?= htmlspecialchars($club_logo) ?>" alt="Logo" style="width:100%;height:100%;border-radius:12px;">
          <?php else: ?>
            ⚽
          <?php endif; ?>
        <?php else: ?>
          ⚽
        <?php endif; ?>
      </div>
      <div class="club-info">
        <h1><?= htmlspecialchars($socio_actual['nombre'] ?? 'Usuario') ?> - <?= htmlspecialchars($club_nombre) ?></h1>
        <p>Tu cancha está lista</p>
      </div>
    </div>
  </div>

  <!-- MITAD SUPERIOR -->
  <div class="dashboard-upper">
    <!-- Sub sección izquierda (70%) - 3 fichas en horizontal -->
    <div class="upper-left">
      <!-- Próximo Evento -->
      <?php if ($proximo_evento): ?>
      <div class="stat-card">
        <h3>Próximo Evento</h3>
        <div style="margin: 1rem 0; font-size: 0.9rem; text-align: left;">
          <div><strong><?= htmlspecialchars($proximo_evento['tipo_evento']) ?></strong> 
            <span style="font-size: 0.8em; opacity: 0.7;">
              (<?= $proximo_evento['tipo_reserva'] === 'semanal' ? 'Semanal' : 
                  ($proximo_evento['tipo_reserva'] === 'mensual' ? 'Mensual' : 'Spot') ?>)
            </span>
          </div>
          
          <div style="margin: 0.5rem 0;">
            <strong>📅 Fecha:</strong> <?= date('d/m/Y', strtotime($proximo_evento['fecha'])) ?><br>
            <strong>⏰ Hora:</strong> <?= substr($proximo_evento['hora_inicio'], 0, 5) ?>
          </div>
          
          <div style="margin: 0.5rem 0;">
            <strong>🏟️ Club:</strong> <?= htmlspecialchars($club_nombre) ?> (ID: <?= $_SESSION['club_id'] ?>)<br>
            <strong>⚽ Cancha:</strong> <?= htmlspecialchars($proximo_evento['nombre_cancha'] ?? 'N/A') ?> (ID: <?= $proximo_evento['id_cancha'] ?>)
          </div>
          
          <div style="margin: 0.5rem 0;">
            <strong>💰 Costo:</strong> $<?= number_format((int)$proximo_evento['monto_total'], 0, ',', '.') ?><br>
            <strong>👥 Cupo:</strong> <?= (int)$proximo_evento['inscritos_actuales'] ?>/<?= (int)$proximo_evento['players'] ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- Último Evento -->
      <div class="stat-card">
        <h3>Último Evento</h3>
        <p>Próximamente disponible</p>
      </div>
      
      <!-- Estadísticas -->
      <div class="stat-card">
        <h3>Estadísticas</h3>
        <p>Próximamente disponible</p>
      </div>
      
      <!-- Noticias (solo responsable) -->
      <?php if (isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1): ?>
      <div class="stat-card" style="grid-column: span 3;">
        <h3>Noticias</h3>
        <p>Próximamente disponible</p>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Sub sección derecha (30%) -->
    <div class="upper-right">
      <button class="btn-action" onclick="window.location.href='reservar_cancha.php'">Reservar Cancha</button>
      
      <?php if (isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1): ?>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= $club_slug ?>'">Gestionar socios</button>
      <?php endif; ?>
      
      <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= $club_slug ?>'">Eventos</button>
      <button class="btn-action" onclick="window.location.href='login_email.php?club=<?= $club_slug ?>'">Login Alternativo</button>
      <button class="btn-action" onclick="window.location.href='mantenedor_socios.php'">Actualizar perfil</button>
    </div>
  </div>

  <!-- MITAD INFERIOR -->
  <div class="dashboard-lower">
    <h3>Detalle Eventos</h3>
    
    <div class="filters">
      <button class="filter-btn active" data-filter="inscritos">Inscritos Próximo evento</button>
      <button class="filter-btn" data-filter="reservas">Reservas</button>
      <button class="filter-btn" data-filter="cuotas">Cuotas</button>
      <button class="filter-btn" data-filter="eventos">Eventos</button>
      <button class="filter-btn" data-filter="socios">Socios</button>
    </div>
    
    <div class="dynamic-table-container">
      <table class="dynamic-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Tipo</th>
            <th>Club</th>
            <th>Cancha</th>
            <th>Costo</th>
            <th>Nombre</th>
            <th>Pos</th>
            <th>Monto</th>
            <th>Pago</th>
            <th>Comentario</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="12" style="text-align: center; padding: 2rem;">Selecciona un filtro para ver los datos</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

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

  function subscribeToPush() {
    console.log('Usuario suscrito a notificaciones');
  }

  requestNotificationPermission();
</script>
</body>
</html>