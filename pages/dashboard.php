<!-- pages/dashboard.php -->
<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener club desde URL
$club_slug = $_GET['id_club'] ?? '';
if (!$club_slug) {
    header('Location: index.php?error=no_club');
    exit;
}

// Buscar club válido
$stmt = $pdo->prepare("SELECT id_club, nombre, logo FROM clubs WHERE email_verified = 1");
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
      background: #f5f7fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }
    
    .header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #003366;
    }
    
    .club-logo {
      width: 60px;
      height: 60px;
      border-radius: 10px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #666;
      font-size: 1.5rem;
    }
    
    .club-info h1 {
      color: #003366;
      margin: 0;
      font-size: 1.8rem;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
    }
    
    .stat-card h3 {
      color: #003366;
      margin-bottom: 0.5rem;
    }
    
    .stat-card .number {
      font-size: 2rem;
      font-weight: bold;
      color: #071289;
    }
    
    .actions {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .actions h2 {
      color: #003366;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-action {
      padding: 0.8rem 1.5rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    
    .btn-action:hover {
      background: #050d66;
    }
    
    .logout {
      text-align: center;
      margin-top: 2rem;
    }
    
    .logout a {
      color: #cc0000;
      text-decoration: none;
      font-weight: bold;
    }
    
    .logout a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="header">
      <div class="club-logo">
        <?php if ($club_logo): ?>
          <img src="../uploads/logos/<?= htmlspecialchars($club_logo) ?>" alt="Logo" style="width:100%;height:100%;border-radius:10px;">
        <?php else: ?>
          ⚽
        <?php endif; ?>
      </div>
      <div class="club-info">
        <h1><?= htmlspecialchars($club_nombre) ?></h1>
        <p>Bienvenido a tu cancha</p>
      </div>
    </div>

    <!-- Estadísticas básicas -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Socios</h3>
        <div class="number" id="totalSocios">0</div>
      </div>
      <div class="stat-card">
        <h3>Eventos</h3>
        <div class="number" id="totalEventos">0</div>
      </div>
      <div class="stat-card">
        <h3>Próximo partido</h3>
        <div class="number" id="proximoPartido">-</div>
      </div>
    </div>

    <!-- Acciones principales -->
    <div class="actions">
      <h2>Acciones rápidas</h2>
      <div class="action-buttons">
        <button class="btn-action" onclick="window.location.href='convocatoria.php?id=<?= htmlspecialchars($club_id) ?>'">Crear convocatoria</button>
        <button class="btn-action" onclick="window.location.href='socios.php?id=<?= htmlspecialchars($club_id) ?>'">Gestionar socios</button>
        <button class="btn-action" onclick="window.location.href='eventos.php?id=<?= htmlspecialchars($club_id) ?>'">Eventos</button>
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

    // Limpiar sesión al cerrar
    function limpiarSesion() {
      localStorage.removeItem('cancha_session');
      localStorage.removeItem('cancha_club');
    }

    // Cargar estadísticas (simuladas)
    document.addEventListener('DOMContentLoaded', () => {
      // En producción, cargarías datos reales desde la API
      document.getElementById('totalSocios').textContent = '24';
      document.getElementById('totalEventos').textContent = '8';
      document.getElementById('proximoPartido').textContent = 'Sáb 15:00';
    });
  </script>
</body>
</html>