<?php
error_log("=== INICIO DASHBOARD_SOCIO.PHP ===");
error_log("GET recibido: " . print_r($_GET, true));
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
        'Secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
// Asegurar que club_id exista en sesión
if (!isset($_SESSION['club_id'])) {
    $_SESSION['club_id'] = null;
}

error_log("SESSION después de start: " . print_r($_SESSION, true));

// === DETECTAR MODO INDIVIDUAL O CLUB ===
$club_slug_from_url = $_GET['id_club'] ?? null;
$modo_individual = ($club_slug_from_url === null || trim($club_slug_from_url) === '');

if (!$modo_individual) {
    if (strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
        error_log("❌ Slug inválido, redirigiendo a index.php");
        header('Location: ../index.php');
        exit;
    }

    // Buscar club
    $stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
    $stmt_club->execute();
    $clubs = $stmt_club->fetchAll();

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
    error_log("✅ Club cargado: " . $club_nombre);
} else {
    error_log("✅ Modo individual detectado");
    $club_id = null;
    $club_nombre = '';
    $club_logo = null;
    $club_slug = null;
}

// === OBTENCIÓN DE ID_SOCIO ===
$id_socio = null;
$socio_actual = null;

if (isset($_SESSION['id_socio'])) {
    $id_socio = $_SESSION['id_socio'];
    
    if ($modo_individual) {
        // Modo individual: no debe tener clubes activos
        $stmt_validate = $pdo->prepare("
            SELECT s.* 
            FROM socios s
            LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
            WHERE s.id_socio = ? AND sc.id_socio IS NULL
        ");
        $stmt_validate->execute([$id_socio]);
    } else {
        // Modo club: debe pertenecer al club actual
        $stmt_validate = $pdo->prepare("
            SELECT s.*
            FROM socios s
            JOIN socio_club sc ON s.id_socio = sc.id_socio
            WHERE s.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'
        ");
        $stmt_validate->execute([$id_socio, $club_id]);
    }
    
    $socio_actual = $stmt_validate->fetch();
    if (!$socio_actual) {
        $id_socio = null;
        $socio_actual = null;
    }
}

if (!$id_socio) {
    $user_email = null;
    if (isset($_SESSION['google_email'])) {
        $user_email = $_SESSION['google_email'];
    } elseif (isset($_SESSION['user_email'])) {
        $user_email = $_SESSION['user_email'];
    }
    
    if ($user_email) {
        if ($modo_individual) {
            $stmt_socio = $pdo->prepare("
                SELECT s.id_socio 
                FROM socios s
                LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
                WHERE s.email = ? AND sc.id_socio IS NULL
            ");
            $stmt_socio->execute([$user_email]);
        } else {
            $stmt_socio = $pdo->prepare("
                SELECT s.id_socio
                FROM socios s
                JOIN socio_club sc ON s.id_socio = sc.id_socio
                WHERE s.email = ? AND sc.id_club = ? AND sc.estado = 'activo'
            ");
            $stmt_socio->execute([$user_email, $club_id]);
        }
        
        $socio_data = $stmt_socio->fetch();
        if ($socio_data) {
            $id_socio = $socio_data['id_socio'];
            $_SESSION['id_socio'] = $id_socio;
        } else {
            if ($modo_individual) {
                header('Location: completar_perfil.php?modo=individual');
            } else {
                header('Location: completar_perfil.php?id=' . $club_slug);
            }
            exit;
        }
    } else {
        header('Location: ../index.php');
        exit;
    }
} else {
    $_SESSION['id_socio'] = $id_socio;
}

// Asegurar socio_actual
if (!$socio_actual) {
    if ($modo_individual) {
        $stmt_fallback = $pdo->prepare("
            SELECT s.*
            FROM socios s
            LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
            WHERE s.id_socio = ? AND sc.id_socio IS NULL
            LIMIT 1
        ");
        $stmt_fallback->execute([$_SESSION['id_socio']]);
    } else {
        $stmt_fallback = $pdo->prepare("
            SELECT s.*
            FROM socios s
            JOIN socio_club sc ON s.id_socio = sc.id_socio
            WHERE s.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'
            LIMIT 1
        ");
        $stmt_fallback->execute([$_SESSION['id_socio'], $club_id]);
    }
    $socio_actual = $stmt_fallback->fetch() ?: ['datos_completos' => 0, 'nombre' => 'Usuario', 'es_responsable' => 0];
}

$es_responsable = !empty($socio_actual) && isset($socio_actual['es_responsable']) && $socio_actual['es_responsable'] == 1;

// === OBTENER TODOS LOS CLUBES DEL SOCIO ===
$clubes_del_socio = [];
if (isset($_SESSION['id_socio'])) {
    $stmt_clubes = $pdo->prepare("
        SELECT 
            c.id_club,
            c.nombre AS club_nombre,
            c.email_responsable
        FROM socio_club sc
        JOIN clubs c ON sc.id_club = c.id_club
        WHERE sc.id_socio = ? AND sc.estado = 'activo'
        ORDER BY c.nombre ASC
    ");
    $stmt_clubes->execute([$_SESSION['id_socio']]);
    $clubes_del_socio = $stmt_clubes->fetchAll();
}

// Guardar en sesión
if (!$modo_individual) {
    $_SESSION['club_id'] = $club_id;
    $_SESSION['current_club'] = $club_slug;
}

// === DETECTAR TORNEOS AMERICANOS ===
$torneos_americanos = [];
$stmt_torneos = $pdo->prepare("
    SELECT 
        t.id_torneo,
        t.nombre AS torneo_nombre,
        t.fecha_inicio,
        pt.id_pareja
    FROM parejas_torneo pt
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    WHERE (pt.id_socio_1 = ? OR pt.id_socio_2 = ?)
      AND t.estado IN ('abierto', 'en_progreso')
    ORDER BY t.fecha_inicio ASC
");
$stmt_torneos->execute([$_SESSION['id_socio'], $_SESSION['id_socio']]);
$torneos_americanos = $stmt_torneos->fetchAll(PDO::FETCH_ASSOC);
$tiene_torneo = !empty($torneos_americanos);
$torneo_actual = $torneos_americanos[0] ?? null;

// === PRÓXIMO EVENTO (solo para club) ===
$proximo_evento = null;
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

// === DEUDAS PENDIENTES (solo del club activo) ===
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
    -- Relación con socio_club para filtrar por club activo --
    INNER JOIN socio_club sc ON c.id_socio = sc.id_socio
    WHERE 
        c.id_socio = ? 
        AND c.estado = 'pendiente'
        AND sc.id_club = ?
        AND sc.estado = 'activo'
    ORDER BY c.fecha_vencimiento ASC
    LIMIT 1
");
$stmt_deudas->execute([$_SESSION['id_socio'], $_SESSION['club_id']]);
$deuda_mas_vigente = $stmt_deudas->fetch();

$stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM cuotas WHERE id_socio = ? AND estado = 'pendiente'");
$stmt_count->execute([$_SESSION['id_socio']]);
$total_deudas = (int)$stmt_count->fetchColumn();

// === ÚLTIMO PARTIDO (solo para club) ===
$ultimo_partido = null;
if (!$modo_individual && isset($_SESSION['club_id'])) {
    $stmt_last = $pdo->prepare("
        SELECT
            r.id_reserva,
            r.fecha,
            r.hora_inicio,
            r.resultado_grabado
        FROM reservas r
        WHERE r.id_club = ? AND r.fecha < CURDATE()
        ORDER BY r.fecha DESC, r.hora_inicio DESC
        LIMIT 1
    ");
    $stmt_last->execute([$_SESSION['club_id']]);
    $ultimo_partido = $stmt_last->fetch();
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
.dashboard-upper {
display: flex;
height: auto;
gap: 2rem;
margin-bottom: 1.5rem;
}
.upper-right {
flex: 0 0 15%;
display: flex;
flex-direction: column;
gap: 1rem;
overflow-y: auto;
margin-right: 20px;
}
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
.dashboard-lower {
background: rgba(255, 255, 255, 0.15);
backdrop-filter: blur(10px);
padding: 1.5rem;
border-radius: 14px;
box-shadow: 0 4px 12px rgba(0,0,0,0.2);
overflow-y: auto;
max-height: 600px;
margin: 0 auto 2rem auto;
max-width: 1400px;
}
.dynamic-table-container {
max-height: 500px;
overflow-y: auto;
}
.dashboard-lower h3 {
margin-bottom: 1rem;
text-align: left;
font-size: 1.3rem;
}
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
@media (max-width: 768px) {
.dashboard-upper {
flex-direction: column;
height: auto;
margin-bottom: 1rem;
}
.upper-left {
flex: 1;
grid-template-columns: repeat(2, 1fr);
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
.ficha-buttons {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 0.5rem;
margin-top: 1rem;
}
.ficha-buttons .btn-action {
padding: 0.4rem;
font-size: 0.8rem;
min-width: auto;
width: 100%;
box-sizing: border-box;
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
/* Columna Acción centrada */
.dynamic-table td:nth-child(12),
.dynamic-table th:nth-child(12) {
vertical-align: middle;
text-align: center;
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
<h2><?= htmlspecialchars($socio_actual['alias'] ?? $socio_actual['nombre'] ?? 'Usuario') ?> - <?= htmlspecialchars($club_nombre) ?></h2>
<p>Tu Cancha está lista</p>
</div>
</div>
<div class="header-right">
<a href="../index.php" onclick="limpiarSesion()" class="logout-header">Salir</a>
</div>
</div>

<!-- MITAD SUPERIOR -->
<div class="dashboard-upper">
<!-- Sub sección izquierda -->
<div class="upper-left">

<?php if ($modo_individual && !empty($torneos_americanos)): ?>
  <!-- Próximo Partido → Fixture -->
  <div class="fichas-dashboard">
  <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3>🎾 Fixture – <?= htmlspecialchars($torneo_actual['torneo_nombre']) ?></h3>
    <div class="stat-card-content" id="fixtureTorneo">
      Cargando fixture...
    </div>
  </div>

  <!-- Último Partido → Resultados -->
  <div class="stat-card">
    <h3>📊 Resultados – <?= htmlspecialchars($torneo_actual['torneo_nombre']) ?></h3>
    <div class="stat-card-content" id="resultadosTorneo">
      Cargando resultados...
    </div>
  </div>

  <!-- Noticias → Posiciones -->
  <div class="stat-card">
    <h3>🏆 Posiciones – <?= htmlspecialchars($torneo_actual['torneo_nombre']) ?></h3>
    <div class="stat-card-content" id="posicionesTorneo">
      Cargando posiciones...
    </div>
  </div>
  </div>

<?php else: ?>
  <div class="fichas-dashboard">
  <!-- Próximo Partido -->
  <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
    <h3 style="color: white;">Próximo Partido</h3>
    <div class="stat-card-content">
    <?php if ($proximo_evento): ?>
      <?php
      $id_reserva = $proximo_evento['id_reserva'];
      $players = (int)$proximo_evento['players'];
      $monto_total = (float)$proximo_evento['monto_total'];
      $deporte = $proximo_evento['id_deporte'];
      $fecha_evento = new DateTime($proximo_evento['fecha'] . ' ' . $proximo_evento['hora_inicio']);
      $ahora = new DateTime();
      $diferencia = $ahora->diff($fecha_evento);
      $horas_restantes = ($diferencia->days * 24) + $diferencia->h;
      $fecha_formateada = $fecha_evento->format('d-m');
      $hora_formateada = $fecha_evento->format('H:i');
      // Calcular el LUNES DE LA SEMANA DEL EVENTO a las 09:00
      $lunes_semana_evento = clone $fecha_evento;
      $lunes_semana_evento->modify('this week monday'); // Lunes de la semana del evento
      $lunes_semana_evento->setTime(9, 0, 0);
      // ¿Ya pasó el lunes 09:00?
      $botones_activos = ($ahora >= $lunes_semana_evento);
      // ¿Ya pasó el lunes 09:00?
      $despues_del_lunes_09 = ($ahora >= $lunes_semana_evento);
      ?>
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
      // Cupos llenos
      $cupos_llenos = ((int)$proximo_evento['inscritos_actuales'] >= (int)$proximo_evento['jugadores_esperados']);
      ?>
      <p><strong><?= $fecha_formateada ?> a las <?= $hora_formateada ?></strong></p>
      <div style="margin:0.5rem 0;font-size:0.85rem;text-align:left;">
      <div style="margin:0.3rem 0;"><strong>💰 Arriendo</strong> $<?= number_format((int)$monto_total, 0, ',', '.') ?>
      <?php if ($proximo_evento['monto_recaudacion']): ?>
      <div style="margin:0.3rem 0; font-size:0.8rem; color:#FFD700;">
      <strong>💰 Cuota:</strong> $<?= number_format((int)$proximo_evento['monto_recaudacion'], 0, ',', '.') ?>
      <br><strong>👥 Cupos:</strong> <?= (int)$proximo_evento['jugadores_esperados'] ?> • <strong>👥 Anotados</strong> <?= (int)$proximo_evento['inscritos_actuales'] ?></div>
      </div>
      <?php endif; ?>
      </div>
      <?php if ($despues_del_lunes_09): ?>
      <!-- Botones de inscripción (activos desde lunes 09:00)  -->
      <?php if ($ya_inscrito): ?>
      <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;"
      onclick="anotarseEvento(<?= $id_reserva ?>, 'reserva', '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
      Bajarse
      </button>
      <?php else: ?>
      <?php if ($cupos_llenos): ?>
      <!-- Cupos llenos -->
      <p style="color:#FF6B6B;margin-top:1rem;font-weight:bold;">
      ❌ No se aceptan más inscripciones hasta que uno de los anotados decida "Bajarse".
      </p>
      <?php else: ?>
      <button class="btn-action" style="background:#4ECDC4;color:#071289;padding:0.4rem;font-size:0.8rem;margin-top:0.5rem;width:100%;"
      onclick="anotarseEvento(<?= $id_reserva ?>, 'reserva', '<?= $deporte ?>', <?= $players ?>, <?= $monto_total ?>)">
      Anotarse
      </button>
      <button class="btn-action" style="background:#4ECDC4;color:#071289;padding:0.4rem;font-size:0.8rem;margin-top:0.3rem;width:100%;"
      onclick="anotarseConCerveza(true)">
      Anotarse + llevo 🍺🍺
      </button>
      <?php endif; ?>
      <button class="btn-action" style="background:#FF6B6B;padding:0.4rem;font-size:0.8rem;"
      onclick="pasoEvento(<?= $id_reserva ?>)">
      <?= $ya_inscrito ? 'Paso' : 'Paso' ?>
      </button>
      <?php endif; ?>
      <!-- Botón IA si aplica -->
      <?php if ($es_responsable && (int)($proximo_evento['inscritos_actuales'] ?? 0) >= 10): ?>
      <button class="btn-action" style="background:#F1C40F;padding:0.4rem;font-size:0.8rem;margin-top:0.5rem;width:100%;"
      onclick="armarEquiposIA(<?= $id_reserva ?>)">
      🤖 Armar Equipos IA
      </button>
      <?php endif; ?>
      <?php else: ?>
      <p style="color:#FFD700;margin-top:1rem;font-size:0.85rem;">
      ⏰ Los botones de inscripción se activarán el lunes <?= $lunes_semana_evento->format('d/m') ?> a las 09:00 hrs
      </p>
      <?php endif; ?>
      <?php else: ?>
      <p style="margin-top:2rem;">Próximamente disponible</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Deudas Pendientes -->
  <?php if ($deuda_mas_vigente): ?>
  <div class="stat-card" style="background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); color: #071289;">
    <h3>💰 Deuda Pendiente</h3>
    <div style="margin:0.8rem 0;padding:0.6rem;background:rgba(255,255,255,0.7);border-radius:8px;font-size:0.85rem;">
      <strong><?= htmlspecialchars($deuda_mas_vigente['detalle_origen']) ?></strong><br>
      <strong>📅</strong> <?= date('d/m', strtotime($deuda_mas_vigente['fecha_evento'])) ?> –
      <strong>💲</strong> $<?= number_format($deuda_mas_vigente['monto'], 0, ',', '.') ?><br>
      <button class="btn-action" style="background:#E74C3C;margin-top:0.5rem;font-size:0.8rem;color:white;" onclick="pagarCuota(<?= $deuda_mas_vigente['id_cuota'] ?>)">Pagar ahora</button>
    </div>
    <?php if ($total_deudas > 1): ?>
    <p style="font-size:0.8rem; margin-top:0.8rem; opacity:0.8;">⚠️ Existen más cuotas pendientes, las puedes revisar en <strong>Detalle Eventos → Cuotas</strong></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Último Partido -->
  <div class="stat-card">
    <h3>📊 Último Partido</h3>
    <div class="stat-card-content">
    <?php
    // Obtener último partido REAL (ya jugado)
    $stmt_last = $pdo->prepare("
        SELECT
            r.id_reserva,
            r.fecha,
            r.hora_inicio,
            r.goles_rojos,
            r.goles_blancos,
            r.jugador_experto
        FROM reservas r
        WHERE r.id_club = ? AND r.fecha < CURDATE()
        ORDER BY r.fecha DESC, r.hora_inicio DESC
        LIMIT 1
    ");
    $stmt_last->execute([$_SESSION['club_id']]);
    $ultimo_partido = $stmt_last->fetch();
    if ($ultimo_partido): ?>
      <p><strong>Fecha:</strong> <?= htmlspecialchars($ultimo_partido['fecha']) ?></p>
      <?php if (!is_null($ultimo_partido['goles_rojos'])): ?>
      <!-- Resultado ya grabado -->
      <div style="margin-top:1rem;">
        <p style="color: #071289;font-weight:bold;">Resultado final</p>
        <p style="color: #98180aff;font-weight:bold;"><strong>Rojos:</strong> <?= (int)$ultimo_partido['goles_rojos'] ?></p>
        <p><strong>Blancos: <?= (int)$ultimo_partido['goles_blancos'] ?></strong></p>
        <?php if (!empty($ultimo_partido['jugador_experto'])): ?>
        <p><strong>Jugador Xperto Baltica:</strong>
        <p style="color: #071289;font-weight:bold;">
        <?php
        $stmt_jug = $pdo->prepare("SELECT alias FROM socios WHERE id_socio = ?");
        $stmt_jug->execute([$ultimo_partido['jugador_experto']]);
        echo htmlspecialchars($stmt_jug->fetchColumn() ?: '—');
        ?>
        </p>
        <?php endif; ?>
      </div>
      <?php elseif ($es_responsable): ?>
      <!-- Formulario para registrar resultado -->
      <form id="postPartidoForm" style="margin-top:1rem;">
        <input type="hidden" name="id_reserva" value="<?= $ultimo_partido['id_reserva'] ?>">
        <div style="display:flex;gap:1rem;margin:0.5rem 0;">
          <div style="flex:1;">
            <label style="font-weight:bold;">Rojos:</label>
            <input type="number" name="goles_rojos" placeholder="0" min="0"
                   value="0"
                   style="width:100%;padding:0.4rem;border-radius:4px;border:1px solid #ccc;">
          </div>
          <div style="flex:1;">
            <label style="font-weight:bold;">Blancos:</label>
            <input type="number" name="goles_blancos" placeholder="0" min="0"
                   value="0"
                   style="width:100%;padding:0.4rem;border-radius:4px;border:1px solid #ccc;">
          </div>
        </div>
        <label style="display:block;margin:0.5rem 0;font-weight:bold;">Jugador Xperto Baltica:</label>
        <select name="jugador_experto" style="width:100%;padding:0.4rem;border-radius:4px;border:1px solid #ccc;">
          <option value="">Seleccionar...</option>
          <?php
          $stmt_inscritos = $pdo->prepare("
              SELECT s.id_socio, s.alias
              FROM inscritos i
              JOIN socios s ON i.id_socio = s.id_socio
              WHERE i.id_evento = ? AND i.tipo_actividad = 'reserva'
              ORDER BY s.alias
          ");
          $stmt_inscritos->execute([$ultimo_partido['id_reserva']]);
          while ($jugador = $stmt_inscritos->fetch()):
          ?>
            <option value="<?= $jugador['id_socio'] ?>"><?= htmlspecialchars($jugador['alias']) ?></option>
          <?php endwhile; ?>
        </select>
        <button type="submit" class="btn-action" style="margin-top:0.5rem;background:#2ECC71;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">
          Grabar Resultado
        </button>
      </form>
      <?php else: ?>
      <p style="margin-top:1rem;">Resultado aún no registrado</p>
      <?php endif; ?>
    <?php else: ?>
      <p style="margin-top:2rem;">Sin partidos anteriores</p>
    <?php endif; ?>
    </div>
  </div>

  <!-- Noticias o Resultados Torneo -->
  <div class="stat-card">
    <h3>
      <?php if ($tiene_torneo): ?>
        🏆 Resultados – <?= htmlspecialchars($torneo_actual['torneo_nombre']) ?>
      <?php else: ?>
        Noticias
      <?php endif; ?>
    </h3>
    <div class="stat-card-content">
      <?php if ($tiene_torneo): ?>
        <div id="noticiasTorneo">Cargando resultados...</div>
      <?php else: ?>
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
      <?php endif; ?>
    </div>
  </div>
  </div>
<?php endif; ?>
</div>

<!-- Sub sección derecha -->
<div class="upper-right">
<?php if (!empty($clubes_del_socio) && count($clubes_del_socio) > 1): ?>
    <div><strong>🏆 Mis Clubes</strong></div>
    <?php foreach ($clubes_del_socio as $c): ?>
      <?php
      $slug_actual = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
      // No mostrar el club actual
      if (!$modo_individual && $club_id == $c['id_club']) continue;
      ?>
      <button class="btn-action" onclick="window.location.href='dashboard_socio.php?id_club=<?= $slug_actual ?>'">
        <?= htmlspecialchars($c['club_nombre']) ?>
      </button>
    <?php endforeach; ?>
<?php endif; ?>


<?php if (!($modo_individual && !empty($torneos_americanos))): ?>
    <?php if ($es_responsable): ?>
        <button class="btn-action" onclick="window.location.href='reservar_cancha.php'">Reservar Cancha</button>
        <button class="btn-action" onclick="window.location.href='perfil_club.php'">Actualizar perfil club</button>
    <?php endif; ?>
    <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= htmlspecialchars($club_slug) ?>'">Eventos</button>
    <button class="btn-action" onclick="abrirModalCompartir()">Compartir club</button>
    <button class="btn-action" onclick="window.location.href='mantenedor_socios.php'">Actualizar perfil socio</button>
    <!-- Botón "+ Otro Club" -->
    <button class="btn-action" style="background:#4CAF50;" onclick="agregarOtroClub()">➕ Otro Club</button>
<?php endif; ?>

</div>
</div>

<!-- CSS RESPONSIVE -->
<style>
@media (min-width: 1024px) {
.dashboard-upper { display: flex; gap: 1.8rem; margin-top: 1.2rem; }
.upper-left { flex: 4; max-width: none; margin-left: 20px; }
.upper-right { flex: 1; display: flex; flex-direction: column; gap: 0.8rem; margin-right: 20px; }
}
.fichas-dashboard {
display: grid;
gap: 1.4rem;
width: 100%;
grid-template-columns: 1fr;
}
@media (min-width: 768px) and (max-width: 1023px) {
.fichas-dashboard { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
.fichas-dashboard { grid-template-columns: repeat(4, 1fr); }
}
.fichas-dashboard > .stat-card { width: 100%; min-width: 0; }
.upper-left { display: block; }
</style>

<!-- MITAD INFERIOR -->
<div class="dashboard-lower" style="margin-top: 8rem;">
<h3>Detalle Eventos</h3>
<!-- Filtros -->
<button class="filter-btn" data-filter="inscritos">Inscritos Próximo futbolito</button>
<button class="filter-btn" data-filter="torneos">Americanos</button>
<?php if (!($modo_individual && !empty($torneos_americanos))): ?>
  <button class="filter-btn" data-filter="equipos">Equipos IA</button>
<?php endif; ?>
<?php if (!$modo_individual): ?>
  <button class="filter-btn" data-filter="reservas">Reservas</button>
  <button class="filter-btn" data-filter="cuotas">Cuotas</button>
  <button class="filter-btn" data-filter="eventos">Eventos</button>
  <button class="filter-btn" data-filter="socios">Socios</button>
<?php endif; ?>

<!-- Columnas Tabla Datos -->
<div class="dynamic-table-container">
<table class="dynamic-table">
<thead>
<tr>
<th>Fecha</th>
<th>Hora</th>
<th>Tipo</th>
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
<tbody id="tablaContenido">
<tr>
<td colspan="12" style="text-align: center; padding: 2rem;">Selecciona un filtro para ver los datos</td>
</tr>
</tbody>
</table>
</div>
</div>

<!-- SCRIPTS COMPLETOS -->
<script>
// === FUNCIONES DE NOTIFICACIÓN ===
function mostrarNotificacion(mensaje, tipo = 'info') {
const toast = document.getElementById('toast');
const msg = document.getElementById('toast-message');
if (!toast || !msg) return;
msg.textContent = mensaje;
toast.className = 'toast ' + (tipo === 'exito' ? 'success' : tipo === 'error' ? 'error' : 'info');
toast.style.display = 'flex';
void toast.offsetWidth;
toast.classList.add('show');
setTimeout(() => {
toast.classList.remove('show');
setTimeout(() => toast.style.display = 'none', 400);
}, 5000);
}
// === VALIDAR EDAD ===
function validarEdad(fechaNac) {
if (!fechaNac) return true;
const hoy = new Date();
const nacimiento = new Date(fechaNac);
let edad = hoy.getFullYear() - nacimiento.getFullYear();
const mes = hoy.getMonth() - nacimiento.getMonth();
if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
return edad >= 14;
}
// === MANEJO DEL FORMULARIO ===
document.getElementById('registroForm')?.addEventListener('submit', async (e) => {
e.preventDefault();
const fechaNacInput = document.getElementById('fecha_nac');
if (fechaNacInput && !validarEdad(fechaNacInput.value)) {
mostrarNotificacion('❌ La edad mínima es 14 años', 'error');
return;
}
const password = document.getElementById('password')?.value;
const passwordConfirm = document.getElementById('password_confirm')?.value;
if (password !== passwordConfirm) {
mostrarNotificacion('❌ Las contraseñas no coinciden', 'error');
return;
}
if (password && password.length < 6) {
mostrarNotificacion('❌ Contraseña debe tener al menos 6 caracteres', 'error');
return;
}
const formData = new FormData(e.target);
const btn = e.submitter;
const originalText = btn.innerHTML;
btn.innerHTML = 'Enviando...';
btn.disabled = true;
try {
const response = await fetch('../api/enviar_codigo_socio.php', { method: 'POST', body: formData });
const textResponse = await response.text();
let data;
try { data = JSON.parse(textResponse); } catch (e) { throw new Error('Error interno del servidor'); }
if (data.success) {
mostrarNotificacion('✅ Código enviado a tu correo', 'exito');
setTimeout(() => {
if (data.club_slug && data.club_slug.trim() !== '') {
window.location.href = 'verificar_socio.php?club=' + encodeURIComponent(data.club_slug);
} else {
window.location.href = 'verificar_socio.php?id_socio=' + encodeURIComponent(data.id_socio);
}
}, 2000);
} else {
mostrarNotificacion('❌ ' + data.message, 'error');
btn.innerHTML = originalText;
btn.disabled = false;
}
} catch (error) {
console.error('Error:', error);
mostrarNotificacion('❌ Error al enviar el código', 'error');
btn.innerHTML = originalText;
btn.disabled = false;
}
});
// === CARGAR PUESTOS POR DEPORTE ===
function cargarPuestosPorDeporte(deporte) {
const url = deporte
? '../api/get_puestos.php?deporte=' + encodeURIComponent(deporte)
: '../api/get_puestos.php';
fetch(url)
.then(r => r.json())
.then(puestos => {
const select = document.getElementById('id_puesto');
if (!select) return;
select.innerHTML = '<option value="">Seleccionar</option>';
puestos.forEach(p => {
const opt = document.createElement('option');
opt.value = p.id_puesto;
opt.textContent = p.puesto;
select.appendChild(opt);
});
})
.catch(error => console.error('Error al cargar puestos:', error));
}
// === TOAST PERSONALIZADO ===
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
}, 5000);
}
// === ANIMACIONES CSS ===
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
// === REGIONES DINÁMICAS ===
let datosChile = {};
fetch('../api/get_regiones.php')
.then(response => response.json())
.then(data => { datosChile = data; })
.catch(error => console.error('Error al cargar regiones:', error));
function actualizarCiudades() {
const region = document.getElementById('region');
const ciudadSelect = document.getElementById('ciudad');
const comunaSelect = document.getElementById('comuna');
if (!region || !ciudadSelect || !comunaSelect) return;
ciudadSelect.innerHTML = '<option value="">Seleccionar ciudad</option>';
comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
ciudadSelect.disabled = !region.value;
comunaSelect.disabled = true;
if (region.value && datosChile[region.value]) {
Object.entries(datosChile[region.value].ciudades).forEach(([codigo, nombre]) => {
const option = document.createElement('option');
option.value = codigo;
option.textContent = nombre;
ciudadSelect.appendChild(option);
});
ciudadSelect.disabled = false;
}
}
document.getElementById('region')?.addEventListener('change', actualizarCiudades);
document.getElementById('ciudad')?.addEventListener('change', function() {
const region = document.getElementById('region')?.value;
const ciudad = this.value;
const comunaSelect = document.getElementById('comuna');
if (!comunaSelect) return;
comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
comunaSelect.disabled = !(region && ciudad && datosChile[region]?.comunas?.[ciudad]);
if (region && ciudad && datosChile[region]?.comunas?.[ciudad]) {
datosChile[region].comunas[ciudad].forEach(comuna => {
const option = document.createElement('option');
option.value = comuna.toLowerCase().replace(/\s+/g, '_');
option.textContent = comuna;
comunaSelect.appendChild(option);
});
comunaSelect.disabled = false;
}
});
// === SERVICE WORKER ===
if ('serviceWorker' in navigator) {
window.addEventListener('load', () => {
navigator.serviceWorker.register('/sw.js')
.then(reg => console.log('SW registrado:', reg.scope))
.catch(err => console.log('Error SW:', err));
});
}
// === PUSH NOTIFICATIONS ===
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
const vapidKey = '<?= VAPID_PUBLIC_KEY ?>';
if (!vapidKey || vapidKey === 'VAPID_PUBLIC_KEY') {
console.warn('VAPID key not configured');
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
id_socio: <?= (int)($_SESSION['id_socio'] ?? 0) ?>,
subscription: subscription
})
});
});
}
function urlBase64ToUint8Array(base64String) {
const padding = '='.repeat((4 - base64String.length % 4) % 4);
const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
const rawData = atob(base64);
return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
}
// === LIMPIAR SESIÓN ===
function limpiarSesion() {
localStorage.removeItem('cancha_session');
localStorage.removeItem('cancha_club');
}
// === MODAL COMPARTIR ===
function abrirModalCompartir() {
const modal = document.getElementById('modalCompartir');
if (modal) modal.style.display = 'flex';
}
function cerrarModalCompartir() {
const modal = document.getElementById('modalCompartir');
if (modal) modal.style.display = 'none';
}
function copiarEnlace() {
const url = '<?= json_encode("https://canchasport.com/pages/registro_socio.php?club=" . ($club_slug ?? '')) ?>';
navigator.clipboard.writeText(url)
.then(() => alert('✅ Enlace copiado!'))
.catch(err => console.error('Error al copiar:', err));
}
// === CERRAR MODAL AL HACER CLICK FUERA ===
const modalCompartir = document.getElementById('modalCompartir');
if (modalCompartir) {
modalCompartir.addEventListener('click', function(e) {
if (e.target === this) cerrarModalCompartir();
});
}
// === ACCIONES DE EVENTOS ===
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
function pasoEvento(idReserva) {
const card = event.target.closest('.stat-card');
if (card) {
const pasoBtn = event.target;
pasoBtn.textContent = 'Paso esta semana';
pasoBtn.disabled = true;
pasoBtn.style.opacity = '0.7';
}
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
// === ARMAR EQUIPOS IA ===
function armarEquiposIA(idReserva) {
console.log('🤖 [Frontend] Iniciando armado de equipos para reserva:', idReserva);
fetch('../api/armar_equipos_ia.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: new URLSearchParams({id_reserva: idReserva})
})
.then(response => {
console.log('ℹ️ [Frontend] Respuesta recibida, status:', response.status);
if (!response.ok) {
throw new Error('Respuesta no OK: ' + response.status);
}
return response.json();
})
.then(data => {
console.log('🤖 [Frontend] Datos recibidos:', data);
if (data.success) {
console.log('🤖 [Frontend] Llamando a mostrarModalEquipos()');
mostrarModalEquipos(data.equipos);
} else {
console.error('🤖 [Frontend] Error desde API:', data.message);
alert('Error: ' + data.message);
}
})
.catch(err => {
console.error('🤖 [Frontend] Error en armado de equipos:', err);
alert('Error al armar equipos: ' + err.message);
});
}
// === EDITAR PERFIL SOCIO ===
function editarPerfilSocio(idSocio) {
window.location.href = 'mantenedor_socios.php?id_socio=' + idSocio;
}
// === ELIMINAR SOCIO ===
function eliminarSocio(idSocio) {
if (!confirm('¿Estás seguro de eliminar a este socio? Esta acción es irreversible.')) return;
fetch('../api/eliminar_socio.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: new URLSearchParams({id_socio: idSocio})
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast('✅ Socio eliminado');
setTimeout(() => location.reload(), 1500);
} else {
mostrarToast('❌ ' + data.message);
}
});
}
// === BAJARSE DE EVENTO ===
function bajarseEvento(idReserva, idSocioObjetivo = null) {
if (!confirm('¿Estás seguro de darte de baja del evento?')) return;
const params = new URLSearchParams({
action: 'bajarse',
id_actividad: idReserva,
tipo_actividad: 'reserva'
});
if (idSocioObjetivo) {
params.append('id_socio_objetivo', idSocioObjetivo);
}
fetch('../api/gestion_eventos.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: params
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast(data.message);
setTimeout(() => location.reload(), 1500);
} else {
mostrarToast('❌ ' + data.message);
}
})
.catch(err => {
console.error('Error:', err);
mostrarToast('❌ Error al procesar la baja');
});
}
// === REVISAR PAGO ===
function revisarPago(idCuota) {
fetch('../api/revisar_pago.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: new URLSearchParams({id_cuota: idCuota})
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast('✅ Cuota en revisión');
setTimeout(() => cargarTabla('cuotas'), 1000);
} else {
mostrarToast('❌ ' + data.message);
}
});
}
// === VALIDAR PAGO ===
function validarPago(idCuota) {
fetch('../api/validar_pago.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: new URLSearchParams({id_cuota: idCuota})
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast('✅ Pago validado');
setTimeout(() => cargarTabla('cuotas'), 1000);
} else {
mostrarToast('❌ ' + data.message);
}
});
}
// === ASIGNAR CERVEZA ===
function asignarCerveza(idInscrito, estado) {
fetch('../api/asignar_cerveza.php', {
method: 'POST',
headers: {'Content-Type': 'application/x-www-form-urlencoded'},
body: new URLSearchParams({
id_inscrito: idInscrito,
lleva_cerveza: estado
})
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast(estado ? '✅ ¡Llevará cervezas!' : '✅ Asignación removida');
setTimeout(() => location.reload(), 1000);
} else {
mostrarToast('❌ ' + data.message);
}
});
}
// === ANOTARSE CON CERVEZA ===
function anotarseConCerveza(llevaCerveza) {
document.getElementById('cervezaMenu').style.display = 'none';
const formData = new FormData();
formData.append('action', 'anotarse');
formData.append('id_actividad', <?= $id_reserva ?? 0 ?>);
formData.append('tipo_actividad', 'reserva');
formData.append('deporte', '<?= $deporte ?? '' ?>');
formData.append('players_max', <?= $players ?? 0 ?>);
formData.append('monto_total', <?= $monto_total ?? 0 ?>);
formData.append('lleva_cerveza', llevaCerveza ? '1' : '0');
fetch('../api/gestion_eventos.php', { method: 'POST', body: formData })
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast(data.message);
setTimeout(() => location.reload(), 1500);
} else {
mostrarToast('❌ ' + data.message);
}
});
}
// === TOGGLE CERVEZA MENU ===
function toggleCervezaMenu(e) {
e.stopPropagation();
const menu = document.getElementById('cervezaMenu');
menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', () => {
const menu = document.getElementById('cervezaMenu');
if (menu) menu.style.display = 'none';
});
// === GUARDAR RESULTADO ÚLTIMO PARTIDO ===
document.getElementById('postPartidoForm')?.addEventListener('submit', async (e) => {
e.preventDefault();
const formData = new FormData(e.target);
const golesRojos = formData.get('goles_rojos');
const golesBlancos = formData.get('goles_blancos');
const jugadorExperto = formData.get('jugador_experto');
if (!golesRojos && !golesBlancos) {
alert('Ingresa al menos un marcador');
return;
}
try {
const response = await fetch('../api/guardar_resultado_partido.php', {
method: 'POST',
body: formData
});
const data = await response.json();
if (data.success) {
mostrarToast('✅ Resultado guardado');
setTimeout(() => location.reload(), 1500);
} else {
mostrarToast('❌ ' + data.message);
}
} catch (error) {
mostrarToast('❌ Error al guardar resultado');
console.error('Error:', error);
}
});
// === CARGAR DETALLE EVENTOS ===
function formatDate(dateStr) {
if (!dateStr) return '-';
const [y, m, d] = dateStr.split('-');
return `${d}/${m}`;
}
// === MODAL EQUIPOS IA ===
function mostrarModalEquipos(equipos) {
const rojosEl = document.getElementById('equipoRojos');
const blancosEl = document.getElementById('equipoBlancos');
const modal = document.getElementById('modalEquipos');
if (!rojosEl || !blancosEl || !modal) {
console.error('❌ Elementos del modal no encontrados');
alert('Error: Modal no disponible');
return;
}
rojosEl.innerHTML = '';
blancosEl.innerHTML = '';
equipos.rojos.forEach(j => {
const li = document.createElement('li');
li.textContent = j.alias;
li.dataset.idSocio = j.id_socio;
li.style.padding = '0.3rem';
li.style.cursor = 'pointer';
li.onclick = () => seleccionarJugador(li, 'rojos');
rojosEl.appendChild(li);
});
equipos.blancos.forEach(j => {
const li = document.createElement('li');
li.textContent = j.alias;
li.dataset.idSocio = j.id_socio;
li.style.padding = '0.3rem';
li.style.cursor = 'pointer';
li.onclick = () => seleccionarJugador(li, 'blancos');
blancosEl.appendChild(li);
});
modal.style.display = 'flex';
}
let jugadorSeleccionado = null;
let equipoOrigen = null;
function seleccionarJugador(elemento, equipo) {
document.querySelectorAll('#equipoRojos li, #equipoBlancos li').forEach(el => {
el.style.border = '1px solid transparent';
el.style.backgroundColor = '';
});
elemento.style.border = '2px solid #3498DB';
elemento.style.backgroundColor = '#d6eaf8';
jugadorSeleccionado = elemento.dataset.idSocio;
equipoOrigen = equipo;
}
function moverJugador(de, a) {
if (!jugadorSeleccionado) {
alert('Selecciona un jugador primero');
return;
}
const origen = document.getElementById(`equipo${de.charAt(0).toUpperCase() + de.slice(1)}`);
const destino = document.getElementById(`equipo${a.charAt(0).toUpperCase() + a.slice(1)}`);
if (destino.children.length >= 7) {
alert('El equipo ya tiene 7 jugadores');
return;
}
let elementoSeleccionado = null;
Array.from(origen.children).forEach(li => {
if (li.dataset.idSocio == jugadorSeleccionado) {
elementoSeleccionado = li;
}
});
if (elementoSeleccionado) {
destino.appendChild(elementoSeleccionado);
jugadorSeleccionado = null;
equipoOrigen = null;
elementoSeleccionado.style.border = '1px solid transparent';
elementoSeleccionado.style.backgroundColor = '';
}
}
function guardarEquipos() {
const rojos = Array.from(document.getElementById('equipoRojos').children).map(li =>
li.dataset.idSocio
);
const blancos = Array.from(document.getElementById('equipoBlancos').children).map(li =>
li.dataset.idSocio
);
if (rojos.length === 0 || blancos.length === 0) {
alert('Ambos equipos deben tener al menos un jugador');
return;
}
fetch('../api/guardar_equipos_manual.php', {
method: 'POST',
headers: {'Content-Type': 'application/json'},
body: JSON.stringify({
id_reserva: <?= $id_reserva ?? 0 ?>,
rojos: rojos,
blancos: blancos
})
})
.then(r => r.json())
.then(data => {
if (data.success) {
mostrarToast('✅ Equipos guardados');
setTimeout(() => location.reload(), 1500);
} else {
mostrarToast('❌ ' + data.message);
}
})
.catch(err => {
console.error('Error:', err);
mostrarToast('❌ Error al guardar equipos');
});
}
function cerrarModalEquipos() {
const modal = document.getElementById('modalEquipos');
if (modal) modal.style.display = 'none';
}
// === CARGAR TABLA ÚNICA ===
function cargarTabla(filtro) {
const tbody = document.getElementById('tablaContenido');
if (!tbody) return;
tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:2rem;">Cargando...</td></tr>';
// Caso especial: torneos usa un endpoint diferente
if (filtro === 'torneos') {
fetch('../api/get_mis_torneos.php')
.then(r => r.json())
.then(data => {
if (!Array.isArray(data) || data.length === 0) {
tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;">No estás inscrito en ningún torneo americano</td></tr>`;
return;
}
let html = '';
data.forEach(row => {
html += `
<tr>
<td>${formatDate(row.fecha)}</td>
<td>-</td>
<td>${row.id_tipoevento}</td>
<td>${row.id_club || '-'}</td>
<td>${row.id_cancha || '-'}</td>
<td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td>
<td>${row.nombre || '-'}</td>
<td>${row.posicion_jugador || '-'}</td>
<td>-</td>
<td>-</td>
<td>${row.comentario || '-'}</td>
<td>-</td>
</tr>
`;
});
tbody.innerHTML = html;
})
.catch(err => {
console.error('Error:', err);
tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#FF6B6B;">Error al cargar los torneos</td></tr>';
});
return;
}
// Caso general: otros filtros usan get_tabla_datos.php
fetch(`../api/get_tabla_datos.php?filtro=${filtro}`)
.then(r => r.json())
.then(data => {
if (data.error) {
tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;color:#FF6B6B;">${data.error}</td></tr>`;
return;
}
if (!Array.isArray(data) || data.length === 0) {
tbody.innerHTML = `<tr><td colspan="12" style="text-align:center;">No hay datos disponibles</td></tr>`;
return;
}
let html = '';
data.forEach(row => {
let botonAccion = '-';
let comentario = '-';
if (filtro === 'cuotas') {
const esResponsable = <?= json_encode($es_responsable) ?>;
if (esResponsable) {
if (row.estado === 'pendiente') {
botonAccion = `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#F39C12;" onclick="revisarPago(${row.id_cuota})">🔍 Revisar</button>`;
} else if (row.estado === 'en_revision') {
botonAccion = `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#2ECC71;" onclick="validarPago(${row.id_cuota})">✅ Validar</button>`;
}
}
comentario = row.comentario || '-';
html += `
<tr>
<td>${formatDate(row.fecha_evento)}</td>
<td>-</td>
<td>${row.origen || '-'}</td>
<td>-</td>
<td>-</td>
<td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td>
<td>${row.nombre_socio || '-'}</td>
<td>${row.rol || '-'}</td>
<td>$${parseInt(row.monto || 0).toLocaleString()}</td>
<td>${row.fecha_pago ? formatDate(row.fecha_pago) : '-'}</td>
<td>${row.estado}${comentario !== '-' ? ' - ' + comentario : ''}</td>
<td>${botonAccion}</td>
</tr>
`;
} else if (filtro === 'socios') {
    const esResponsable = <?= json_encode($es_responsable) ?>;
    let botonAccion = '-';
    if (esResponsable) {
        botonAccion = `
            <div style="display:flex;gap:0.6rem;justify-content:center;">
                <span style="cursor:pointer;font-size:1.2rem;" onclick="editarPerfilSocio(${row.id_evento})">✏️</span>
                <span style="cursor:pointer;font-size:1.2rem;" onclick="eliminarSocio(${row.id_evento})">🗑️</span>
            </div>
        `;
    }
    html += `
        <tr>
            <td>${formatDate(row.created_at)}</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>${row.alias || '-'}</td>
            <td>${row.posicion_jugador || '-'}</td>
            <td>-</td>
            <td>-</td>
            <td>${row.email || '-'}</td>
            <td>${botonAccion}</td>
        </tr>
    `;
} else if (filtro === 'inscritos') {
const esResponsable = <?= json_encode($es_responsable) ?>;
const esMiInscripcion = (row.id_socio == <?= (int)($_SESSION['id_socio'] ?? 0) ?>);
const fechaEvento = new Date(row.fecha + ' ' + (row.hora_inicio || '00:00'));
const ahora = new Date();
let acciones = '';
if (esMiInscripcion || (esResponsable && fechaEvento > ahora)) {
acciones += `<button class="btn-action" style="padding:0.2rem 0.4rem;font-size:0.7rem;background:#FF6B6B;margin-right:0.3rem;" onclick="bajarseEvento(${row.id_evento}, ${esResponsable && !esMiInscripcion ? row.id_socio : 'null'})">Bajar</button>`;
}
if (esResponsable && fechaEvento > ahora) {
const emoji = row.lleva_cerveza ? '🍺' : '';
acciones += `<span style="font-size:1.2rem;cursor:pointer;" onclick="asignarCerveza(${row.id_inscrito}, ${row.lleva_cerveza ? 0 : 1})">${emoji}</span>`;
}
botonAccion = acciones || '-';
comentario = row.comentario || '-';
html += `
<tr>
<td>${formatDate(row.fecha)}</td>
<td>${row.hora_inicio?.substring(0,5) || '-'}</td>
<td>${row.id_tipoevento || '-'}</td>
<td>${row.id_club || '-'}</td>
<td>${row.id_cancha || '-'}</td>
<td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td>
<td>${row.nombre || '-'}</td>
<td>${row.posicion_jugador || '-'}</td>
<td>$${parseInt(row.cuota_monto || 0).toLocaleString()}</td>
<td>${row.fecha_pago ? formatDate(row.fecha_pago) : '-'}</td>
<td>${comentario}</td>
<td>${botonAccion}</td>
</tr>
`;
} else {
// Equipos IA, Reservas, Eventos → sin acciones ni comentario
html += `
<tr>
<td>${formatDate(row.fecha)}</td>
<td>${row.hora_inicio?.substring(0,5) || '-'}</td>
<td>${row.id_tipoevento || '-'}</td>
<td>${row.id_club || '-'}</td>
<td>${row.id_cancha || '-'}</td>
<td>$${parseInt(row.costo_evento || 0).toLocaleString()}</td>
<td>${row.nombre || '-'}</td>
<td>${row.posicion_jugador || '-'}</td>
<td>-</td>
<td>-</td>
<td>-</td>
<td>-</td>
</tr>
`;
}
});
tbody.innerHTML = html;
})
.catch(err => {
console.error('Error:', err);
tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#FF6B6B;">Error al cargar los datos</td></tr>';
});
}
// === INICIALIZAR AL CARGAR LA PÁGINA ===
document.addEventListener('DOMContentLoaded', () => {
// 1. Configurar botones de filtro
document.querySelectorAll('.filter-btn').forEach(btn => {
btn.addEventListener('click', () => {
document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
btn.classList.add('active');
const filtro = btn.getAttribute('data-filter');
cargarTabla(filtro);
});
});
// 2. Cargar tabla según parámetro de URL o por defecto
const urlParams = new URLSearchParams(window.location.search);
const filtro = urlParams.get('filtro') || 'inscritos';
const activeBtn = document.querySelector(`[data-filter="${filtro}"]`);
if (activeBtn) {
activeBtn.classList.add('active');
}
cargarTabla(filtro);

// === CARGAR DATOS DEL TORNEO SI ES NECESARIO ===
<?php if ($modo_individual && !empty($torneos_americanos)): ?>
const idTorneo = <?= (int)$torneo_actual['id_torneo'] ?>;

// Fixture
fetch(`../api/get_fixture.php?id_torneo=${idTorneo}`)
.then(r => r.json())
.then(data => {
let html = '';
const rondas = {};
data.forEach(p => {
const key = new Date(p.fecha_hora_programada).toISOString().split('T')[0];
if (!rondas[key]) rondas[key] = [];
rondas[key].push(p);
});
let numRonda = 1;
Object.values(rondas).forEach(partidos => {
html += `<strong>Set ${numRonda}</strong><br>`;
partidos.forEach(p => {
html += `<div>${p.pareja1} vs ${p.pareja2}</div>`;
});
html += `<br>`;
numRonda++;
});
document.getElementById('fixtureTorneo').innerHTML = html || 'No hay fixture';
});

// Resultados
fetch(`../api/get_resultados_torneo.php?id_torneo=${idTorneo}`)
.then(r => r.json())
.then(data => {
let html = '<table style="width:100%;font-size:0.85rem;">';
const rondas = {};
data.forEach(p => {
const key = new Date(p.fecha_hora_programada).toISOString().split('T')[0];
if (!rondas[key]) rondas[key] = [];
rondas[key].push(p);
});
let numRonda = 1;
Object.values(rondas).forEach(partidos => {
partidos.forEach(p => {
const ganador = (p.juegos1 > p.juegos2) ? p.pareja1 : p.pareja2;
html += `<tr><td>Set ${numRonda}</td><td>${p.pareja1} (${p.juegos1})</td><td>vs</td><td>${p.pareja2} (${p.juegos2})</td><td><strong>${ganador}</strong></td></tr>`;
});
numRonda++;
});
html += '</table>';
document.getElementById('resultadosTorneo').innerHTML = html || 'Sin resultados';
});

// Posiciones
fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`)
.then(r => r.json())
.then(data => {
let html = '<table style="width:100%;font-size:0.85rem;">';
(data.posiciones || []).forEach(p => {
html += `<tr><td style="text-align:center;font-weight:bold;">${p.sets_ganados}</td><td>${p.nombre_pareja}</td></tr>`;
});
html += '</table>';
document.getElementById('posicionesTorneo').innerHTML = html || 'Sin posiciones';
});

// Noticias (Resultados resumen)
fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`)
.then(r => r.json())
.then(data => {
let html = '<ul style="list-style:none;padding:0;">';
(data.posiciones || []).slice(0, 3).forEach((p, i) => {
html += `<li style="margin:0.5rem 0;"><strong>${i+1}. ${p.nombre_pareja}</strong> - ${p.sets_ganados} sets</li>`;
});
html += '</ul>';
document.getElementById('noticiasTorneo').innerHTML = html || 'Sin resultados';
});
<?php endif; ?>
});
// === INICIALIZAR PUESTOS ===
document.addEventListener('DOMContentLoaded', () => {
const deporteSelect = document.getElementById('deporte');
if (deporteSelect?.value) {
cargarPuestosPorDeporte(deporteSelect.value);
}
deporteSelect?.addEventListener('change', function() {
cargarPuestosPorDeporte(this.value);
});
});

