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

    <!-- Panel de Torneos -->
    <div class="dynamic-panel" id="panelTorneos">
      <h3>🏆 Torneos Americanos Activos</h3>
      <div id="listaTorneos" style="margin-top: 1rem;">
        <p>Cargando torneos...</p>
      </div>
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

    function verFixture(idTorneo) {
      window.torneoActualId = idTorneo;
        // Primero, obtener el nombre del torneo
        fetch(`../api/get_torneo_nombre.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(torneo => {
                const nombreTorneo = torneo.nombre || 'Torneo';

                // Luego, cargar el fixture
                fetch(`../api/get_fixture.php?id_torneo=${idTorneo}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data || data.length === 0) {
                            alert('No hay fixture generado');
                            return;
                        }

                        let html = `<h3>🎾 Fixture - ${nombreTorneo}</h3>`;
                        
                        // Agrupar por fecha/hora
                        const rondas = {};
                        data.forEach(partido => {
                            const key = partido.fecha_hora_programada;
                            if (!rondas[key]) rondas[key] = [];
                            rondas[key].push(partido);
                        });

                        let rondaNum = 1;
                        Object.entries(rondas).forEach(([fecha, partidos]) => {
                            const fechaObj = new Date(fecha);
                            const fechaStr = fechaObj.toLocaleDateString('es-CL');
                            let horaStr = '';
                            if (rondaNum === 1) {
                                horaStr = ' ' + fechaObj.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
                            }
                            
                            html += `<div style="margin:1.5rem 0;"><strong>📅 Set ${rondaNum} – ${fechaStr}${horaStr}</strong><br>`;
                            partidos.forEach(p => {
                                html += `
                                    <div style="display:flex;justify-content:space-between;margin:0.4rem 0;background:rgba(255,255,255,0.1);padding:0.5rem;border-radius:6px;">
                                        <span>${p.pareja1}</span>
                                        <span>vs</span>
                                        <span>${p.pareja2}</span>
                                        <span style="cursor:pointer;color:#FFD700;" onclick="abrirResultado(${p.id_partido}, '${p.pareja1}', '${p.pareja2}')">✅ Resultado</span>
                                    </div>
                                `;
                            });
                            html += `</div>`;
                            rondaNum++;
                        });

                        html += `<button class="action-btn" style="margin-top:1rem;" onclick="cerrarSubmodal()">Cerrar</button>`;
                        document.getElementById('submodalContenido').innerHTML = html;
                        document.getElementById('submodalGenerico').style.display = 'flex';
                    });
            });
    }

    function editarResultado(idPartido, equipo1, equipo2) {
      const goles1 = prompt(`Goles de ${equipo1}:`);
      const goles2 = prompt(`Goles de ${equipo2}:`);
      if (goles1 === null || goles2 === null) return;
      if (isNaN(goles1) || isNaN(goles2)) {
        alert('Por favor ingresa números válidos');
        return;
      }
      fetch('../api/guardar_resultado_partido_torneo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          id_partido: idPartido,
          goles1: goles1,
          goles2: goles2
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('✅ Resultado guardado');
          verFixture(document.querySelector('#submodalContenido h3').textContent.split(' - ')[1]?.replace('📋 Fixture ', '') || idPartido);
        } else {
          alert('❌ ' + data.message);
        }
      });
    }

    // === CARGAR TORNEOS AL INICIAR ===
    document.addEventListener('DOMContentLoaded', () => {
      fetch('../api/get_torneos_recinto.php')
        .then(r => r.json())
        .then(data => {
          const contenedor = document.getElementById('listaTorneos');
          if (data.error) {
            contenedor.innerHTML = `<p style="color:#FF6B6B;">❌ ${data.error}</p>`;
            return;
          }
          if (data.length === 0) {
            contenedor.innerHTML = `<p>📭 No hay torneos activos.</p>`;
            return;
          }
          let html = '<div style="display:flex;flex-direction:column;gap:0.8rem;">';
          data.forEach(torneo => {
            const fecha = new Date(torneo.fecha_inicio).toLocaleDateString('es-CL');
            const fechaInicio = new Date(torneo.fecha_inicio).toLocaleDateString('es-CL');
            const fechaFin = new Date(torneo.fecha_fin).toLocaleDateString('es-CL');
            const creado = new Date(torneo.created_at).toLocaleDateString('es-CL');
            const publico = torneo.publico == 1 ? '✅ Sí' : '❌ No';
            const estadoMap = {
              'borrador': 'Borrador',
              'abierto': 'Abierto',
              'cerrado': 'Cerrado',
              'en_progreso': 'En progreso',
              'finalizado': 'Finalizado'
            };
            const estadoLabel = estadoMap[torneo.estado] || torneo.estado;
            const parejas = `${torneo.num_parejas_max} parejas`;
            const pendientes = torneo.parejas_inscritas;
            const valor = parseInt(torneo.valor) || 0;
            const totalRecaudado = valor * (torneo.parejas_inscritas || 0);
            html += `
              <div style="background:rgba(255,255,255,0.2);padding:1.2rem;border-radius:12px;position:relative;">
                <h4 style="margin:0 0 0.8rem 0;font-size:1.1rem;">${torneo.nombre}</h4>
                <small>${torneo.categoria} • ${torneo.nivel} • ${fechaInicio} • ${parejas} • ${estadoLabel} • ${torneo.premios}</small>
                <small><div>Inscritos: ${torneo.parejas_inscritas} / ${torneo.num_parejas_max}</div></small>
                <small><div>Valor: $${valor.toLocaleString()}</div></small>
                <small><div>Recaudado:</strong> $${totalRecaudado.toLocaleString()} (${torneo.parejas_inscritas || 0} pendientes)</div></small>

                <!-- Menú de acciones (tres puntos) -->
                <div style="position:absolute;top:0.8rem;right:0.8rem;cursor:pointer;font-size:1.2rem;color: #ed0606;z-index:5;" onclick="toggleMenu(${torneo.id_torneo}, event)">
                  ⋮
                </div>

                <!-- Menú desplegable -->
                <div id="menu-${torneo.id_torneo}" style="display:none;position:absolute;top:2rem;right:0.5rem;background:white;color:#071289;padding:0.5rem;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.2);z-index:10;">
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;" onclick="editarTorneo(${torneo.id_torneo})">✏️ Editar</div>
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;" onclick="cerrarTorneo(${torneo.id_torneo})">🔒 Cerrar</div>
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;color:#FF6B6B;" onclick="eliminarTorneo(${torneo.id_torneo})">🗑️ Eliminar</div>
                </div>

                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" 
                        onclick="generarFixture(${torneo.id_torneo})">
                  Generar Fixture
                </button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" 
                        onclick="verFixture(${torneo.id_torneo})">
                  Ver Fixture
                </button>   
                <button class="action-btn" style="padding:0.35rem;font-size:0.9rem;background:#FFD700;color:#071289;flex:1;"
                        onclick="compartirTorneo('${torneo.slug}')">
                  Compartir link
                </button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" 
                        onclick="verParejas(${torneo.id_torneo})">
                  Parejas
                </button>
              </div>
            `;
          });
          html += '</div>';
          contenedor.innerHTML = html;
        })
        .catch(err => {
          console.error(err);
          document.getElementById('listaTorneos').innerHTML = `<p style="color:#FF6B6B;">❌ Error al cargar torneos</p>`;
        });
    });

    // === VER FIXTURE ===
    function verFixture(idTorneo) {
      fetch(`../api/get_fixture.php?id_torneo=${idTorneo}`)
          .then(r => r.json())
          .then(data => {
              if (!data || data.length === 0) {
                  alert('No hay fixture generado');
                  return;
              }

              let html = `<h3>🎾 Fixture Americano</h3>`;
              
              // Agrupar por fecha/hora
              const rondas = {};
              data.forEach(partido => {
                  const key = partido.fecha_hora_programada;
                  if (!rondas[key]) rondas[key] = [];
                  rondas[key].push(partido);
              });

              let rondaNum = 1;
              Object.entries(rondas).forEach(([fecha, partidos]) => {
                  const fechaObj = new Date(fecha);
                  const fechaStr = fechaObj.toLocaleDateString('es-CL');
                  const horaStr = fechaObj.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
                  
                  html += `<div style="margin:1.5rem 0;"><strong>📅 Set ${rondaNum} – ${fechaStr} ${horaStr}</strong><br>`;
                  partidos.forEach(p => {
                      html += `
                          <div style="display:flex;justify-content:space-between;margin:0.4rem 0;background:rgba(255,255,255,0.1);padding:0.5rem;border-radius:6px;">
                              <span>${p.pareja1}</span>
                              <span>vs</span>
                              <span>${p.pareja2}</span>
                              <span style="cursor:pointer;color:#FFD700;" onclick="abrirResultado(${p.id_partido}, '${p.pareja1}', '${p.pareja2}')">✅ Resultado</span>
                          </div>
                      `;
                  });
                  html += `</div>`;
                  rondaNum++;
              });

              html += `<button class="action-btn" style="margin-top:1rem;" onclick="cerrarSubmodal()">Cerrar</button>`;
              document.getElementById('submodalContenido').innerHTML = html;
              document.getElementById('submodalGenerico').style.display = 'flex';
          });
    }

    // === EDITAR RESULTADO ===
    function editarResultado(idPartido, equipo1, equipo2) {
      const goles1 = prompt(`Goles de ${equipo1}:`);
      const goles2 = prompt(`Goles de ${equipo2}:`);
      if (goles1 === null || goles2 === null) return;
      if (isNaN(goles1) || isNaN(goles2)) {
        alert('Por favor ingresa números válidos');
        return;
      }
      fetch('../api/guardar_resultado_partido_torneo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          id_partido: idPartido,
          goles1: goles1,
          goles2: goles2
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('✅ Resultado guardado');
          verFixture(idPartido); // Recargar fixture
        } else {
          alert('❌ ' + data.message);
        }
      });
    }

    // === compartir torneo ===
    function compartirTorneo(slug) {
      const link = `https://canchasport.com/pages/torneo_publico.php?slug=${slug}`;
      let qrHtml = `
        <h3>📤 Compartir torneo</h3>
        <p>Copia el enlace o escanea el QR para inscribirse:</p>
        <div style="text-align:center;margin:1.5rem 0;">
          <div id="qrTorneo" style="width:180px;height:180px;margin:0 auto;"></div>
        </div>
        <div style="background:#f1f1f1;padding:0.8rem;border-radius:6px;margin:1rem 0;word-break:break-all;font-family:monospace;font-size:0.9rem;">
          ${link}
        </div>
        <button class="action-btn" style="margin-bottom:0.5rem;width:100%;" onclick="copiarLink('${link}')">
          📋 Copiar enlace
        </button>
        <button class="action-btn" style="background:#6c757d;width:100%;" onclick="cerrarSubmodal()">
          Cerrar
        </button>
      `;
      document.getElementById('submodalContenido').innerHTML = qrHtml;
      document.getElementById('submodalGenerico').style.display = 'flex';

      // Generar QR
      new QRCode(document.getElementById("qrTorneo"), {
        text: link,
        width: 160,
        height: 160,
        colorDark: "#071289",
        colorLight: "#ffffff"
      });
    }

    // === copiar enlace ===
    function copiarLink(link) {
      navigator.clipboard.writeText(link).then(() => {
        alert('✅ Enlace copiado al portapapeles');
      });
    }

    // === ver parejas ===
    function verParejas(idTorneo) {
      fetch(`../api/get_parejas_torneo.php?id_torneo=${idTorneo}`)
        .then(r => r.json())
        .then(data => {
          let html = `<h3>Parejas inscritas</h3><table style="width:100%;border-collapse:collapse;margin-top:1rem;">
            <thead><tr style="background:#071289;color:white;">
              <th>Nº</th><th>Nombre</th><th>Estado</th><th>Acción</th>
            </tr></thead><tbody>`;
          
          data.forEach((p, i) => {
            html += `
              <tr style="border-bottom:1px solid #ccc;">
                <td>${i+1}</td>
                <td>${p.nombre}</td>
                <td>${p.estado_valor || 'pendiente'}</td>
                <td><span style="cursor:pointer;font-size:1.2rem;" onclick="eliminarPareja(${p.id_pareja})">🗑️</span></td>
              </tr>
            `;
          });
          html += `</tbody></table><button class="action-btn" style="margin-top:1rem;" onclick="cerrarSubmodal()">Cerrar</button>`;
          document.getElementById('submodalContenido').innerHTML = html;
          document.getElementById('submodalGenerico').style.display = 'flex';
        });
    }

    // Gerenar fixture
    function generarFixture(idTorneo) {
        fetch('../api/generar_fixture.php', {
            method: 'POST',
            credentials: 'include',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id_torneo: String(idTorneo)})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                // Opcional: recargar la lista de torneos
                cargarTorneos();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Error al generar el fixture');
        });
    }

    // Cerrar otros menús al abrir uno nuevo
    function toggleMenu(id, event) {
      event.stopPropagation();
      const menu = document.getElementById(`menu-${id}`);
      // Cerrar todos los menús
      document.querySelectorAll('[id^="menu-"]').forEach(m => m.style.display = 'none');
      // Abrir el seleccionado
      menu.style.display = 'block';
    }

    // Cerrar menús al hacer clic fuera
    document.addEventListener('click', () => {
      document.querySelectorAll('[id^="menu-"]').forEach(m => m.style.display = 'none');
    });

    // editar torneo
    function editarTorneo(idTorneo) {
      // Redirigir al formulario de edición
      window.location.href = `crear_torneo.php?editar=${idTorneo}`;
    }

    // Cerrar torneo
    function cerrarTorneo(idTorneo) {
      if (confirm('¿Cerrar inscripciones para este torneo?')) {
        fetch('../api/cambiar_estado_torneo.php', {
          method: 'POST',
          credentials: 'include',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({id_torneo: String(idTorneo), estado: 'cerrado'})
        }).then(r => r.json()).then(data => {
          if (data.success) location.reload();
          else alert('Error: ' + data.message);
        });
      }
    }

    // Eliminar torneo
    function eliminarTorneo(idTorneo) {
      if (confirm('¿Eliminar este torneo? Esta acción no se puede deshacer.')) {
        fetch('../api/eliminar_torneo.php', {
          method: 'POST',
          credentials: 'include',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({id_torneo: String(idTorneo)})
        }).then(r => r.json()).then(data => {
          if (data.success) location.reload();
          else alert('Error: ' + data.message);
        });
      }
    }

    function abrirResultado(idPartido, pareja1, pareja2) {
        const anchoReducido = 'max-width:450px; width:85%;'; // ← 25% más angosto
        const html = `
            <div style="text-align:center;${anchoReducido}">
                <h3>📊 Ingresar resultado</h3>
                <p><strong>${pareja1} vs ${pareja2}</strong></p>
                <div style="display:flex;justify-content:center;gap:1rem;margin:1rem 0;">
                    <div>
                        <label>${pareja1}</label>
                        <input type="number" id="juegos1" min="0" max="7" value="6" style="width:80px;padding:0.4rem;text-align:center;">
                    </div>
                    <div>
                        <label>${pareja2}</label>
                        <input type="number" id="juegos2" min="0" max="7" value="4" style="width:80px;padding:0.4rem;text-align:center;">
                    </div>
                </div>
                <div id="ganadora" style="margin:0.5rem 0;font-weight:bold;"></div>
                <button class="action-btn" style="background:#2ECC71;margin-top:0.5rem;" onclick="guardarResultado(${idPartido}, '${pareja1}', '${pareja2}')">Guardar</button>
                <button class="action-btn" style="background:#6c757d;margin-top:0.5rem;" onclick="volverAFixture()">Cancelar</button>
            </div>
        `;
        document.getElementById('submodalContenido').innerHTML = html;
        document.getElementById('submodalGenerico').style.display = 'flex';

        // Actualizar ganadora en tiempo real
        document.getElementById('juegos1').addEventListener('input', actualizarGanadora);
        document.getElementById('juegos2').addEventListener('input', actualizarGanadora);
        actualizarGanadora();
    }

    function actualizarGanadora() {
        const j1 = parseInt(document.getElementById('juegos1').value) || 0;
        const j2 = parseInt(document.getElementById('juegos2').value) || 0;
        const pareja1 = document.querySelector('#submodalContenido p strong').textContent.split(' vs ')[0];
        const pareja2 = document.querySelector('#submodalContenido p strong').textContent.split(' vs ')[1];
        const div = document.getElementById('ganadora');
        if (j1 > j2) {
            div.textContent = `Ganadora: ${pareja1}`;
        } else if (j2 > j1) {
            div.textContent = `Ganadora: ${pareja2}`;
        } else {
            div.textContent = '';
        }
    }

    function guardarResultado(idPartido, pareja1, pareja2) {
        const j1 = parseInt(document.getElementById('juegos1').value) || 0;
        const j2 = parseInt(document.getElementById('juegos2').value) || 0;

        fetch('../api/guardar_resultado_torneo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                id_partido: idPartido,
                juegos1: j1,
                juegos2: j2
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Resultado guardado');
                if (typeof window.torneoActualId !== 'undefined') {
                    verFixture(window.torneoActualId);
                } else {
                    cerrarSubmodal();
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Error de conexión');
        });
    }

    function volverAFixture() {
        // Vuelve a cargar el fixture del torneo actual
        if (window.torneoActualId) {
            verFixture(window.torneoActualId);
        } else {
            cerrarSubmodal();
        }
    }

    function cerrarSubmodal() {
        document.getElementById('submodalGenerico').style.display = 'none';
    }
  </script>
  <!-- Submodal genérico -->
  <div id="submodalGenerico" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    justify-content: center;
    align-items: center;
  ">
    <div style="
      background: white;
      color: #071289;
      padding: 2rem;
      border-radius: 16px;
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    ">
      <span style="
        float: right;
        font-size: 1.5rem;
        cursor: pointer;
      " onclick="cerrarSubmodal()">&times;</span>
      <div id="submodalContenido"></div>
    </div>
  </div>
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</body>
</html>