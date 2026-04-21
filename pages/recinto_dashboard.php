<?php
// pages/recinto_dashboard.php

// 1. Incluir config.php
require_once __DIR__ . '/../includes/config.php';

// 2. Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 2. Debug: Ver qué tenemos exactamente
$rol_actual = $_SESSION['recinto_rol'] ?? 'NO_EXISTE';
$id_recinto_actual = $_SESSION['id_recinto'] ?? 'NO_EXISTE';

error_log(" [DASHBOARD] Verificando sesión...");
error_log("   - id_recinto: " . var_export($id_recinto_actual, true));
error_log("   - rol: '" . $rol_actual . "' (Tipo: " . gettype($rol_actual) . ")");

// 3. Validación corregida
// Verificamos explícitamente que existan y que el rol sea uno de los esperados
$roles_validos = ['admin', 'asistente'];

if (!isset($_SESSION['id_recinto']) || !isset($_SESSION['recinto_rol']) || !in_array($rol_actual, $roles_validos)) {
    error_log("❌ [DASHBOARD] FALLÓ LA VALIDACIÓN.");
    error_log("   - isset(id_recinto): " . (isset($_SESSION['id_recinto']) ? 'SI' : 'NO'));
    error_log("   - isset(rol): " . (isset($_SESSION['recinto_rol']) ? 'SI' : 'NO'));
    error_log("   - in_array(rol): " . (in_array($rol_actual, $roles_validos) ? 'SI' : 'NO'));
    
    // Opcional: Si quieres ser menos estricto y solo verificar que exista el ID
    // if (!isset($_SESSION['id_recinto'])) { ... }
    
    header('Location: login_recintos.php');
    exit;
}

error_log("✅ [DASHBOARD] Sesión válida. Rol: $rol_actual");

require_once __DIR__ . '/../includes/permisos.php';

