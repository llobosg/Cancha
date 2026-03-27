<?php
require_once __DIR__ . '/../includes/config.php';

$roles_permitidos = ['admin', 'staff', 'recinto_admin', 'admin_recinto'];
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
      header('Location: ../index.php');
    exit;
}

$id_torneo = $_GET['id'] ?? 0;
if (!$id_torneo) {
    die('ID de torneo requerido');
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>🏆 Panel Torneo en Vivo — CanchaSport</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: #0d1b2a;
      color: white;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .header {
      background: linear-gradient(90deg, #1e3a8a, #0d1b2a);
      padding: 1.2rem 2rem;
      text-align: center;
    }
    .header h1 {
      font-size: 2.2rem;
      font-weight: bold;
    }
    .main {
      display: flex;
      flex: 1;
      padding: 1.5rem;
      gap: 2rem;
    }
    .fixture-col {
      width: 60%;
    }
    .posiciones-col {
      width: 40%;
      background: #1b263b;
      padding: 1.5rem;
      border-radius: 12px;
      height: fit-content;
    }
    .bloque {
      background: #1b263b;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .partido {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      margin-bottom: 1rem;
      background: #0d1b2a;
      border-radius: 8px;
      border-left: 4px solid #3b82f6;
    }
    .pareja {
      font-weight: bold;
      font-size: 1.1rem;
    }
    .resultado {
      display: flex;
      gap: 0.5rem;
    }
    .resultado input {
      width: 50px;
      padding: 0.4rem;
      border: 1px solid #3b82f6;
      border-radius: 4px;
      background: #0d1b2a;
      color: white;
      text-align: center;
      font-size: 1.1rem;
    }
    .posiciones h2 {
      margin-bottom: 1.2rem;
      color: #60a5fa;
    }
    .ranking {
      list-style: none;
    }
    .ranking li {
      display: flex;
      justify-content: space-between;
      padding: 0.8rem 0;
      border-bottom: 1px solid #1e293b;
    }
    .ranking li:first-child {
      color: #fbbf24;
    }
    .ranking li:nth-child(2) {
      color: #c0c0c0;
    }
    .ranking li:nth-child(3) {
      color: #cd7f32;
    }
    footer {
      background: #000;
      padding: 1rem;
      text-align: center;
      font-size: 0.9rem;
      color: #64748b;
    }
  </style>
</head>
<body>

  <div class="header">
    <h1>🏆 TORNEO EN VIVO</h1>
  </div>

  <div class="main">
    <!-- FIXTURE -->
    <div class="fixture-col">
      <div class="bloque">
        <h2 style="margin-bottom:1rem; color:#60a5fa;">📋 Fixture</h2>
        <div id="fixture-container">
          <!-- Se llenará dinámicamente -->
          <p>Cargando partidos...</p>
        </div>
      </div>
    </div>

    <!-- POSICIONES -->
    <div class="posiciones-col">
      <div class="posiciones">
        <h2>🏅 Posiciones</h2>
        <ul id="ranking-list" class="ranking">
          <li><span>Cargando...</span></li>
        </ul>
      </div>
    </div>
  </div>

  <footer>
    powered by CanchaSport
  </footer>

  <script>
    const ID_TORNEO = <?= (int)$id_torneo ?>;

    // Actualizar cada 10 segundos
    async function cargarDatos() {
      try {
        // Fixture
        const resFixture = await fetch(`/api/torneos/fixture_actualizado.php?id=${ID_TORNEO}`);
        const fixture = await resFixture.json();
        renderizarFixture(fixture);

        // Posiciones
        const resPos = await fetch(`/api/torneos/posiciones.php?id=${ID_TORNEO}`);
        const posiciones = await resPos.json();
        renderizarPosiciones(posiciones);
      } catch (err) {
        console.error("Error al cargar datos:", err);
      }
    }

    function renderizarFixture(partidos) {
      const cont = document.getElementById('fixture-container');
      if (partidos.length === 0) {
        cont.innerHTML = '<p>No hay partidos programados</p>';
        return;
      }

      cont.innerHTML = partidos.map(p => `
        <div class="partido">
          <div>
            <div class="pareja">${p.pareja1}</div>
            <div class="pareja">${p.pareja2}</div>
          </div>
          <div class="resultado">
            <input type="number" min="0" max="99" value="${p.set1_p1 || ''}" 
                   onchange="guardarResultado(${p.id_partido}, 'set1_p1', this.value)">
            -
            <input type="number" min="0" max="99" value="${p.set1_p2 || ''}" 
                   onchange="guardarResultado(${p.id_partido}, 'set1_p2', this.value)">
          </div>
        </div>
      `).join('');
    }

    function renderizarPosiciones(ranking) {
      const ul = document.getElementById('ranking-list');
      if (ranking.length === 0) {
        ul.innerHTML = '<li><span>Sin datos</span></li>';
        return;
      }

      ul.innerHTML = ranking.map((r, i) => `
        <li>
          <span>${i+1}. ${r.alias || r.nombre}</span>
          <span>${r.puntos} pts</span>
        </li>
      `).join('');
    }

    async function guardarResultado(idPartido, campo, valor) {
      try {
        await fetch('/api/torneos/guardar_resultado.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ id_partido: idPartido, campo, valor })
        });
        // Recargar para reflejar cambios
        cargarDatos();
      } catch (err) {
        alert('Error al guardar resultado');
      }
    }

    // Iniciar
    document.addEventListener('DOMContentLoaded', () => {
      cargarDatos();
      setInterval(cargarDatos, 10000); // cada 10s
    });
  </script>
</body>
</html>