<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación CEO
if (!isset($_SESSION['ceo_id']) || $_SESSION['ceo_rol'] !== 'ceo_cancha') {
    header('Location: ceo_login.php');
    exit;
}

// Estadísticas
$stmt_clubs = $pdo->query("SELECT COUNT(*) as total FROM clubs");
$total_clubs = $stmt_clubs->fetch()['total'];

$stmt_socios = $pdo->query("SELECT COUNT(*) as total FROM socios");
$total_socios = $stmt_socios->fetch()['total'];

// Estadísticas por región
$stmt_regiones = $pdo->query("
    SELECT 
        COALESCE(país, 'Sin país') as país,
        COUNT(*) as total 
    FROM clubs 
    GROUP BY país 
    ORDER BY total DESC
");
$regiones = $stmt_regiones->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard CEO - Cancha</title>
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
      color: white;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }
    
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
    }
    
    .logo-ceo {
      font-size: 2.5rem;
      font-weight: bold;
      color: #FFD700;
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
    
    .mantenedores-section {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .mantenedores-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-top: 1.5rem;
    }
    
    .mantenedor-card {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      cursor: pointer;
      transition: transform 0.2s;
    }
    
    .mantenedor-card:hover {
      transform: translateY(-5px);
      background: #f0f0f0;
    }
    
    .mantenedor-card h4 {
      color: #071289;
      margin: 0 0 1rem 0;
    }
    
    .region-stats {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      margin-top: 2rem;
    }
    
    .region-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .region-item {
      background: rgba(255, 255, 255, 0.2);
      padding: 0.8rem;
      border-radius: 8px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="header">
      <div class="logo-ceo">👑 CEO Dashboard</div>
      <div>
        <a href="ceo_logout.php" class="logout" style="color: #ffcc00; text-decoration: none;">Cerrar sesión</a>
      </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Total Clubs</h3>
        <div class="number"><?= $total_clubs ?></div>
      </div>
      <div class="stat-card">
        <h3>Total Socios</h3>
        <div class="number"><?= $total_socios ?></div>
      </div>
    </div>

    <!-- Mantenedores -->
    <div class="mantenedores-section">
      <h2>Mantenedores del Sistema</h2>
      <div class="mantenedores-grid">
        <div class="mantenedor-card" onclick="window.location.href='mantenedor_tipoeventos.php'">
          <h4>⚽ Tipo Eventos</h4>
          <p>Gestionar tipos de eventos</p>
        </div>
        <div class="mantenedor-card" onclick="window.location.href='mantenedor_puestos.php'">
          <h4>👔 Puestos</h4>
          <p>Gestionar puestos de socios</p>
        </div>
        <div class="mantenedor-card" onclick="window.location.href='mantenedor_clubs.php'">
          <h4>🏟️ Clubs</h4>
          <p>Administrar todos los clubs</p>
        </div>
        <div class="mantenedor-card" onclick="window.location.href='mantenedor_socios.php'">
          <h4>👥 Socios</h4>
          <p>Administrar todos los socios</p>
        </div>
      </div>
    </div>

    <!-- Estadísticas por región -->
    <div class="region-stats">
      <h2>Clubs por País/Región</h2>
      <div class="region-list">
        <?php foreach ($regiones as $region): ?>
        <div class="region-item">
          <strong><?= htmlspecialchars($region['país']) ?></strong><br>
          <?= $region['total'] ?> clubs
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="logout">
      <a href="ceo_logout.php">Cerrar sesión</a>
    </div>
  </div>

  <script>
    // Agregar protección adicional
    if (!localStorage.getItem('ceo_session')) {
        localStorage.setItem('ceo_session', 'active');
    }
  </script>
</body>
</html>