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
    $socio_actual = $stmt_fallback->fetch() ?: ['datos_completos' => 0, 'nombre' => 'Usuario'];
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
        r.monto_total  -- ← ¡AGREGAR ESTE CAMPO!
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
        r.monto_total  -- ← ¡Y EN EL GROUP BY!
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
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
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
              <!-- Forzar la carga del logo incluso si no se puede verificar file_exists -->
              <img src="/uploads/logos/<?= htmlspecialchars($club_logo) ?>" 
                  alt="Logo" 
                  style="width:100%;height:100%;border-radius:12px;"
                  onerror="this.parentElement.innerHTML='⚽'">
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

    <!-- Estadísticas -->
    <!-- Próximo Evento -->
    <?php if ($proximo_evento): ?>
    <div class="stat-card">
        <h3>Próximo Evento</h3>
        
        <!-- Datos principales -->
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
                <?php if ((int)$proximo_evento['players'] > 0): ?>
                    <span style="color: <?= ((int)$proximo_evento['inscritos_actuales'] >= (int)$proximo_evento['players']) ? '#ff6b6b' : '#4ECDC4' ?>;">
                        (<?= ((int)$proximo_evento['inscritos_actuales'] >= (int)$proximo_evento['players']) ? 'Lleno' : 'Disponible' ?>)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php 
        $inscritos = (int)$proximo_evento['inscritos_actuales'];
        $players = (int)$proximo_evento['players'];
        $deporte = $proximo_evento['id_deporte'];
        $id_reserva = $proximo_evento['id_reserva'];
        $monto_total = (int)$proximo_evento['monto_total'];
        
        // Deportes que requieren validación de cupo
        $deportes_con_cupo = ['futbolito', 'futsal', 'padel', 'tenis'];
        $validar_cupo = in_array($deporte, $deportes_con_cupo);
        $cupo_lleno = ($validar_cupo && $inscritos >= $players);
        ?>
        
        <!-- Mostrar Botones según estado de inscripción y cupo del partido -->
        <?php if ($cupo_lleno): ?>
            <!-- Cupo lleno -->
            <div style="background: #ff6b6b; color: white; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-top: 1rem;">
                Inscripciones cerradas
            </div>
        <?php else: ?>
            <!-- Verificar si el usuario ya está inscrito -->
            <?php 
            $stmt_check_inscrito = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ?");
            $stmt_check_inscrito->execute([$id_reserva, $_SESSION['id_socio']]);
            $ya_inscrito = $stmt_check_inscrito->fetch();
            ?>
            
            <?php if ($ya_inscrito): ?>
                <!-- Ya está inscrito -->
                <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #E74C3C;" 
                            onclick="anotarseEvento(<?= $id_reserva ?>, '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
                        Bajarse
                    </button>
                    <!-- Botón "Paso" oculto cuando está inscrito -->
                </div>
                
                <!-- Botones adicionales (siempre visibles para todos) -->
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #9B59B6;" 
                            onclick="notificarGalletas(<?= $id_reserva ?>)">
                        Notificar a galletas
                    </button>
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #F39C12;" 
                            onclick="invitarCancha(<?= $id_reserva ?>)">
                        Invitar un Cancha
                    </button>
                </div>
            <?php else: ?>
                <!-- No está inscrito -->
                <div style="display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap;">
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #4ECDC4;" 
                            onclick="anotarseEvento(<?= $id_reserva ?>, '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
                        Anotarse
                    </button>
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #FF6B6B;" 
                            onclick="pasoEvento(<?= $id_reserva ?>)">
                        Paso
                    </button>
                </div>
                
                <!-- Botones adicionales (siempre visibles para todos) -->
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #9B59B6;" 
                            onclick="notificarGalletas(<?= $id_reserva ?>)">
                        Notificar a galletas
                    </button>
                    <button class="btn-action" style="flex: 1; min-width: 120px; background: #F39C12;" 
                            onclick="invitarCancha(<?= $id_reserva ?>)">
                        Invitar un Cancha
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>  <!-- ¡ESTE ES EL CIERRE QUE FALTABA! -->

      <!-- En dashboard_socio.php, agrega esto en las acciones -->
      <div class="action-buttons">
        <button class="btn-action" onclick="window.location.href='reservar_cancha.php'">Reservar Cancha</button>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= $club_slug ?>'">Gestionar socios</button>
        <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= $club_slug ?>'">Eventos</button>
        <!-- Nuevo botón -->
        <button class="btn-action" onclick="window.location.href='login_email.php?club=<?= $club_slug ?>'">Login Alternativo</button>
      </div>

      <?php
        // Asegurar que $socio_actual esté definida
        if (!isset($socio_actual)) {
            $stmt_socio = $pdo->prepare("SELECT nombre, email, genero FROM socios WHERE id_socio = ? AND id_club = ?");
            $stmt_socio->execute([$_SESSION['id_socio'], $_SESSION['club_id']]);
            $socio_actual = $stmt_socio->fetch() ?: ['nombre' => 'Usuario', 'email' => '', 'genero' => ''];
        }

        //-- Botones condicionales según datos_completos -->
        if (!$socio_actual || !$socio_actual['datos_completos']): ?>
          <div class="welcome-message">
            <h3>👋 ¡Bienvenido! <?= htmlspecialchars($socio_actual['nombre'] ?? 'Usuario') ?></h3>
            <p>Te invitamos a <strong>completar tu perfil</strong> para acceder a todas las funcionalidades:</p>
            <ul>
              <li>📞 Teléfono de contacto</li>
              <li>🏠 Dirección completa</li>
              <li>👤 Información adicional</li>
            </ul>
            <a href="completar_perfil.php?club=<?= htmlspecialchars($club_slug) ?>" class="btn-primary">
              Completar mi perfil ahora
            </a>
          </div>
      <?php else: ?>
        <div style="text-align: center; margin: 2rem 0;">
          <a href="mantenedor_socios.php" class="update-profile-btn">
            👤 Actualizar perfil <?= htmlspecialchars($socio_actual['nombre'] ?? 'Usuario') ?>
          </a>
        </div>
      <?php endif; ?>

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

    // Función para anotarse a un evento
    function anotarseEvento(idReserva, deporte, playersMax, montoTotal) {
        const formData = new FormData();
        formData.append('action', 'anotarse');
        formData.append('id_reserva', idReserva);
        formData.append('deporte', deporte);
        formData.append('players_max', playersMax);
        formData.append('monto_total', montoTotal);
        
        fetch('../api/gestion_eventos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar toast personalizado
                mostrarToast(data.message);
                // Recargar la página para actualizar la ficha
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                mostrarToast('❌ ' + data.message);
            }
        })
        .catch(error => {
            mostrarToast('❌ Error al procesar la inscripción');
            console.error('Error:', error);
        });
    }

    // Función para marcar "Paso"
    function pasoEvento(idReserva) {
        const card = event.target.closest('.stat-card');
        if (card) {
            card.innerHTML = `
                <h3>Próximo Evento</h3>
                <div style="margin: 1rem 0; font-size: 0.9rem; text-align: center;">
                    <strong>Paso esta semana</strong>
                </div>
            `;
        }
    }

    // Función para notificar a galletas
    function notificarGalletas(idReserva) {
        // Abrir modal o implementar notificación
        alert('Función "Notificar a galletas" en desarrollo');
    }

    // Función para invitar un cancha
    function invitarCancha(idReserva) {
        // Implementar lógica de invitación
        alert('Función "Invitar un Cancha" en desarrollo');
    }

    // Función para mostrar toast notifications
    function mostrarToast(mensaje) {
        // Crear contenedor de toast si no existe
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        // Crear toast
        const toast = document.createElement('div');
        toast.textContent = mensaje;
        toast.style.cssText = `
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease-out, fadeOut 0.5s ease-in 2.5s forwards;
            font-size: 14px;
        `;
        
        toastContainer.appendChild(toast);
        
        // Eliminar toast después de 3 segundos
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }

    // Animaciones CSS para toasts
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>