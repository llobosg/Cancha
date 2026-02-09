<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación de administrador de recinto
if (!isset($_SESSION['id_recinto'])) {
    header('Location: ../index.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Obtener datos del recinto
$stmt = $pdo->prepare("SELECT * FROM recintos_deportivos WHERE id_recinto = ?");
$stmt->execute([$id_recinto]);
$recinto = $stmt->fetch();

if (!$recinto) {
    header('Location: ../index.php');
    exit;
}

// Estadísticas generales
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_canchas,
        SUM(CASE WHEN r.estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_activas,
        COALESCE(SUM(CASE WHEN r.estado = 'confirmada' THEN r.monto_total ELSE 0 END), 0) as ingresos_hoy
    FROM canchas c
    LEFT JOIN reservas r ON c.id_cancha = r.id_cancha AND DATE(r.fecha) = CURDATE()
    WHERE c.id_recinto = ?
");
$stmt_stats->execute([$id_recinto]);
$stats = $stmt_stats->fetch();

// Tasa de ocupación (últimos 7 días)
$stmt_ocupacion = $pdo->prepare("
    SELECT 
        DATE(fecha) as fecha,
        COUNT(CASE WHEN id_reserva IS NOT NULL THEN 1 END) as reservadas,
        COUNT(*) as totales
    FROM disponibilidad_canchas dc
    JOIN canchas c ON dc.id_cancha = c.id_cancha
    WHERE c.id_recinto = ? AND fecha BETWEEN CURDATE() - INTERVAL 6 DAY AND CURDATE()
    GROUP BY DATE(fecha)
    ORDER BY fecha
");
$stmt_ocupacion->execute([$id_recinto]);
$ocupacion_data = $stmt_ocupacion->fetchAll();

// Ingresos últimos 30 días
$stmt_ingresos = $pdo->prepare("
    SELECT 
        DATE(created_at) as fecha,
        SUM(monto_total) as ingresos
    FROM reservas 
    WHERE id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)
      AND estado = 'confirmada'
      AND created_at >= CURDATE() - INTERVAL 29 DAY
    GROUP BY DATE(created_at)
    ORDER BY fecha
");
$stmt_ingresos->execute([$id_recinto]);
$ingresos_data = $stmt_ingresos->fetchAll();

// Canchas más rentables
$stmt_top_canchas = $pdo->prepare("
    SELECT 
        c.nro_cancha,
        c.id_deporte,
        COUNT(r.id_reserva) as reservas,
        SUM(r.monto_total) as ingresos
    FROM canchas c
    LEFT JOIN reservas r ON c.id_cancha = r.id_cancha AND r.estado = 'confirmada'
    WHERE c.id_recinto = ?
    GROUP BY c.id_cancha
    ORDER BY ingresos DESC
    LIMIT 5
");
$stmt_top_canchas->execute([$id_recinto]);
$top_canchas = $stmt_top_canchas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($recinto['nombre']) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 2rem;
      margin-bottom: 2.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
    }

    @media (max-width: 768px) {
      .header {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 1rem;
      }
    }
    
    .logo-recinto {
      font-size: 2.5rem;
      font-weight: bold;
      color: #FFD700;
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
    
    .charts-section {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      margin-bottom: 2.5rem;
    }
    
    .charts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 2rem;
    }
    
    .chart-container {
      height: 300px;
    }
    
    .top-canchas {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
    }
    
    .top-canchas table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    
    .top-canchas th, .top-canchas td {
      padding: 0.8rem;
      text-align: left;
      border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .top-canchas th {
      color: #FFD700;
    }
    
    .actions-section {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      margin-top: 2rem;
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
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
  </style>
</head>
<body>
  <div class="dashboard-container">
    <div class="header">
      <div>
        <h1 style="margin: 0; color: #FFD700; font-size: 2.8rem;">⚽ Cancha</h1>
        <p style="margin: 0.5rem 0 0 0; color: rgba(255,255,255,0.8); font-size: 1.1rem;">
          Administración Recintos Deportivos
        </p>
      </div>
      <div style="text-align: center; margin-top: 1rem;">
        <?php if (!empty($recinto['logorecinto'])): ?>
          <img src="../uploads/logos_recintos/<?= htmlspecialchars($recinto['logorecinto']) ?>" 
              alt="Logo <?= htmlspecialchars($recinto['nombre']) ?>"
              style="width: 80px; height: 80px; border-radius: 12px; object-fit: cover; background: rgba(255,255,255,0.2);">
        <?php else: ?>
          <div style="width: 80px; height: 80px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
            🏟️
          </div>
        <?php endif; ?>
        <h2 style="margin: 0.5rem 0 0 0; color: white; font-size: 1.4rem;">
          <?= htmlspecialchars($recinto['nombre']) ?>
        </h2>
      </div>
        <a href="recinto_logout.php" style="color: #ffcc00; text-decoration: none; font-weight: bold;">Cerrar sesión</a>
      </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Total Canchas</h3>
        <div class="number"><?= $stats['total_canchas'] ?></div>
      </div>
      <div class="stat-card">
        <h3>Reservas Hoy</h3>
        <div class="number"><?= $stats['reservas_activas'] ?></div>
      </div>
      <div class="stat-card">
        <h3>Ingresos Hoy</h3>
        <div class="number">$<?= number_format($stats['ingresos_hoy'] ?? 0, 0, ',', '.') ?></div>
      </div>
      <div class="stat-card">
        <h3>Ocupación Semanal</h3>
        <div class="number">
          <?php 
          $total_reservadas = array_sum(array_column($ocupacion_data, 'reservadas'));
          $total_disponibles = array_sum(array_column($ocupacion_data, 'totales'));
          $ocupacion_pct = $total_disponibles > 0 ? round(($total_reservadas / $total_disponibles) * 100, 1) : 0;
          echo $ocupacion_pct . '%';
          ?>
        </div>
      </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-section">
      <h2>Estadísticas</h2>
      <div class="charts-grid">
        <div class="chart-container">
          <canvas id="ocupacionChart"></canvas>
        </div>
        <div class="chart-container">
          <canvas id="ingresosChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Top canchas -->
    <div class="top-canchas">
      <h2>Canchas Más Rentables</h2>
      <table>
        <thead>
          <tr>
            <th>Cancha</th>
            <th>Deporte</th>
            <th>Reservas</th>
            <th>Ingresos</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_canchas as $cancha): ?>
          <tr>
            <td><?= htmlspecialchars($cancha['nro_cancha']) ?></td>
            <td><?= ucfirst($cancha['id_deporte']) ?></td>
            <td><?= $cancha['reservas'] ?></td>
            <td>$<?= number_format($cancha['ingresos'], 0, ',', '.') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Acciones rápidas -->
    <div class="actions-section">
      <h2>Acciones Rápidas</h2>
      <div class="action-buttons">
        <button class="btn-action" onclick="window.location.href='gestion_canchas.php?id=<?= $recinto['id_recinto'] ?>'">Gestionar Canchas</button>
        <button class="btn-action" onclick="window.location.href='calendario_reservas.php?id=<?= $recinto['id_recinto'] ?>'">Calendario Reservas</button>
        <button class="btn-action" onclick="window.location.href='crear_reserva_manual.php?id=<?= $recinto['id_recinto'] ?>'">Reserva Manual</button>
        <button class="btn-action" onclick="window.location.href='estadisticas_avanzadas.php?id=<?= $recinto['id_recinto'] ?>'">Estadísticas Avanzadas</button>
      </div>
    </div>

    <div class="logout">
      <a href="recinto_logout.php">Cerrar sesión</a>
    </div>
  </div>

  <script>
    // Datos para gráficos
    const ocupacionData = <?= json_encode($ocupacion_data) ?>;
    const ingresosData = <?= json_encode($ingresos_data) ?>;
    
    // Formatear fechas para los gráficos
    const ocupacionLabels = ocupacionData.map(item => new Date(item.fecha).toLocaleDateString('es-ES', {weekday: 'short'}));
    const ocupacionValues = ocupacionData.map(item => item.totales > 0 ? Math.round((item.reservadas / item.totales) * 100) : 0);
    
    const ingresosLabels = ingresosData.map(item => new Date(item.fecha).toLocaleDateString('es-ES', {day: 'numeric'}));
    const ingresosValues = ingresosData.map(item => parseFloat(item.ingresos));
    
    // Gráfico de ocupación
    const ocupacionCtx = document.getElementById('ocupacionChart').getContext('2d');
    new Chart(ocupacionCtx, {
        type: 'bar',
        data: {
            labels: ocupacionLabels,
            datasets: [{
                label: 'Tasa de Ocupación (%)',
                data: ocupacionValues,
                backgroundColor: 'rgba(255, 215, 0, 0.7)',
                borderColor: 'rgba(255, 215, 0, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // Gráfico de ingresos
    const ingresosCtx = document.getElementById('ingresosChart').getContext('2d');
    new Chart(ingresosCtx, {
        type: 'line',
        data: {
            labels: ingresosLabels,
            datasets: [{
                label: 'Ingresos Diarios ($)',
                data: ingresosValues,
                borderColor: 'rgba(0, 204, 102, 1)',
                backgroundColor: 'rgba(0, 204, 102, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
  </script>
</body>
</html>