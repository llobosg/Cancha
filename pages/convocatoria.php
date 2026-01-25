<?php
//-- pages/convocatoria.php --

require_once __DIR__ . '/../includes/config.php';

$id_evento = $_GET['id'] ?? null;
if (!$id_evento) {
    die('Evento no especificado');
}

// Obtener datos del evento y club
$stmt = $pdo->prepare("
    SELECT e.id_evento, e.fecha, e.hora, e.lugar, e.valor_cuota, c.nombre AS club_nombre
    FROM eventos e
    JOIN clubs c ON e.id_club = c.id_club
    WHERE e.id_evento = ?
");
$stmt->execute([$id_evento]);
$evento = $stmt->fetch();
if (!$evento) die('Evento no encontrado');

// Obtener todos los inscritos + socios
$stmt = $pdo->prepare("
    SELECT 
        s.id_socio,
        s.alias,
        s.nombre,
        s.foto_url,
        s.genero,
        i.anotado,
        i.equipo
    FROM inscritos i
    JOIN socios s ON i.id_socio = s.id_socio
    WHERE i.id_evento = ?
    ORDER BY 
        CASE WHEN s.alias != '' THEN s.alias ELSE s.nombre END ASC
");
$stmt->execute([$id_evento]);
$inscritos = $stmt->fetchAll();

// Contar confirmados y retirados
$confirmados = array_filter($inscritos, fn($i) => $i['anotado'] == 1);
$retirados = array_filter($inscritos, fn($i) => $i['anotado'] == 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Convocatoria - <?= htmlspecialchars($evento['club_nombre']) ?></title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    :root {
      --primary: #009966;
      --blue: #2196f3;
      --red: #f44336;
      --gray: #9e9e9e;
      --light-bg: #f5f7fa;
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--light-bg);
      color: #333;
      padding-bottom: 2rem;
    }
    .header {
      background: linear-gradient(135deg, var(--primary), #007a52);
      color: white;
      padding: 1.5rem 2rem;
      text-align: center;
    }
    .header h1 {
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
    }
    .event-info {
      opacity: 0.9;
      font-size: 1.1rem;
    }
    .stats-bar {
      display: flex;
      justify-content: center;
      gap: 2rem;
      padding: 1rem 2rem;
      background: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .stat {
      text-align: center;
    }
    .stat-value {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--primary);
    }
    .stat-label {
      font-size: 0.9rem;
      color: #666;
    }
    .grid-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1.5rem;
    }
    .players-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 1.5rem;
      justify-items: center;
    }
    .player-card {
      position: relative;
      width: 100%;
      max-width: 140px;
      text-align: center;
    }
    .player-avatar {
      width: 100%;
      aspect-ratio: 1/1;
      border-radius: 12px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 2.2rem;
      color: white;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .avatar-femenino { background: linear-gradient(135deg, #ec407a, #d81b60); }
    .avatar-masculino { background: linear-gradient(135deg, #29b6f6, #0288d1); }
    .avatar-otro { background: linear-gradient(135deg, #9575cd, #673ab7); }
    .player-name {
      margin-top: 0.6rem;
      font-size: 0.95rem;
      font-weight: 600;
      color: #333;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 140px;
    }
    .badge {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 1.1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    .badge.confirmado { background: var(--blue); }
    .badge.retirado { background: var(--red); }
    .badge.pendiente { background: var(--gray); }
    .no-players {
      text-align: center;
      padding: 3rem;
      color: #888;
    }
    @media (max-width: 600px) {
      .players-grid {
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
      }
      .player-name {
        font-size: 0.85rem;
        max-width: 100px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>⚽ Convocatoria</h1>
    <div class="event-info">
      <?= date('d M Y', strtotime($evento['fecha'])) ?> • <?= date('H:i', strtotime($evento['hora'])) ?>  
      <?php if ($evento['lugar']): ?> • <?= htmlspecialchars($evento['lugar']) ?><?php endif; ?>
    </div>
  </div>

  <div class="stats-bar">
    <div class="stat">
      <div class="stat-value"><?= count($confirmados) ?></div>
      <div class="stat-label">Confirmados</div>
    </div>
    <div class="stat">
      <div class="stat-value"><?= count($retirados) ?></div>
      <div class="stat-label">Retirados</div>
    </div>
    <div class="stat">
      <div class="stat-value">$<?= number_format($evento['valor_cuota'], 0, ',', '.') ?></div>
      <div class="stat-label">Cuota</div>
    </div>
  </div>

  <div class="grid-container">
    <?php if ($inscritos): ?>
      <div class="players-grid">
        <?php foreach ($inscritos as $socio): ?>
          <?php
            $nombre_mostrar = !empty($socio['alias']) ? $socio['alias'] : $socio['nombre'];
            $iniciales = strtoupper(substr($nombre_mostrar, 0, 2));
            $foto = $socio['foto_url'] ? '../uploads/socios/' . htmlspecialchars($socio['foto_url']) : '';
            $genero_class = match($socio['genero']) {
                'Femenino' => 'avatar-femenino',
                'Masculino' => 'avatar-masculino',
                default => 'avatar-otro'
            };
            $badge_class = $socio['anotado'] == 1 ? 'confirmado' : 'retirado';
            $badge_icon = $socio['anotado'] == 1 ? '✓' : '✕';
          ?>
          <div class="player-card">
            <?php if ($foto): ?>
              <img src="<?= $foto ?>" alt="<?= htmlspecialchars($nombre_mostrar) ?>" class="player-avatar">
            <?php else: ?>
              <div class="player-avatar <?= $genero_class ?>"><?= $iniciales ?></div>
            <?php endif; ?>
            <div class="badge <?= $badge_class ?>"><?= $badge_icon ?></div>
            <div class="player-name"><?= htmlspecialchars($nombre_mostrar) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="no-players">
        <p>No hay jugadores inscritos aún.</p>
        <p>Comparte el enlace de inscripción con tu equipo.</p>
      </div>
    <?php endif; ?>
  </div>
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