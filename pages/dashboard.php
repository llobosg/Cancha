<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener slug del club desde la URL
$club_slug = $_GET['id_club'] ?? '';
if (!$club_slug) {
    header('Location: index.php?error=no_club');
    exit;
}

// Buscar club por slug (usando email_responsable para generar el mismo hash)
$stmt = $pdo->prepare("
    SELECT id_club, nombre, logo, email_responsable 
    FROM clubs 
    WHERE email_verified = 1
");
$stmt->execute();
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$club_id = null;
$club_nombre = '';
$club_logo = '';

foreach ($clubs as $c) {
    $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    if ($generated_slug === $club_slug) {
        $club_id = (int)$c['id_club'];
        $club_nombre = $c['nombre'];
        $club_logo = $c['logo'];
        break;
    }
}

if (!$club_id) {
    header('Location: index.php?error=invalid_club');
    exit;
}

// Contar socios del club
$stmt = $pdo->prepare("SELECT COUNT(*) FROM socios WHERE id_club = ? AND email_verified = 1");
$stmt->execute([$club_id]);
$total_socios = (int)$stmt->fetchColumn();

// Contar eventos (simulado, ajusta según tu tabla real)
$total_eventos = 0;
$proximo_partido = 'No programado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($club_nombre) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
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
    }
    
    .header {
      display: flex;
      align-items: center;
      gap: 1.2rem;
      margin-bottom: 2.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
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
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
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

    /* Logo ⚽ en esquinas */
    .dashboard-container::before,
    .dashboard-container::after {
      content: "⚽";
      position: absolute;
      font-size: 2.2rem;
      opacity: 0.4;
      z-index: 1;
    }
    .dashboard-container::before { top: 30px; left: 30px; }
    .dashboard-container::after { bottom: 30px; right: 30px; }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="header">
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

    <!-- Estadísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Socios activos</h3>
        <div class="number"><?= $total_socios ?></div>
      </div>
      <div class="stat-card">
        <h3>Eventos</h3>
        <div class="number"><?= $total_eventos ?></div>
      </div>
      <div class="stat-card">
        <h3>Próximo partido</h3>
        <div class="number"><?= htmlspecialchars($proximo_partido) ?></div>
      </div>
    </div>

    <!-- Acciones -->
    <div class="actions">
      <h2>Acciones rápidas</h2>
      <div class="action-buttons">
        <button class="btn-action" onclick="window.location.href='convocatoria.php?id=<?= $club_id ?>'">Crear convocatoria</button>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= $club_id ?>'">Gestionar socios</button>
        <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= $club_id ?>'">Eventos</button>
      </div>
    </div>

    <!-- Cerrar sesión -->
    <div class="logout">
      <a href="index.php" onclick="limpiarSesion()">Cerrar sesión</a>
    </div>
  </div>

  <script>
    // Guardar sesión en dispositivo
    const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
    localStorage.setItem('cancha_device', deviceId);
    localStorage.setItem('cancha_session', 'active');
    localStorage.setItem('cancha_club', '<?= htmlspecialchars($club_slug) ?>');

    function limpiarSesion() {
      localStorage.removeItem('cancha_session');
      localStorage.removeItem('cancha_club');
    }
  </script>
</body>
</html>