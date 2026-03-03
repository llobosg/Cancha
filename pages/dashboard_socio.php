<?php
error_log("=== INICIO DASHBOARD_SOCIO.PHP ===");
error_log("GET recibido: " . print_r($_GET, true));
error_log("SESSION inicial: " . (isset($_SESSION) ? print_r($_SESSION, true) : 'NO DEFINIDA'));

require_once __DIR__ . '/../includes/config.php';

if (!defined('VAPID_PUBLIC_KEY')) {
    define('VAPID_PUBLIC_KEY', '');
}

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
error_log("SESSION después de start: " . print_r($_SESSION, true));

// Determinar si es modo individual
$modo_individual = !isset($_GET['id_club']) || empty($_GET['id_club']);
error_log("MODO INDIVIDUAL: " . ($modo_individual ? 'true' : 'false'));

if ($modo_individual) {
    error_log("✓ Modo individual detectado");
    $club_id = null;
    $club_nombre = '';
    $club_logo = null;
    $club_slug = null;
} else {
    error_log("Modo club detectado");
    $club_slug_from_url = $_GET['id_club'];
    
    if (strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
        error_log("❌ Slug inválido, redirigiendo a index.php");
        header('Location: ../index.php');
        exit;
    }

    // Buscar club
    $stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
    $stmt_club->execute();
    $clubs = $stmt_club->fetchAll();
    error_log("Clubs encontrados: " . count($clubs));

    $club_id = null;
    $club_nombre = '';
    $club_logo = '';
    $club_slug = null;

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
        error_log("❌ Club no encontrado, redirigiendo a index.php");
        header('Location: ../index.php');
        exit;
    }
    error_log("✓ Club cargado: " . $club_nombre);
}

// 🔥 FLUJO COMPLETO DE OBTENCIÓN DE ID_SOCIO CON DATOS COMPLETOS 🔥
$id_socio = null;
$socio_actual = null;

// Verificar si ya tenemos id_socio en sesión y es válido
if (isset($_SESSION['id_socio'])) {
    $id_socio = $_SESSION['id_socio'];
    error_log("ID_SOCIO desde sesión: " . $id_socio);
    
    if ($modo_individual) {
        $stmt_validate = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club IS NULL");
        $stmt_validate->execute([$id_socio]);
    } else {
        $stmt_validate = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club = ?");
        $stmt_validate->execute([$id_socio, $club_id]);
    }
    
    $socio_actual = $stmt_validate->fetch();
    error_log("Socio actual encontrado: " . ($socio_actual ? 'true' : 'false'));
    
    if (!$socio_actual) {
        $id_socio = null;
        $socio_actual = null;
        error_log("❌ Socio no válido, limpiando ID");
    }
}

if (!$id_socio) {
    error_log("No hay ID_SOCIO válido, buscando por email...");
    $user_email = null;
    
    if (isset($_SESSION['google_email'])) {
        $user_email = $_SESSION['google_email'];
        error_log("Email desde Google: " . $user_email);
    } elseif (isset($_SESSION['user_email'])) {
        $user_email = $_SESSION['user_email'];
        error_log("Email desde sesión: " . $user_email);
    }
    
    if ($user_email) {
        if ($modo_individual) {
            $stmt_socio = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? AND id_club IS NULL");
            $stmt_socio->execute([$user_email]);
        } else {
            $stmt_socio = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? AND id_club = ?");
            $stmt_socio->execute([$user_email, $club_id]);
        }
        
        $socio_data = $stmt_socio->fetch();
        error_log("Socio encontrado por email: " . ($socio_data ? 'true' : 'false'));
        
        if ($socio_data) {
            $id_socio = $socio_data['id_socio'];
            $_SESSION['id_socio'] = $id_socio;
            error_log("✓ ID_SOCIO guardado en sesión: " . $id_socio);
        } else {
            error_log("❌ Socio no existe, redirigiendo a completar_perfil");
            if ($modo_individual) {
                header('Location: completar_perfil.php?modo=individual');
            } else {
                header('Location: completar_perfil.php?id=' . $club_slug);
            }
            exit;
        }
    } else {
        error_log("❌ No hay email en sesión, redirigiendo a index.php");
        header('Location: ../index.php');
        exit;
    }
} else {
    $_SESSION['id_socio'] = $id_socio;
    error_log("✓ ID_SOCIO ya existía en sesión");
}

