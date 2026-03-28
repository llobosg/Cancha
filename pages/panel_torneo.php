<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Obtener ID del torneo desde la URL
$id_torneo = (int)($_GET['id'] ?? 0);

if ($id_torneo <= 0) {
    die('❌ Torneo no especificado. Regresa al listado.');
}

// Opcional: guardar en sesión para otras páginas
$_SESSION['id_torneo_actual'] = $id_torneo;

// Obtener nombre del torneo
$stmt = $pdo->prepare("SELECT nombre FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
$stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
$torneo = $stmt->fetch();
$nombre_torneo = $torneo ? $torneo['nombre'] : 'Torneo';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <div class="header">
    <h1>🏆 TORNEO <?= htmlspecialchars($nombre_torneo) ?> EN VIVO</h1>
  </div>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    background: url('/assets/img/padel.png') center/contain fixed;
    color: white;
    font-family: 'Segoe UI', sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  .header {
    background: #10B981; /* Verde fluor */
    padding: 1.2rem 2rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
  }
  .header h1 {
    font-size: 2.4rem;
    font-weight: bold;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
  }
  .main {
    display: flex;
    flex: 1;
    padding: 1.5rem;
    gap: 2rem;
    height: calc(100vh - 120px);
  }
  .fixture-col {
    width: 70%;
    overflow-y: auto;
  }
  .posiciones-col {
      width: 30%;
      background: rgba(13, 47, 94, 0.85);
      border: 2px solid white; /* Rayas de cancha */
      padding: 1.5rem;
      border-radius: 12px;
      height: fit-content;
    }
  .bloque {
    background: rgba(13, 47, 94, 0.85); /* Azul intenso con transparencia */
    border: 2px solid white; /* Rayas de cancha */
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(2px);
  }
  .partido {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem;
    margin-bottom: 0.6rem;
    background: rgba(19, 58, 112, 0.7);
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.3);
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
      background: rgba(0, 0, 0, 0.6);
      padding: 1rem;
      text-align: center;
      font-size: 0.9rem;
      color: #a0aec0;
    }
  </style>
</head>
<body>
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
        <h2>🏅 Posiciones</h2>
        <ul id="ranking-list" class="ranking">
          <li><span>Cargando...</span></li>
        </ul>
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
        const resPos = await fetch(`/api/torneos/posiciones_torneo.php?id=${ID_TORNEO}`);
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

      const sets = [];
      for (let i = 0; i < partidos.length; i += 3) {
        sets.push(partidos.slice(i, i + 3));
      }

      let html = '';
      sets.forEach((setPartidos, idx) => {
        html += `<div style="margin:1.5rem 0;"><strong>SET ${idx + 1}</strong></div>`;
        setPartidos.forEach(p => {
          // Determinar ganador
          let ganador = '';
          if (p.set1_p1 !== null && p.set1_p2 !== null) {
            ganador = (p.set1_p1 > p.set1_p2) ? p.pareja1 : p.pareja2;
          }

          html += `
            <div class="partido" style="display:flex;justify-content:space-between;align-items:center;padding:0.8rem;background:#0d1b2a;border-radius:8px;margin-bottom:0.6rem;">
              <div style="flex:2;">${p.pareja1} VS ${p.pareja2}</div>
              <div style="flex:1;text-align:center;font-weight:bold;">
                ${p.set1_p1 || '–'} - ${p.set1_p2 || '–'}
              </div>
              <div style="flex:1.5;color:#4ade80;">${ganador || '–'}</div>
            </div>
          `;
        });
      });

      cont.innerHTML = html;
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
          <span>${r.puntos} Set</span>
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