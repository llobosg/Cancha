<?php
// pages/dashboard.php
require_once __DIR__ . '/../includes/config.php';

// Obtener club desde URL
$club_slug = $_GET['id_club'] ?? '';
if (!$club_slug) {
    header('Location: index.php?error=no_club');
    exit;
}

// Buscar club válido - ¡INCLUIR email_responsable!
$stmt = $pdo->prepare("SELECT id_club, nombre, logo, email_responsable FROM clubs WHERE email_verified = 1");
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$club_id = null;
$club_nombre = '';
$club_logo = '';

foreach ($clubs as $c) {
    if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
        $club_id = $c['id_club'];
        $club_nombre = $c['nombre'];
        $club_logo = $c['logo'];
        break;
    }
}

if (!$club_id) {
    header('Location: index.php?error=invalid_club');
    exit;
}

// Total socios activos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM socios WHERE id_club = ? AND activo = 'Si'");
$stmt->execute([$id_club]);
$total_socios = $stmt->fetchColumn();

// Próximo evento
$stmt = $pdo->prepare("
    SELECT id_evento, fecha, hora, lugar, valor_cuota 
    FROM eventos 
    WHERE id_club = ? AND fecha >= CURDATE() 
    ORDER BY fecha ASC, hora ASC 
    LIMIT 1
");
$stmt->execute([$id_club]);
$proximo_evento = $stmt->fetch();

// Cuotas impagas (pendientes o en revisión)
$stmt = $pdo->prepare("
    SELECT SUM(c.monto) as total_impago
    FROM cuotas c
    JOIN eventos e ON c.id_evento = e.id_evento
    WHERE e.id_club = ? AND c.estado IN ('pendiente', 'en_revision')
");
$stmt->execute([$id_club]);
$total_impago = $stmt->fetchColumn() ?: 0;

// Eventos del mes actual
$mes_actual = date('Y-m');
$stmt = $pdo->prepare("
    SELECT id_evento, fecha, hora, lugar, valor_cuota,
           (SELECT COUNT(*) FROM inscritos i WHERE i.id_evento = e.id_evento AND i.anotado = 1) as inscritos
    FROM eventos e
    WHERE id_club = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?
    ORDER BY fecha, hora
");
$stmt->execute([$id_club, $mes_actual]);
$eventos_mes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($club['nombre']) ?> - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    :root {
      --primary: #009966;
      --secondary: #3a4f63;
      --light: #f5f7fa;
      --card-bg: white;
      --shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--light);
      color: #333;
    }
    .header {
      background: linear-gradient(135deg, var(--primary), #007a52);
      color: white;
      padding: 1.2rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header h1 {
      font-size: 1.8rem;
      font-weight: 700;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      padding: 2rem;
    }
    .stat-card {
      background: var(--card-bg);
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
    }
    .stat-card h3 {
      font-size: 1rem;
      color: #666;
      margin-bottom: 0.5rem;
    }
    .stat-card .value {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary);
    }
    .next-event {
      background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
      border-left: 5px solid var(--primary);
    }
    .next-event .value {
      color: #2e7d32;
    }
    .impago {
      background: linear-gradient(135deg, #ffebee, #ffcdd2);
      border-left: 5px solid #c62828;
    }
    .impago .value {
      color: #c62828;
    }
    .main-content {
      display: grid;
      grid-template-columns: 1fr 350px;
      gap: 2rem;
      padding: 0 2rem 2rem;
    }
    @media (max-width: 900px) {
      .main-content {
        grid-template-columns: 1fr;
      }
    }
    .calendar {
      background: var(--card-bg);
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: var(--shadow);
    }
    .calendar h2 {
      margin-bottom: 1.2rem;
      color: var(--secondary);
      font-size: 1.4rem;
    }
    .event-item {
      padding: 1rem;
      border-bottom: 1px solid #eee;
    }
    .event-item:last-child {
      border-bottom: none;
    }
    .event-date {
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 0.3rem;
    }
    .event-place {
      font-size: 0.95rem;
      color: #666;
    }
    .event-inscritos {
      font-size: 0.9rem;
      color: #888;
      margin-top: 0.3rem;
    }
    .no-events {
      text-align: center;
      color: #888;
      padding: 2rem;
    }
    .btn-action {
      display: inline-block;
      background: var(--primary);
      color: white;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-top: 1rem;
      transition: opacity 0.2s;
    }
    .btn-action:hover {
      opacity: 0.9;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>🏟️ <?= htmlspecialchars($club['nombre']) ?></h1>
    <span><?= htmlspecialchars(ucfirst($club['deporte'])) ?></span>
  </div>

  <div class="stats">
    <div class="stat-card next-event">
      <h3>Próximo evento</h3>
      <?php if ($proximo_evento): ?>
        <div class="value"><?= date('d M', strtotime($proximo_evento['fecha'])) ?></div>
        <p><?= date('H:i', strtotime($proximo_evento['hora'])) ?> • <?= htmlspecialchars($proximo_evento['lugar'] ?? 'Lugar por definir') ?></p>
        <p>Cuota: $<?= number_format($proximo_evento['valor_cuota'], 0, ',', '.') ?></p>
        <a href="crear_evento.php?id=<?= $proximo_evento['id_evento'] ?>" class="btn-action">Ver convocatoria</a>
      <?php else: ?>
        <div class="value">—</div>
        <p>No hay eventos programados</p>
        <a href="crear_evento.php?id_club=<?= $id_club ?>" class="btn-action">Crear evento</a>
      <?php endif; ?>
    </div>

    <div class="stat-card">
      <h3>Total socios</h3>
      <div class="value"><?= $total_socios ?></div>
      <a href="gestion_socios.php?id_club=<?= $id_club ?>" class="btn-action">Gestionar</a>
    </div>

    <div class="stat-card impago">
      <h3>Cuotas impagas</h3>
      <div class="value">$<?= number_format($total_impago, 0, ',', '.') ?></div>
      <a href="gestion_cuotas.php?id_club=<?= $id_club ?>" class="btn-action">Ver detalles</a>
    </div>
  </div>

  <div class="main-content">
    <div></div> <!-- Espacio vacío a la izquierda -->

    <div class="calendar">
      <h2>📅 Eventos este mes</h2>
      <?php if ($eventos_mes): ?>
        <?php foreach ($eventos_mes as $evento): ?>
          <div class="event-item">
            <div class="event-date">
              <?= date('d M', strtotime($evento['fecha'])) ?> • <?= date('H:i', strtotime($evento['hora'])) ?>
            </div>
            <div class="event-place"><?= htmlspecialchars($evento['lugar'] ?? 'Sin lugar') ?></div>
            <div class="event-inscritos"><?= $evento['inscritos'] ?> inscritos</div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-events">
          No hay eventos este mes.<br>
          <a href="crear_evento.php?id_club=<?= $id_club ?>" class="btn-action" style="margin-top:1rem;">Crear evento</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script>
    // ✅ localStorage es JavaScript, no PHP
    const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
    localStorage.setItem('cancha_device', deviceId);
    localStorage.setItem('cancha_session', 'active');
    localStorage.setItem('cancha_club', '<?= htmlspecialchars($club_slug) ?>');
  </script>
  <script>
    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW registrado:', reg.scope))
          .catch(err => console.log('Error SW:', err));
      });
    }
    </script>
</body>
</html>