// Obtener datos del usuario logueado para mostrar en el perfil
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

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

    /* Asegurar que las tarjetas de estadísticas tengan tamaño consistente */
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }

    /* Botones de acción secundaria (Gestionar Asistentes, Perfil) */
    .btn-action-secondary:hover {
        background: #e3f2fd;
        border-color: #071289;
        transform: translateY(-2px);
    }

    /* Ocultar elementos que no queremos ver (por seguridad visual) */
    .hidden-panel {
        display: none !important;
    }

    .title-cancha {
        font-size: 2.8rem;
        margin-top: 1.5rem;
    }
    
    .title-cancha {
      font-family: 'Dancing Script', cursive;
      font-size: 3.8rem;
      margin: 2rem 0 1rem 0;
      color: white;
      text-shadow: 0 3px 6px rgba(245, 243, 247, 0.84);
    }

    @media (max-width: 768px) {
      .top-bar {
        padding: 0 1rem;
        height: 55px;
      }
      .title-cancha {
        font-size: 2.8rem;
        margin-top: 1.5rem;
      }
      .subtitle {
        font-size: 1.1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Barra superior -->
  <div class="top-bar">
    <div class="logo">⚽</div>
        <h2 class="title-cancha">CanchaSport ⚽></h2>
        <p style="color: #f1f1e8; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
            Recinto: <?= htmlspecialchars($recinto_nombre) ?> 
            <?php if (esAsistente()): ?> <span style="background:#e3f2fd; color:#1565c0; padding:2px 8px; border-radius:4px; font-size:0.8rem; margin-left:10px;">Rol: Administrador</span> <?php endif; ?>
        </p>
        <div style="color: #f1f1e8; margin: 0.5rem 0 0 0; font-size: 0.9rem;">
            Usuario: <?= htmlspecialchars($usuario_actual['nombre']) ?>
        </div>
    </div>
  </div>
  <div class="container" style="max-width: 1400px; margin: 0 auto; padding: 2rem;">
    <!-- Sub-header con Botones de Gestión (Anteriormente parte del título) -->
    <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 2rem; gap: 1rem;">
         <?php if (esAdmin()): ?>
            <a href="gestion_asistentes.php" class="btn-action-secondary" style="text-decoration: none; padding: 0.6rem 1.2rem; background: white; color: #AB47BC; border-radius: 8px; font-weight: bold; border: 2px solid #AB47BC; transition: 0.2s;">
                👥 Asistentes
            </a>
        <?php endif; ?>
        <a href="mantenedor_admin_recinto.php?id=<?= $usuario_actual['id_admin'] ?>" class="btn-action-secondary" style="text-decoration: none; padding: 0.6rem 1.2rem; background: white; color: #333; border-radius: 8px; font-weight: bold; border: 1px solid #ddd; transition: 0.2s;">
            ⚙️ Mi Perfil
        </a>
    </div>

    <!-- 2. CONTENIDO ESPECÍFICO POR ROL --> 
    <?php if (esAdmin()): ?>
        <!-- === VISTA ADMIN === -->
        
        <!-- Fila de KPIs (Solo Ingresos) -->
        <div style="display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap;">
            <div class="stat-card" style="flex: 0 0 auto; min-width: 250px; max-width: 300px; background: linear-gradient(135deg, #2E7D32, #4CAF50); color: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);">
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">Ingresos Este Mes</h3>
                <div style="font-size: 2.2rem; font-weight: 900;">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
                <div style="font-size: 0.85rem; margin-top: 0.5rem; opacity: 0.8;">Actualizado al <?= date('d/m/Y') ?></div>
            </div>
            <!-- Aquí podrías agregar más KPIs financieros si quisieras en el futuro -->
        </div>

    <?php else: ?>
        <!-- === VISTA ASISTENTE === -->
        
        <!-- Gráficos Operativos -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stat-card" style="background: #071289; color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <div class="stat-title" style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 1rem;">Canchas Disponibles</div>
                <div class="chart" style="margin-bottom: 1rem;">
                  <svg viewBox="0 0 100 20" style="width:100%; height:20px;">
                    <rect x="0" y="0" width="100" height="20" fill="rgba(255,255,255,0.2)" rx="3"/>
                    <rect x="0" y="0" width="60" height="20" fill="#4ECDC4" rx="3"/>
                  </svg>
                </div>
                <div style="font-size: 1.2rem; font-weight: bold;">6/10 Reservadas</div>
            </div>

            <div class="stat-card" style="background: #071289; color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <div class="stat-title" style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 1rem;">Ocupación MTD</div>
                <div class="chart" style="margin-bottom: 1rem; display: flex; justify-content: center;">
                  <svg viewBox="0 0 100 100" style="width:80px; height:80px;">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="8"/>
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#4ECDC4" stroke-width="8"
                            stroke-dasharray="282" stroke-dashoffset="<?= 282 * (1 - 0.72) ?>" transform="rotate(-90 50 50)"/>
                    <text x="50" y="55" text-anchor="middle" fill="white" font-size="16" font-weight="bold">72%</text>
                  </svg>
                </div>
                <div style="font-size: 0.9rem;">+7% vs mes anterior</div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <button class="action-btn" id="btnGestionCancha" style="padding: 1rem; background: white; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: bold; color: #333; transition: 0.2s;">Crear Canchas 🎾</button>
            <button class="action-btn" id="btnCalendarioReservas" style="padding: 1rem; background: white; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: bold; color: #333; transition: 0.2s;">Calendario reservas</button>
            <button class="action-btn" onclick="alert('Función en desarrollo: Reserva Manual')" style="padding: 1rem; background: white; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: bold; color: #333; transition: 0.2s;">Reserva Manual</button>
            <button class="action-btn" id="btnCrearTorneo" style="padding: 1rem; background: white; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; font-weight: bold; color: #333; transition: 0.2s;">Crear Torneo </button>
        </div>

        <!-- Panel de Torneos (Opcional, si el asistente lo necesita) -->
        <div class="dynamic-panel" id="panelTorneos" style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #eee;">
            <h3 style="margin-top: 0; color: #071289;">🏆 Torneos Americanos Activos</h3>
            <div id="listaTorneos" style="margin-top: 1rem; color: #666;">
                <p>Cargando torneos...</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. SECCIÓN PRINCIPAL COMUN: PLANILLA DE RESERVAS -->
    <!-- Visible para AMBOS roles -->
    <div class="main-content-section" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
            <h2 style="margin: 0; color: #333; font-size: 1.4rem;">📅 Planilla de Reservas</h2>
            <a href="calendario_reservas.php?vista=planilla" style="color: #071289; text-decoration: none; font-weight: bold; font-size: 0.9rem; background: #f0f4f8; padding: 0.5rem 1rem; border-radius: 6px;">Ver modo completo &rarr;</a>
        </div>
        
        <div style="text-align: center; padding: 2rem; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc;">
            <p style="color: #666; margin-bottom: 1rem;">Accede a la vista detallada de horarios, estados de pago y gestión de canchas.</p>
            <a href="calendario_reservas.php?vista=planilla" style="display: inline-block; padding: 0.8rem 2rem; background: #071289; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.2s;">Ir a la Planilla Completa</a>
        </div>
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
                const rondaNum = 0;

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
                        html += `<button class="action-btn" style="margin-top:0.5rem;background:#4ECDC4;" onclick="verResultados(${idTorneo})">Resultados Set</button>`;
                        html += `<button class="action-btn" style="margin-top:0.5rem;background:#FFD700;color:#071289;" onclick="verPosicionesTorneo(${idTorneo})">🏆 Posiciones</button>`;
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
          data.forEach($torneo => {
            const fechaInicio = new Date($torneo.fecha_inicio).toLocaleDateString('es-CL');
            const fechaFin = new Date($torneo.fecha_fin).toLocaleDateString('es-CL');
            const estadoMap = {
              'borrador': 'Borrador',
              'abierto': 'Abierto',
              'cerrado': 'Cerrado',
              'en_progreso': 'En progreso',
              'finalizado': 'Finalizado'
            };
            const estadoLabel = estadoMap[$torneo.estado] || $torneo.estado;
            const parejas = `${$torneo.num_parejas_max} parejas`;
            const valor = parseInt($torneo.valor) || 0;
            const totalRecaudado = valor * ($torneo.parejas_inscritas || 0);

            html += `
              <div style="background:rgba(255,255,255,0.2);padding:1.2rem;border-radius:12px;position:relative;">
                <h4 style="margin:0 0 0.8rem 0;font-size:1.1rem;">${$torneo.nombre}</h4>
                <small>${$torneo.categoria} • ${$torneo.nivel} • ${fechaInicio} • ${parejas} • ${estadoLabel} • ${$torneo.premios}</small>
                <small><div>Inscritos: ${$torneo.parejas_inscritas} / ${$torneo.num_parejas_max}</div></small>
                <small><div>Valor: $${valor.toLocaleString()}</div></small>
                <small><div>Recaudado: $${totalRecaudado.toLocaleString()} (${($torneo.parejas_inscritas || 0)} inscritos)</div></small>

                <!-- Menú de acciones -->
                <div style="position:absolute;top:0.8rem;right:0.8rem;cursor:pointer;font-size:1.2rem;color:#ed0606;z-index:5;" onclick="toggleMenu(${$torneo.id_torneo}, event)">⋮</div>

                <div id="menu-${$torneo.id_torneo}" style="display:none;position:absolute;top:2rem;right:0.5rem;background:white;color:#071289;padding:0.5rem;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.2);z-index:10;">
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;" onclick="editarTorneo(${$torneo.id_torneo})">✏️ Editar</div>
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;" onclick="cerrarTorneo(${$torneo.id_torneo})">🔒 Cerrar</div>
                  <div style="padding:0.3rem 0.6rem;cursor:pointer;color:#FF6B6B;" onclick="eliminarTorneo(${$torneo.id_torneo})">🗑️ Eliminar</div>
                </div>

                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" onclick="compartirTorneo('${$torneo.slug}')">Compartir link</button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" onclick="verParejas(${$torneo.id_torneo})">Parejas</button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" onclick="generarFixture(${$torneo.id_torneo})">Generar Fixture</button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;" onclick="verFixture(${$torneo.id_torneo})">Ver Fixture</button>
                <button class="action-btn" style="margin-top:0.5rem;padding:0.3rem;font-size:0.85rem;background:#3b82f6;color:white;" onclick="irPanelTorneo(${$torneo.id_torneo})">📺 Panel Torneo</button>
                
                <!-- ✅ Botón Finalizado y UpRanking -->
                <button 
                    class="btn-action" style="background:#FF9800;margin-left:10px;" 
                    onclick="finalizarTorneoYCalcularRanking(${parseInt($torneo.id_torneo)})">
                    ✅ Finalizado y UpRanking
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
                      <td>${p.nombre1} + ${p.nombre2}</td>
                      <td>${p.estado_valor || 'pendiente'}</td>
                      <td><span style="cursor:pointer;font-size:1.2rem;" onclick="eliminarParejaTorneo(${p.id_pareja})">🗑️</span></td>
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
        // Primero, verificar si ya hay resultados
        fetch(`../api/verificar_resultados_torneo.php?id_torneo=${idTorneo}`)
            .then(r => r.json())
            .then(data => {
                if (data.tiene_resultados) {
                    if (confirm('⚠️ Ya existen resultados registrados. ¿Deseas regenerar el fixture? Esto borrará todos los resultados actuales.')) {
                        procederConGeneracion(idTorneo);
                    }
                } else {
                    procederConGeneracion(idTorneo);
                }
            })
            .catch(err => {
                console.error(err);
                alert('❌ Error al verificar resultados');
            });
    }

    function procederConGeneracion(idTorneo) {
        fetch('../api/generar_fixture.php', {
            method: 'POST',
            credentials: 'include',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id_torneo: idTorneo})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
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
    
    let contenidoFixtureAnterior = '';

    function abrirResultado(idPartido, pareja1, pareja2) {
        // Guardar contenido del fixture
        contenidoFixtureAnterior = document.getElementById('submodalContenido').innerHTML;

        // Cargar resultado actual si existe
        fetch(`../api/get_resultado_partido.php?id_partido=${idPartido}`)
            .then(r => r.json())
            .then(resultado => {
                const j1 = resultado.juegos_pareja_1 || 0;
                const j2 = resultado.juegos_pareja_2 || 0;

                const anchoReducido = 'max-width:450px; width:85%;';
                const html = `
                    <div style="text-align:center;${anchoReducido}">
                        <h3>📊 Editar resultado</h3>
                        <p><strong>${pareja1} vs ${pareja2}</strong></p>
                        <div style="display:flex;justify-content:center;gap:1rem;margin:1rem 0;">
                            <div>
                                <label>${pareja1}</label>
                                <input type="number" id="juegos1" min="0" max="7" value="${j1}" style="width:80px;padding:0.4rem;text-align:center;">
                            </div>
                            <div>
                                <label>${pareja2}</label>
                                <input type="number" id="juegos2" min="0" max="7" value="${j2}" style="width:80px;padding:0.4rem;text-align:center;">
                            </div>
                        </div>
                        <div id="ganadora" style="margin:0.5rem 0;font-weight:bold;"></div>
                        <button class="action-btn" style="background:#2ECC71;margin-top:0.5rem;" onclick="guardarResultado(${idPartido}, '${pareja1}', '${pareja2}')">Guardar</button>
                        <button class="action-btn" style="background:#6c757d;margin-top:0.5rem;" onclick="volverAFixture()">Cancelar</button>
                    </div>
                `;
                document.getElementById('submodalContenido').innerHTML = html;
                document.getElementById('submodalGenerico').style.display = 'flex';

                // Actualizar ganadora
                document.getElementById('juegos1').addEventListener('input', actualizarGanadora);
                document.getElementById('juegos2').addEventListener('input', actualizarGanadora);
                actualizarGanadora();
            })
            .catch(err => {
                console.error('Error al cargar resultado:', err);
                alert('❌ No se pudo cargar el resultado actual');
                volverAFixture();
            });
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
        if (contenidoFixtureAnterior) {
            document.getElementById('submodalContenido').innerHTML = contenidoFixtureAnterior;
        } else if (typeof window.torneoActualId !== 'undefined') {
            verFixture(window.torneoActualId);
        } else {
            cerrarSubmodal();
        }
    }

    function cerrarSubmodal() {
        document.getElementById('submodalGenerico').style.display = 'none';
    }

    function verResultados(idTorneo) {
      fetch(`../api/get_resultados_torneo.php?id_torneo=${idTorneo}`)
          .then(r => r.json())
          .then(data => {
              if (!data || data.length === 0) {
                  alert('No hay resultados registrados');
                  return;
              }

              // Agrupar por fecha/hora (misma ronda)
              const rondas = {};
              data.forEach(partido => {
                  const key = new Date(partido.fecha_hora_programada).toISOString().split('T')[0] + '_' + 
                              new Date(partido.fecha_hora_programada).getHours();
                  if (!rondas[key]) rondas[key] = [];
                  rondas[key].push(partido);
              });

              let html = `<h3>📊 Resultados 🎾 Torneo</h3>`;
              html += `<table style="width:100%;border-collapse:collapse;margin-top:1rem;">`;
              html += `<thead><tr style="background:#071289;color:white;"><th>Ronda</th><th>Pareja</th><th>vs</th><th>Pareja</th><th>Ganadora</th></tr></thead><tbody>`;

              let numRonda = 1;
              Object.values(rondas).forEach(partidos => {
                  partidos.forEach(p => {
                      const ganador = (p.juegos1 > p.juegos2) ? p.pareja1 : p.pareja2;
                      html += `
                          <tr style="border-bottom:1px solid #ccc;">
                              <td>Set ${numRonda}</td>
                              <td>${p.pareja1} (${p.juegos1})</td>
                              <td>vs</td>
                              <td>${p.pareja2} (${p.juegos2})</td>
                              <td><strong>${ganador}</strong></td>
                          </tr>
                      `;
                  });
                  numRonda++;
              });

              html += `</tbody></table>`;
              html += `<button class="action-btn" style="margin-top:1rem;" onclick="cerrarSubmodal()">Cerrar</button>`;
              document.getElementById('submodalContenido').innerHTML = html;
              document.getElementById('submodalGenerico').style.display = 'flex';
          });
  }

  function verPosicionesTorneo(idTorneo) {
    fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`)
        .then(r => r.json())
        .then(data => {
            if (!data.posiciones || data.posiciones.length === 0) {
                alert('No hay datos de posiciones');
                return;
            }

            let html = `<h3>🏆 Cuadro Resultados – ${data.torneo_nombre}</h3>`;
            html += `<table style="width:100%;border-collapse:collapse;margin-top:1rem;">`;
            html += `<thead><tr style="background:#071289;color:white;"><th>Sets Ganados</th><th>Pareja</th></tr></thead><tbody>`;

            data.posiciones.forEach(p => {
                html += `
                    <tr style="border-bottom:1px solid #ccc;">
                        <td style="text-align:center;font-weight:bold;">${p.sets_ganados}</td>
                        <td>${p.nombre_pareja}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            html += `<button class="action-btn" style="margin-top:1rem;" onclick="volverAFixture()">Volver</button>`;
            document.getElementById('submodalContenido').innerHTML = html;
            document.getElementById('submodalGenerico').style.display = 'flex';
        })
        .catch(err => {
            console.error('Error al cargar posiciones:', err);
            alert('❌ Error al cargar el cuadro de resultados');
            volverAFixture();
        });
  }
  // === EVENTOS BOTONES PRINCIPALES ===
  document.getElementById('btnCrearTorneo')?.addEventListener('click', () => {
      window.location.href = 'crear_torneo.php';
  });

  document.getElementById('btnGestionCancha')?.addEventListener('click', () => {
      window.location.href = 'gestion_canchas.php';
  });

  document.getElementById('btnCalendarioReservas')?.addEventListener('click', () => {
      window.location.href = 'calendario_reservas.php';
  });

  // === MENÚ ADMIN ===
  function toggleMenuAdmin(event) {
      event.stopPropagation();
      const menu = document.getElementById('menuAdmin');
      menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  }

  // Cerrar menú al hacer clic fuera
  document.addEventListener('click', () => {
      const menu = document.getElementById('menuAdmin');
      if (menu) menu.style.display = 'none';
  });

  function eliminarParejaTorneo(idPareja) {
    if (!confirm('¿Estás seguro de eliminar esta pareja del torneo?\n\n⚠️ Esto NO elimina a los jugadores como socios, solo los retira del torneo.')) {
        return;
    }

    fetch('../api/eliminar_pareja_torneo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({id_pareja: idPareja})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Recargar el submodal de parejas
            const idTorneo = window.torneoActualId;
            if (idTorneo) verParejas(idTorneo); // o la función que muestra las parejas
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Error al eliminar la pareja');
    });
  }

  function irPanelTorneo(idTorneo) {
    window.location.href = 'panel_torneo.php?id=' + idTorneo;
  }

  // === FINALIZAR TORNEO Y CALCULAR RANKING ===
  function finalizarTorneoYCalcularRanking(idTorneo) {
      if (!confirm('¿Estás seguro de finalizar este torneo y calcular el ranking?')) return;

      // 1. Validar que todos los partidos estén finalizados
      fetch(`../api/validar_torneo_finalizado.php?id_torneo=${idTorneo}`)
      .then(r => r.json())
      .then(data => {
          if (!data.success) {
              alert('❌ ' + data.message);
              return;
          }

          // 2. Calcular ranking
          fetch('../api/calcular_ranking_torneo.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id_torneo: idTorneo })
          })
          .then(r => r.json())
          .then(res => {
              if (res.success) {
                  alert('✅ Ranking actualizado correctamente');
                  location.reload();
              } else {
                  alert('❌ Error al calcular ranking: ' + res.message);
              }
          })
          .catch(err => {
              console.error('Error:', err);
              alert('❌ Error al procesar el ranking');
          });
      })
      .catch(err => {
          console.error('Error validación:', err);
          alert('❌ Error al verificar estado del torneo');
      });
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