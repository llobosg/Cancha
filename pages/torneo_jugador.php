<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_socio']) && !isset($_SESSION['user_email'])) {
    header('Location: ../index.php');
    exit;
}

// Validar slug del torneo
$slug = $_GET['slug'] ?? null;
if (!$slug || strlen($slug) < 5) {
    header('Location: ../index.php');
    exit;
}

// Obtener ID del torneo
$stmt_t = $pdo->prepare("SELECT id_torneo, nombre, fecha_inicio FROM torneos WHERE slug = ? AND publico = 1");
$stmt_t->execute([$slug]);
$torneo = $stmt_t->fetch();
if (!$torneo) {
    http_response_code(404);
    die('Torneo no encontrado');
}
$id_torneo = $torneo['id_torneo'];
$nombre_torneo = $torneo['nombre'];
$fecha_torneo = date('d/m', strtotime($torneo['fecha_inicio']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($nombre_torneo) ?> | CanchaSport</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎾</text></svg>">
  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(to bottom, #071289, #0a2a66);
      color: white;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 1.2rem;
    }
    .header {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .header h1 {
      font-size: 1.6rem;
      margin: 0.3rem 0;
    }
    .header p {
      opacity: 0.9;
      font-size: 0.95rem;
    }
    .tabs {
      display: flex;
      gap: 0.5rem;
      overflow-x: auto;
      margin: 1.2rem 0;
      padding-bottom: 0.5rem;
    }
    .tab {
      background: rgba(255,255,255,0.2);
      border: none;
      color: white;
      padding: 0.6rem 1rem;
      border-radius: 20px;
      font-size: 0.95rem;
      white-space: nowrap;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tab.active {
      background: #FFD700;
      color: #071289;
      font-weight: bold;
    }
    .content {
      background: rgba(255,255,255,0.15);
      border-radius: 14px;
      padding: 1.2rem;
      margin-top: 1rem;
    }
    .content h3 {
      margin-top: 0;
      font-size: 1.2rem;
      color: #FFD700;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    th, td {
      padding: 0.5rem 0.3rem;
      text-align: left;
      border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    th {
      font-weight: bold;
      opacity: 0.9;
    }
    .loading {
      text-align: center;
      padding: 1.5rem;
      opacity: 0.7;
    }
    .no-data {
      text-align: center;
      padding: 1.5rem;
      font-style: italic;
    }
    .back-btn {
      display: block;
      margin: 1.5rem auto 0;
      background: #667eea;
      color: white;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      font-weight: bold;
      text-decoration: none;
      width: fit-content;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🎾 <?= htmlspecialchars($nombre_torneo) ?></h1>
      <p><?= $fecha_torneo ?> • Americano</p>
    </div>

    <div class="tabs">
      <button class="tab active" data-tab="parejas">Parejas</button>
      <button class="tab" data-tab="fixture">Fixture</button>
      <button class="tab" data-tab="resultados">Resultados</button>
      <button class="tab" data-tab="posiciones">Posiciones</button>
    </div>

    <div class="content">
      <div id="contenidoTab" class="loading">Cargando...</div>
    </div>

    <a href="../index.php" class="back-btn">← Volver</a>
  </div>

  <script>
    const idTorneo = <?= (int)$id_torneo ?>;
    let currentTab = 'parejas';

    // Cambiar pestaña
    document.querySelectorAll('.tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentTab = btn.dataset.tab;
        cargarContenido();
      });
    });

    function cargarContenido() {
      const cont = document.getElementById('contenidoTab');
      cont.innerHTML = '<div class="loading">Cargando...</div>';

      switch(currentTab) {
        case 'parejas':
          fetch(`../api/get_parejas_torneo.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(data => {
              if (!data.length) {
                cont.innerHTML = '<div class="no-data">No hay parejas inscritas aún</div>';
                return;
              }
              let html = `<table><thead><tr><th>#</th><th>Pareja</th></tr></thead><tbody>`;
              data.forEach((p, i) => {
                html += `<tr><td>${i+1}</td><td>${p.nombre}</td></tr>`;
              });
              html += `</tbody></table>`;
              cont.innerHTML = html;
            });
          break;

        case 'fixture':
          fetch(`../api/get_fixture.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(data => {
              if (!data.length) {
                cont.innerHTML = '<div class="no-data">Fixture no generado</div>';
                return;
              }
              const rondas = {};
              data.forEach(p => {
                const key = new Date(p.fecha_hora_programada).toISOString().split('T')[0];
                if (!rondas[key]) rondas[key] = [];
                rondas[key].push(p);
              });
              let html = '';
              let numRonda = 1;
              Object.values(rondas).forEach(partidos => {
                html += `<strong>Set ${numRonda}</strong><br>`;
                partidos.forEach(p => {
                  html += `<div style="margin:0.4rem 0;">${p.pareja1} vs ${p.pareja2}</div>`;
                });
                html += `<br>`;
                numRonda++;
              });
              cont.innerHTML = html;
            });
          break;

        case 'resultados':
          fetch(`../api/get_resultados_torneo.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(data => {
              if (!data.length) {
                cont.innerHTML = '<div class="no-data">No hay resultados registrados</div>';
                return;
              }
              const rondas = {};
              data.forEach(p => {
                const key = new Date(p.fecha_hora_programada).toISOString().split('T')[0];
                if (!rondas[key]) rondas[key] = [];
                rondas[key].push(p);
              });
              let html = '<table><thead><tr><th>Ronda</th><th>Pareja 1</th><th>vs</th><th>Pareja 2</th><th>Ganadora</th></tr></thead><tbody>';
              let numRonda = 1;
              Object.values(rondas).forEach(partidos => {
                partidos.forEach(p => {
                  const ganador = (p.juegos1 > p.juegos2) ? p.pareja1 : p.pareja2;
                  html += `<tr><td>Set ${numRonda}</td><td>${p.pareja1} (${p.juegos1})</td><td>vs</td><td>${p.pareja2} (${p.juegos2})</td><td><strong>${ganador}</strong></td></tr>`;
                });
                numRonda++;
              });
              html += '</tbody></table>';
              cont.innerHTML = html;
            });
          break;

        case 'posiciones':
          fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(data => {
              if (!data.posiciones || !data.posiciones.length) {
                cont.innerHTML = '<div class="no-data">Sin posiciones</div>';
                return;
              }
              let html = '<table><thead><tr><th>Sets</th><th>Pareja</th></tr></thead><tbody>';
              data.posiciones.forEach(p => {
                html += `<tr><td style="text-align:center;font-weight:bold;">${p.sets_ganados}</td><td>${p.nombre_pareja}</td></tr>`;
              });
              html += '</tbody></table>';
              cont.innerHTML = html;
            });
          break;
      }
    }

    // Cargar la primera pestaña
    cargarContenido();
  </script>
</body>
</html>