function agregarOtroClub() {
    fetch('../api/listar_clubes_publicos.php')
    .then(r => r.json())
    .then(clubes => {
        let html = '<h3>Selecciona un club</h3>';
        html += '<input type="text" id="buscador-club" placeholder="Buscar..." onkeyup="filtrarClubes()" style="width:100%;padding:0.5rem;margin:0.5rem 0;">';
        html += '<div id="lista-clubes" style="max-height:300px;overflow-y:auto;">';
        clubes.forEach(c => {
            // Evitar clubes ya unidos
            const yaUnido = <?= json_encode(array_column($clubes_del_socio, 'id_club')) ?>;
            if (yaUnido.includes(c.id_club)) return;
            html += `<div style="padding:0.5rem;cursor:pointer;border-bottom:1px solid #eee;" onclick="solicitarUnirseAClub(${c.id_club})">${c.nombre}</div>`;
        });
        html += '</div>';
        
        // Mostrar en modal
        const modal = document.createElement('div');
        modal.id = 'modalOtroClub';
        modal.innerHTML = `
            <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;display:flex;justify-content:center;align-items:center;">
                <div style="background:white;padding:2rem;border-radius:12px;max-width:400px;width:90%;">
                    ${html}
                    <button onclick="cerrarModalOtroClub()" style="margin-top:1rem;background:#6c757d;color:white;border:none;padding:0.5rem 1rem;border-radius:6px;">Cerrar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    });
}

function filtrarClubes() {
    const input = document.getElementById('buscador-club').value.toLowerCase();
    const items = document.querySelectorAll('#lista-clubes > div');
    items.forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(input) ? 'block' : 'none';
    });
}

function cerrarModalOtroClub() {
    const modal = document.getElementById('modalOtroClub');
    if (modal) modal.remove();
}

function solicitarUnirseAClub(idClub) {
    // Obtener slug del club
    fetch(`../api/get_club_slug.php?id=${idClub}`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            fetch('../api/unirse_a_club.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ club_slug: data.slug })
            })
            .then(r => r.json())
            .then(res => {
                cerrarModalOtroClub();
                if (res.success) {
                    alert('✅ ' + res.message);
                    location.reload();
                } else {
                    alert('❌ ' + res.message);
                }
            });
        }
    });
}
</script>

<!-- Modal Compartir Club -->
<div id="modalCompartir" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
<div style="background:white; color:#071289; padding:2rem; border-radius:14px; max-width:400px; width:90%;">
<h3 style="margin-top:0;">🔗 Compartir tu club</h3>
<p>Envía este enlace a tus compañeros para que se inscriban fácilmente:</p>
<div style="background:#f1f1f1; padding:0.6rem; border-radius:6px; margin:1rem 0; word-break:break-all; font-family:monospace; font-size:0.9rem;">
<?= htmlspecialchars("https://canchasport.com/pages/registro_socio.php?club=" . ($club_slug ?? '')) ?>
</div>
<button onclick="copiarEnlace()" style="background:#071289; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; margin-right:0.5rem;">📋 Copiar</button>
<button onclick="cerrarModalCompartir()" style="background:#6c757d; color:white; border:none; padding:0.5rem 1rem; border-radius:6px;">Cerrar</button>
</div>
</div>

<!-- Modal Equipos IA -->
<div id="modalEquipos" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; justify-content:center; align-items:center;">
<div class="submodal-content" style="background:white; color:#333; padding:2rem; border-radius:16px; max-width:800px; width:90%; max-height:90vh; overflow-y:auto;">
<h3>🤖 Equipos Futbolito</h3>
<div style="display:flex;gap:2rem;margin:1.5rem 0;">
<div style="flex:1;background:#ffebee;padding:1rem;border-radius:8px;">
<h4 style="color:#e74c3c;">🔴 Rojos</h4>
<ul id="equipoRojos" style="list-style:none;padding:0;"></ul>
<button onclick="moverJugador('rojos', 'blancos')"
style="margin-top:0.5rem;background:#2980b9;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">
➡️ Mover a Blancos
</button>
</div>
<div style="flex:1;background:#e3f2fd;padding:1rem;border-radius:8px;">
<h4 style="color:#2980b9;">⚪ Blancos</h4>
<ul id="equipoBlancos" style="list-style:none;padding:0;"></ul>
<button onclick="moverJugador('blancos', 'rojos')"
style="margin-top:0.5rem;background:#e74c3c;color:white;border:none;padding:0.3rem 0.6rem;border-radius:4px;width:100%;">
➡️ Mover a Rojos
</button>
</div>
</div>
<h4>👉 Seleccionar un jugador y dar click en barra para mover a los Rojos o Blancos</h4>
<button onclick="guardarEquipos()" class="btn-submit" style="margin-top:1.5rem;">Guardar Equipos</button>
<button onclick="cerrarModalEquipos()" style="margin-top:0.5rem;background:#6c757d;color:white;border:none;padding:0.5rem 1rem;border-radius:6px;">Cerrar</button>
</div>
</div>
</div>
</body>
</html>