<?php
require_once __DIR__ . '/../includes/config.php';

// Configuración consistente de sesión
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

// Validar rol de recinto
if (!isset($_SESSION['recinto_rol']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
    header('Location: ../index.php');
    exit;
}

// Cargar datos del recinto
$id_recinto = $_SESSION['id_recinto'] ?? null;
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($recinto_nombre) ?> | CanchaSport</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏟️</text></svg>">
  <style>
    :root {
      --bg-primary: #071289;
      --accent: #4ECDC4;
      --gold: #FFD700;
      --card-bg: rgba(255, 255, 255, 0.15);
      --text-light: white;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)),
                  url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      color: var(--text-light);
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      padding: 1rem;
    }
    .container {
      max-width: 1400px;
      margin: 0 auto;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
    }
    .logo {
      width: 60px; height: 60px;
      border-radius: 12px;
      background: var(--card-bg);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem;
    }
    .filters-bar {
      display: flex; gap: 0.5rem; margin-bottom: 1.2rem;
    }
    .filter-btn {
      padding: 0.4rem 0.8rem;
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 6px;
      color: white;
      font-size: 0.85rem;
      cursor: pointer;
    }
    .filter-btn.active {
      background: var(--accent);
      border-color: var(--accent);
    }
    .stats-grid {
      display: grid;
      gap: 1.2rem;
      margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }
    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(10px);
      padding: 1.2rem;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .stat-title {
      font-size: 0.95rem;
      opacity: 0.9;
      margin-bottom: 0.8rem;
    }
    .chart {
      height: 60px;
      margin: 0.5rem 0;
    }
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .action-btn {
      padding: 0.8rem 0.5rem;
      background: var(--accent);
      color: var(--bg-primary);
      border: none;
      border-radius: 10px;
      font-weight: bold;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .action-btn:hover {
      transform: translateY(-2px);
    }
    .dynamic-panel {
      background: var(--card-bg);
      padding: 1.5rem;
      border-radius: 14px;
      min-height: 200px;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <header>
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="logo">🏟️</div>
        <div>
          <h1><?= htmlspecialchars($recinto_nombre) ?></h1>
          <p>Panel de Administración</p>
        </div>
      </div>
      <a href="../index.php" class="filter-btn" style="background:#FF6B6B;">Salir</a>
    </header>

    <!-- Filtros -->
    <div class="filters-bar">
      <button class="filter-btn active" data-period="month">Mes</button>
      <button class="filter-btn" data-period="week">Semana</button>
      <button class="filter-btn" data-period="day">Hoy</button>
    </div>

    <!-- Gráficos -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-title">Canchas disponibles</div>
        <div class="chart">
          <svg viewBox="0 0 100 20" style="width:100%; height:100%;">
            <rect x="0" y="0" width="100" height="20" fill="rgba(255,255,255,0.2)" rx="3"/>
            <rect x="0" y="0" width="60" height="20" fill="var(--accent)" rx="3"/>
          </svg>
        </div>
        <div>6/10 reservadas</div>
      </div>

      <div class="stat-card">
        <div class="stat-title">Ingresos este mes</div>
        <div style="font-size: 1.4rem; font-weight: bold;">$1.250.000</div>
        <div style="font-size: 0.9rem; color: #A8E6CF;">+12% vs mes anterior</div>
      </div>

      <div class="stat-card">
        <div class="stat-title">Ocupación MTD</div>
        <div class="chart">
          <svg viewBox="0 0 100 100" style="width:80px; height:80px; margin:0 auto;">
            <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="8"/>
            <circle cx="50" cy="50" r="45" fill="none" stroke="var(--accent)" stroke-width="8"
                    stroke-dasharray="282" stroke-dashoffset="<?= 282 * (1 - 0.72) ?>" transform="rotate(-90 50 50)"/>
            <text x="50" y="55" text-anchor="middle" fill="white" font-size="16">72%</text>
          </svg>
        </div>
        <div>+7% vs mes anterior</div>
      </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="quick-actions">
      <button class="action-btn" onclick="alert('Función en desarrollo: Gestionar cancha')">Gestionar cancha</button>
      <button class="action-btn" onclick="alert('Función en desarrollo: Calendario reservas')">Calendario reservas</button>
      <button class="action-btn" onclick="alert('Función en desarrollo: Reserva Manual')">Reserva Manual</button>
      <button class="action-btn" onclick="alert('Función en desarrollo: Crear Americano')">Crear Americano</button>
    </div>

    <!-- Panel dinámico -->
    <div class="dynamic-panel" id="dynamicPanel">
      <h3>📋 Bienvenido al panel de administración</h3>
      <p>Selecciona una acción rápida para comenzar.</p>
    </div>
  </div>

  <script>
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
  </script>
</body>
</html>