// Asegurar que $socio_actual esté definida
if (!$socio_actual) {
    error_log("Cargando socio_actual desde fallback...");
    if ($modo_individual) {
        $stmt_fallback = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club IS NULL LIMIT 1");
        $stmt_fallback->execute([$_SESSION['id_socio']]);
    } else {
        $stmt_fallback = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ? AND id_club = ? LIMIT 1");
        $stmt_fallback->execute([$_SESSION['id_socio'], $club_id]);
    }
    $socio_actual = $stmt_fallback->fetch() ?: ['datos_completos' => 0, 'nombre' => 'Usuario', 'es_responsable' => 0];
    error_log("Socio actual fallback: " . ($socio_actual ? 'cargado' : 'predeterminado'));
}

// Guardar en sesión
if (!$modo_individual) {
    $_SESSION['club_id'] = $club_id;
    $_SESSION['current_club'] = $club_slug;
    error_log("✓ Datos de club guardados en sesión");
}

error_log("=== FIN INICIO DASHBOARD_SOCIO.PHP ===");
?>

<?php
// 🔥 CONSULTA PARA PRÓXIMO EVENTO 🔥
$proximo_evento = null;

// Solo cargar eventos si es socio de club (no individual)
if (!$modo_individual && isset($_SESSION['club_id'])) {
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
        r.monto_total,
        r.monto_recaudacion,
        r.jugadores_esperados
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
        r.monto_total,
        r.monto_recaudacion,
        r.jugadores_esperados
    ORDER BY r.fecha ASC, r.hora_inicio ASC
    LIMIT 1
");
    $stmt_evento->execute([$_SESSION['club_id']]);
    $proximo_evento = $stmt_evento->fetch();
}
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

    /* Ajustar proporción: más espacio para fichas, menos para botones */
    .dashboard-upper {
      display: flex;
      height: auto; /* ← Cambiado de 75vh a auto */
      gap: 2rem;
      margin-bottom: 1.5rem; /* ← Reducido de 2rem a 1.5rem */
    }

    .upper-right {
      flex: 0 0 15%;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
      margin-right: 20px;
    }

    /* BOTONES SUPERIOR DERECHA - MÁS CORTOS */
    .btn-action {
      padding: 0.4rem 1rem;
      background: #00cc66;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
      min-width: 110px;
    }

    .btn-action:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }

    /* SUBIR DETALLE EVENTOS - POSICIÓN CERCANA A FICHAS */
    .dashboard-lower {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 14px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      overflow-y: auto;
      max-height: 600px; /* ≈14 filas */
      margin: 0 auto 2rem auto;
      max-width: 1400px;
    }

    /* Asegurar que la tabla no se desborde */
    .dynamic-table-container {
      max-height: 500px;
      overflow-y: auto;
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

    /* CERRAR SESIÓN EN HEADER */
    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .logout-header {
      color: #ffcc00;
      text-decoration: none;
      font-weight: bold;
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .logout-header:hover {
      text-decoration: underline;
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
        grid-template-columns: repeat(2, 1fr); /* 2 columnas en móvil */
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
      
      .dashboard-lower {
        height: auto;
        margin-top: 1rem;
      }
      
      .filters {
        justify-content: center;
      }
    }

    /* ALTURA FIJA PARA FICHAS */
    .stat-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 1rem;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      height: 310px; /* Altura fija para todas las fichas */
      display: flex;
      flex-direction: column;
    }

    .stat-card h3 {
      margin-bottom: 0.5rem;
      opacity: 0.9;
    }

    .stat-card-content {
      flex: 1;
      overflow-y: auto;
    }

    /* BOTONES DENTRO DE FICHA - LAYOUT EN PARES */
    .ficha-buttons {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    /* Ajuste para botones dentro de fichas */
    .ficha-buttons .btn-action {
      padding: 0.4rem;
      font-size: 0.8rem;
      min-width: auto;
      width: 100%;
      box-sizing: border-box; /* ← clave para respetar el ancho del contenedor */
    }

    @media (max-width: 768px) {
      .ficha-buttons {
        grid-template-columns: 1fr;
      }
    }

    .btn-share {
      background: rgba(255,255,255,0.2);
      color: white;
      border: 1px solid rgba(255,255,255,0.4);
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-share:hover {
      background: rgba(255,255,255,0.3);
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
            <img src="../assets/icons/logo2-icon-192x192.png" alt="CanchaSport" style="width:100%;height:100%;border-radius:12px;">
          <?php endif; ?>
        <?php else: ?>
          <img src="../assets/icons/logo2-icon-192x192.png" alt="CanchaSport" style="width:100%;height:100%;border-radius:12px;">
        <?php endif; ?>
      </div>
      <div class="club-info">
        <h2><?= htmlspecialchars($socio_actual['nombre'] ?? 'Usuario') ?> - <?= htmlspecialchars($club_nombre) ?></h2>
        <p>Tu Cancha está lista</p>
      </div>
    </div>
    
    <div class="header-right">
      <?php if (!$modo_individual): ?>
        <button class="btn-share" onclick="abrirModalCompartir()">📤 Compartir club</button>
      <?php endif; ?>
      <a href="../index.php" onclick="limpiarSesion()" class="logout-header">Salir</a>
    </div>
  </div>

  <!-- MITAD SUPERIOR -->
  <div class="dashboard-upper">
    <!-- Sub sección izquierda (75% en desktop) -->
    <div class="upper-left">

      <?php
      // === INICIALIZACIÓN: Verificar inscripción y cuota pendiente ===
      $ya_inscrito = false;
      $id_cuota = null;
      $id_reserva = null;
      $players = 0;
      $monto_total = 0;
      $deporte = '';
      if ($proximo_evento) {
          $id_reserva = $proximo_evento['id_reserva'];
          $players = (int)$proximo_evento['players'];
          $monto_total = (float)$proximo_evento['monto_total'];
          $deporte = $proximo_evento['id_deporte'];

          $stmt_check = $pdo->prepare("
              SELECT 1 
              FROM inscritos 
              WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = 'reserva'
          ");
          $stmt_check->execute([$id_reserva, $_SESSION['id_socio']]);
          $ya_inscrito = (bool) $stmt_check->fetch();

          if ($ya_inscrito) {
              $stmt_cuota = $pdo->prepare("
                  SELECT id_cuota 
                  FROM cuotas 
                  WHERE id_evento = ? AND id_socio = ? AND tipo_actividad = 'reserva' AND estado = 'pendiente'
              ");
              $stmt_cuota->execute([$id_reserva, $_SESSION['id_socio']]);
              $cuota_row = $stmt_cuota->fetch();
              $id_cuota = $cuota_row ? $cuota_row['id_cuota'] : null;
          }
      }

      $stmt_deudas = $pdo->prepare("
          SELECT 
              c.id_cuota,
              c.monto,
              c.fecha_vencimiento,
              CASE 
                  WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                  WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                  ELSE 'Sin detalle'
              END as detalle_origen,
              COALESCE(r.fecha, e.fecha) as fecha_evento
          FROM cuotas c
          LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
          LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
          LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
          LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
          LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
          WHERE c.id_socio = ? AND c.estado = 'pendiente'
          ORDER BY c.fecha_vencimiento ASC  -- La más antigua (más urgente)
          LIMIT 1
      ");
      $stmt_deudas->execute([$_SESSION['id_socio']]);
      $deuda_mas_vigente = $stmt_deudas->fetch(); // Solo una

      // Contar total de deudas pendientes
      $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM cuotas WHERE id_socio = ? AND estado = 'pendiente'");
      $stmt_count->execute([$_SESSION['id_socio']]);
      $total_deudas = (int)$stmt_count->fetchColumn();
      ?>

      <!-- === CONTENEDOR GRID RESPONSIVE === -->
      <div class="fichas-dashboard">
        <!-- Próximo Evento -->
        <?php if ($proximo_evento): ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
          <h3>⚽ Próximo Evento</h3>
          <?php
            $icono_deporte = '⚽';
            if (in_array($deporte, ['futbol', 'fútbol', 'futbolito', 'futsal'])) {
                $icono_deporte = '⚽';
            } elseif (in_array($deporte, ['padel', 'pádel', 'tenis'])) {
                $icono_deporte = '🎾';
            } elseif (in_array($deporte, ['volley', 'voleibol', 'volleyball'])) {
                $icono_deporte = '🏐';
            } elseif ($deporte === 'gimnasio') {
                $icono_deporte = '🏋️';
            } elseif ($deporte === 'piscina') {
                $icono_deporte = '🏊';
            }
            $tipo_reserva_label = match($proximo_evento['tipo_reserva']) {
                'semanal' => 'Semanal',
                'mensual' => 'Mensual',
                default => 'Spot'
            };
          ?>
          <div style="margin:0.5rem 0;font-size:0.85rem;text-align:left;">
            <div><strong><?= $icono_deporte ?> <?= htmlspecialchars($proximo_evento['tipo_evento']) ?></strong> <span style="font-size:0.7em;opacity:0.7;">(<?= $tipo_reserva_label ?>)</span></div>
            <div style="margin:0.3rem 0;"><strong>📅</strong> <?= date('d/m', strtotime($proximo_evento['fecha'])) ?> • <strong>⏰</strong> <?= substr($proximo_evento['hora_inicio'], 0, 5) ?></div>
            <div style="margin:0.3rem 0;"><strong>🏟️</strong> <?= htmlspecialchars($proximo_evento['nombre_cancha'] ?? 'N/A') ?></div>
            <div style="margin:0.3rem 0;"><strong>💰 Arriendo</strong> $<?= number_format((int)$monto_total, 0, ',', '.') ?>
            <?php if ($proximo_evento['monto_recaudacion']): ?>
            <div style="margin:0.3rem 0; font-size:0.8rem; color:#FFD700;">
              <strong>💰 Cuota:</strong> $<?= number_format((int)$proximo_evento['monto_recaudacion'], 0, ',', '.') ?>
              <br><strong>👥 Cupos:</strong> <?= (int)$proximo_evento['jugadores_esperados'] ?> • <strong>👥 Anotados</strong> <?= (int)$proximo_evento['inscritos_actuales'] ?></div>
            </div>
            <?php endif; ?>
          </div>

          <div class="ficha-buttons">
            <?php if ($ya_inscrito): ?>
              <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;"
                      onclick="anotarseEvento(<?= $id_reserva ?>, 'reserva', '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
                Bajarse
              </button>
            <?php else: ?>
              <button class="btn-action" style="background:#4ECDC4;padding:0.4rem;font-size:0.8rem;"
                      onclick="anotarseEvento(<?= $id_reserva ?>, 'reserva', '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
                Anotarse
              </button>
            <?php endif; ?>

            <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;"
                    onclick="pasoEvento(<?= $id_reserva ?>)">
              <?= $ya_inscrito ? 'Paso' : 'Paso' ?>
            </button>

            <?php if (isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1): ?>
              <button class="btn-action" style="background:#9B59B6;padding:0.4rem;font-size:0.8rem;"
                      onclick="invitarGalletas(<?= $id_reserva ?>)">
                Invitar Galletas
              </button>
              <button class="btn-action" style="background:#F39C12;padding:0.4rem;font-size:0.8rem;"
                      onclick="invitarCancha(<?= $id_reserva ?>)">
                Invitar un Cancha
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Deudas Pendientes -->
        <?php if ($deuda_mas_vigente): ?>
          <div class="stat-card" style="background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); color: #071289;">
            <h3>⚠️ Deuda Pendiente</h3>
            <div style="margin:0.8rem 0;padding:0.6rem;background:rgba(255,255,255,0.7);border-radius:8px;font-size:0.85rem;">
              <strong><?= htmlspecialchars($deuda_mas_vigente['detalle_origen']) ?></strong><br>
              <strong>📅</strong> <?= date('d/m', strtotime($deuda_mas_vigente['fecha_evento'])) ?> • 
              <strong>💰</strong> $<?= number_format($deuda_mas_vigente['monto'], 0, ',', '.') ?><br>
              <button class="btn-action" style="background:#E74C3C;margin-top:0.5rem;font-size:0.8rem;color:white;"
                      onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">
                Pagar ahora
              </button>
            </div>

            <?php if ($total_deudas > 1): ?>
              <p style="font-size:0.8rem; margin-top:0.8rem; opacity:0.8;">
                ⚠️ Existens más cuotas pendientes, las puedes revisar en <strong>Detalle Eventos → Cuotas</strong>
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stat-card">
          <h3>Estadísticas</h3>
          <div class="stat-card-content">
            <p style="margin-top:2rem;">Próximamente disponible</p>
          </div>
        </div>

        <!-- Noticias -->
        <div class="stat-card">
          <h3>Noticias</h3>
          <div class="stat-card-content">
            <div style="text-align:left;font-size:0.85rem;line-height:1.4;">
              <div>• Bienvenidos a la temporada 2026</div>
              <div>• Nuevas reglas para inscripciones</div>
              <div>• Torneo interno próximamente</div>
              <div>• Actualización de horarios</div>
              <div>• Nuevo sistema de cuotas</div>
              <div>• Eventos especiales</div>
              <div>• Capacitación para capitanes</div>
              <div>• Mantención de canchas</div>
              <div>• Seguro deportivo obligatorio</div>
              <div>• Más novedades pronto...</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sub sección derecha (25% en desktop) -->
    <div class="upper-right">
      <button class="btn-action" onclick="window.location.href='reservar_cancha.php'">Reservar Cancha</button>
      
      <?php if (isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1): ?>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= htmlspecialchars($club_slug) ?>'">Gestionar socios</button>
      <?php endif; ?>
      
      <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= htmlspecialchars($club_slug) ?>'">Eventos</button>
      <button class="btn-action" onclick="window.location.href='login_email.php?club=<?= htmlspecialchars($club_slug) ?>'">Login Alternativo</button>
      <button class="btn-action" onclick="window.location.href='mantenedor_socios.php'">Actualizar perfil</button>
    </div>
  </div>

  <!-- CSS RESPONSIVE CORREGIDO -->
<style>
/* Asegurar layout flexible en desktop */
@media (min-width: 1024px) {
  .dashboard-upper {
    display: flex;
    gap: 1.8rem;
    margin-top: 1.2rem;
  }
  .upper-left {
    flex: 4; /* ~80% del ancho */
    max-width: none;
    margin-left: 20px;
  }
  .upper-right {
    flex: 1; /* ~20% del ancho */
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-right: 20px;
  }
}

/* Contenedor de fichas */
.fichas-dashboard {
  display: grid;
  gap: 1.4rem;
  width: 100%;
  /* Móvil: 1 columna */
  grid-template-columns: 1fr;
}

/* Tablet: 2 columnas */
@media (min-width: 768px) and (max-width: 1023px) {
  .fichas-dashboard {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Desktop: 4 columnas */
@media (min-width: 1024px) {
  .fichas-dashboard {
    grid-template-columns: repeat(4, 1fr);
  }
}

/* Asegurar que las tarjetas usen todo el ancho */
.fichas-dashboard > .stat-card {
  width: 100%;
  min-width: 0;
}

/* Ajuste adicional: asegurar que upper-left no use grid directamente */
.upper-left {
  display: block; /* o flex, pero NO grid */
}
</style>

  <!-- MITAD INFERIOR -->
  <div class="dashboard-lower">
    <h3>Detalle Eventos</h3>
    
    <div class="filters">
      <button class="filter-btn active" data-filter="inscritos">Inscritos Próximo evento</button>
      
      <?php if (!$modo_individual): ?>
        <button class="filter-btn" data-filter="reservas">Reservas</button>
        <button class="filter-btn" data-filter="cuotas">Cuotas</button>
        <button class="filter-btn" data-filter="eventos">Eventos</button>
        <button class="filter-btn" data-filter="socios">Socios</button>
      <?php endif; ?>
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
            <th>Arriendo</th>
            <th>Nombre</th>
            <th>Pos</th>
            <th>Cuota</th>
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

  <!-- Sección de compartir y logout -->
  <script>
  // === VARIABLES PHP INYECTADAS DE FORMA SEGURA ===
  const shareUrl = <?= json_encode($share_url ?? '') ?>;
  const clubSlug = <?= json_encode($club_slug ?? '') ?>;
  const idSocio = <?= json_encode($_SESSION['id_socio'] ?? 0) ?>;

  // === QR en dashboard (si se usara en futuro) ===
  // Nota: Ya no se usa aquí, pero se deja comentado por si acaso
  /*
  if (document.getElementById("qrCode")) {
      new QRCode(document.getElementById("qrCode"), {
          text: shareUrl,
          width: 160,
          height: 160,
          colorDark: "#003366",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
      });
  }
  */

  function copyLink() {
      const link = document.getElementById('shareLink')?.textContent;
      if (link) {
          navigator.clipboard.writeText(link).then(() => {
              alert('¡Enlace copiado al portapapeles!');
          });
      }
  }

  // === Gestión de sesión local ===
  const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
  localStorage.setItem('cancha_device', deviceId);
  localStorage.setItem('cancha_session', 'active');
  localStorage.setItem('cancha_club', clubSlug);

  function limpiarSesion() {
      localStorage.removeItem('cancha_session');
      localStorage.removeItem('cancha_club');
  }

  // === PWA ===
  if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
          navigator.serviceWorker.register('/service-worker.js')
              .then(registration => console.log('SW registered:', registration))
              .catch(err => console.log('SW registration failed:', err));
      });
  }

  // === Notificaciones Push ===
  function requestNotificationPermission() {
      if (!('Notification' in window)) return;
      if (Notification.permission === 'granted') {
          subscribeToPush();
      } else if (Notification.permission !== 'denied') {
          Notification.requestPermission().then(permission => {
              if (permission === 'granted') subscribeToPush();
          });
      }
  }

  function subscribeToPush() {
    const vapidKey = <?= json_encode(VAPID_PUBLIC_KEY ?? '') ?>;
    if (!vapidKey || vapidKey.length < 10) {
        console.warn('VAPID key no configurada, notificaciones push desactivadas');
        return;
    }

    navigator.serviceWorker.ready.then(registration => {
        return registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey)
        });
    }).then(subscription => {
        fetch('../api/guardar_suscripcion.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_socio: <?= json_encode($_SESSION['id_socio'] ?? 0) ?>,
                subscription: subscription
            })
        });
    }).catch(err => {
        console.error('Error al suscribir a notificaciones:', err);
    });
  }

  // === Funciones de interacción ===
  function anotarseEvento(idActividad, tipoActividad, deporte, playersMax, montoTotal) {
      const formData = new FormData();
      formData.append('action', 'anotarse');
      formData.append('id_actividad', idActividad);
      formData.append('tipo_actividad', tipoActividad);
      formData.append('deporte', deporte);
      formData.append('players_max', playersMax);
      formData.append('monto_total', montoTotal);
      
      fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  mostrarToast(data.message);
                  setTimeout(() => location.reload(), 1500);
              } else {
                  mostrarToast('❌ ' + data.message);
              }
          })
          .catch(error => {
              mostrarToast('❌ Error al procesar la inscripción');
              console.error('Error:', error);
          });
  }

  function mostrarToast(mensaje) {
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
      
      setTimeout(() => {
          if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 3000);
  }

  // Animaciones para toasts
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

  function pasoEvento(idReserva) {
      const btn = event.target;
      btn.textContent = 'Paso esta semana';
      btn.disabled = true;
      btn.style.opacity = '0.7';
  }

  function invitarGalletas(idReserva) {
      alert('Función "Invitar Galletas" en desarrollo');
  }

  function invitarCancha(idReserva) {
      alert('Función "Invitar un Cancha" en desarrollo');
  }

  function pagarCuota(idCuota) {
      window.location.href = 'pagar_cuota.php?id_cuota=' + idCuota;
  }

  // === Carga de tablas ===
  function cargarDetalleEventos(filtro = 'inscritos') {
      let url = filtro === 'cuotas' 
          ? '../api/cargar_cuotas_socio.php'
          : `../api/cargar_detalle_eventos.php?filtro=${filtro}`;

      fetch(url)
          .then(response => response.json())
          .then(data => {
              const tbody = document.querySelector('.dynamic-table tbody');
              if (data.error) {
                  tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;color:#ff6b6b;">${data.error}</td></tr>`;
                  return;
              }
              if (data.length === 0) {
                  tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;padding:1.5rem;">Sin datos para mostrar</td></tr>`;
                  return;
              }

              let html = '';
              data.forEach(row => {
                  if (filtro === 'cuotas') {
                      html += `
                        <tr>
                            <td>${formatDate(row.fecha_evento)}</td>
                            <td>-</td>
                            <td>${row.origen || '-'}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td> <!-- ✅ Costo = arriendo -->
                            <td>-</td>
                            <td>-</td>
                            <td>$${parseInt(row.monto || 0).toLocaleString()}</td>      <!-- ✅ Monto = cuota -->
                            <td>${row.fecha_pago ? formatDate(row.fecha_pago) : '-'}</td>
                            <td>${row.comentario || '-'}</td>
                            <td>
                                <button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#3498DB;"
                                        onclick="pagarCuota(${row.id_cuota})">
                                    💳 Pagar
                                </button>
                            </td>
                        </tr>
                    `;
                  } else {
                      html += `
                          <tr>
                              <td>${formatDate(row.fecha)}</td>
                              <td>${row.hora_inicio?.substring(0,5) || '-'}</td>
                              <td>${row.id_tipoevento || '-'}</td>
                              <td>${row.id_club}</td>
                              <td>${row.id_cancha}</td>
                              <td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td>
                              <td>${row.nombre || '-'}</td>
                              <td>${row.posicion_jugador || '-'}</td>
                              <td>$${parseInt(row.monto || 0).toLocaleString()}</td>
                              <td>${row.fecha_pago ? formatDate(row.fecha_pago) : '-'}</td>
                              <td>${row.comentario || '-'}</td>
                              <td>
                                  <button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#3498DB;">Editar</button>
                              </td>
                          </tr>
                      `;
                  }
              });
              tbody.innerHTML = html;
          })
          .catch(err => {
              console.error('Error al cargar datos:', err);
              document.querySelector('.dynamic-table tbody').innerHTML = 
                  `<tr><td colspan="12" style="text-align:center;color:#ff6b6b;">Error al cargar datos</td></tr>`;
          });
  }

  function formatDate(dateStr) {
      if (!dateStr) return '-';
      const [y, m, d] = dateStr.split('-');
      return `${d}/${m}`;
  }

  // === Inicialización ===
  document.addEventListener('DOMContentLoaded', () => {
      cargarDetalleEventos('inscritos');
      document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.addEventListener('click', () => {
              document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
              btn.classList.add('active');
              cargarDetalleEventos(btn.getAttribute('data-filter'));
          });
      });
      requestNotificationPermission();
  });

  function urlBase64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
      const rawData = atob(base64);
      return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
  }

  // === MODAL COMPARTIR ===
  function abrirModalCompartir() {
      document.getElementById('modalCompartir').style.display = 'flex';
      new QRCode(document.getElementById("qrCodeModal"), {
          text: shareUrl,
          width: 160,
          height: 160,
          colorDark: "#003366",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
      });
  }

  function cerrarModalCompartir() {
      document.getElementById('modalCompartir').style.display = 'none';
  }

  function copiarEnlace() {
      navigator.clipboard.writeText(shareUrl)
          .then(() => alert('¡Enlace copiado!'))
          .catch(err => console.error('Error al copiar:', err));
  }

  document.getElementById('modalCompartir')?.addEventListener('click', function(e) {
      if (e.target === this) cerrarModalCompartir();
  });
  </script>

  <!-- Modal Compartir Club -->
  <div id="modalCompartir" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; color:#071289; padding:2rem; border-radius:14px; max-width:400px; width:90%;">
      <h3 style="margin-top:0;">📤 Compartir tu club</h3>
      <p>Envía este enlace a tus compañeros para que se inscriban fácilmente:</p>
      
      <?php
      $share_url = "https://canchasport.com/pages/registro_socio.php?club=" . htmlspecialchars($club_slug);
      ?>
      
      <div style="margin:1rem 0;">
        <div id="qrCodeModal" style="width:180px; height:180px; margin:0 auto; background:white; padding:8px; border-radius:8px;"></div>
      </div>
      
      <div style="background:#f1f1f1; padding:0.6rem; border-radius:6px; margin:1rem 0; word-break:break-all; font-family:monospace; font-size:0.9rem;">
        <?= htmlspecialchars($share_url) ?>
      </div>
      
      <button onclick="copiarEnlace()" style="background:#071289; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; margin-right:0.5rem;">📋 Copiar</button>
      <button onclick="cerrarModalCompartir()" style="background:#6c757d; color:white; border:none; padding:0.5rem 1rem; border-radius:6px;">Cerrar</button>
    </div>
  </div>
</body>
</html>