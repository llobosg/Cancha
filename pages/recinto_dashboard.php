<?php
  // pages/recinto_dashboard.php

 // 1. Incluir config.php
  require_once __DIR__ . '/../includes/config.php';

  // 2. Iniciar sesión
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  // 3. Debug: Ver qué tenemos exactamente
  $rol_actual = $_SESSION['recinto_rol'] ?? 'NO_EXISTE';
  $id_recinto_actual = $_SESSION['id_recinto'] ?? 'NO_EXISTE';

  error_log(" [DASHBOARD] Verificando sesión...");
  error_log("   - id_recinto: " . var_export($id_recinto_actual, true));
  error_log("   - rol: '" . $rol_actual . "' (Tipo: " . gettype($rol_actual) . ")");

  // 4. Validación corregida
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

  // Configuración consistente de sesión (Opcional si ya está en config.php, pero seguro tenerlo aquí)
  if (session_status() === PHP_SESSION_NONE) {
      session_set_cookie_params([
          'lifetime' => 86400,
          'path' => '/',
          'domain' => '',
          'secure' => isset($_SERVER['HTTPS']),
          'httponly' => true,
          'samesite' => 'Lax'
      ]);  
  }

  // ✅ CORRECCIÓN: Validar nuevos roles (admin, asistente)
  $rol_actual = $_SESSION['recinto_rol'] ?? '';
  $roles_validos = ['admin', 'asistente']; // Nuevos roles permitidos

  if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_validos)) {
      // Si no tiene rol válido, redirigir al login de recintos (no al index general)
      header('Location: login_recintos.php');
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
  <!-- TOP BAR CANCHASPORT (Unificado) -->
  <div class="top-bar" style="background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%); padding: 1rem 2rem; box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000;">
      
      <!-- Logo / Marca -->
      <a href="../index.php" class="brand-logo" style="color: white; font-weight: 900; font-size: 1.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.8rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
          <span style="font-size: 1.8rem;">🏟️</span> CanchaSport
      </a>
      
      <!-- Menú de Usuario y Sesión -->
      <div style="display: flex; align-items: center; gap: 1rem;">
          
          <!-- Botón Menú Desplegable (Kebab) -->
          <div style="position: relative;">
              <button onclick="toggleMenuAdmin(event)" style="background: rgba(255,255,255,0.2); border: none; font-size: 1.8rem; cursor: pointer; color: white; line-height: 1; padding: 0.4rem 0.8rem; border-radius: 8px; transition: 0.2s;" title="Opciones">
                  ⋮
              </button>

              <!-- Menú Desplegable -->
              <div id="menuAdmin" style="display: none; position: absolute; right: 0; top: 120%; background: white; border: 1px solid #eee; border-radius: 12px; z-index: 1001; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; animation: fadeIn 0.2s ease;">
                  
                  <!-- Cabecera del menú con X -->
                  <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1rem; border-bottom: 1px solid #f0f0f0; background: #fafafa;">
                      <span style="font-size: 0.8rem; font-weight: bold; color: #999; text-transform: uppercase;">Menú</span>
                      <span onclick="closeMenuAdmin()" style="cursor: pointer; font-size: 1.2rem; color: #999; font-weight: bold; line-height: 1;" title="Cerrar">&times;</span>
                  </div>

                  <!-- Opciones -->
                  <div style="padding: 0.5rem;">
                      <?php if (esAdmin()): ?>
                          <a href="gestion_asistentes.php" onclick="closeMenuAdmin()" style="display: block; padding: 0.8rem 1rem; text-decoration: none; color: #333; border-radius: 8px; transition: 0.2s; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                              👥 Gestionar Asistentes
                          </a>
                      <?php endif; ?>

                      <a href="mantenedor_admin_recinto.php?id=<?= $usuario_actual['id_admin'] ?>" onclick="closeMenuAdmin()" style="display: block; padding: 0.8rem 1rem; text-decoration: none; color: #333; border-radius: 8px; transition: 0.2s; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                          ️ Mi Perfil
                      </a>
                  </div>
              </div>
          </div>

          <!-- Botón Cerrar Sesión -->
          <a href="logout.php" style="text-decoration: none; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; font-weight: bold; font-size: 0.9rem; transition: 0.2s; backdrop-filter: blur(5px);">
              🚪 Salir
          </a>
      </div>
  </div>

  <!-- Animación CSS y Script para el menú (Solo si no los tienes ya en el head/body) -->
  <style>
  @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
  }
  #menuAdmin a:hover {
      background-color: #f3e5f5; /* Lila muy suave */
      color: #AB47BC;
  }
  </style>

  <script>
      function toggleMenuAdmin(event) {
          event.stopPropagation();
          const menu = document.getElementById('menuAdmin');
          const isVisible = menu.style.display === 'block';
          menu.style.display = isVisible ? 'none' : 'block';
      }

      function closeMenuAdmin() {
          document.getElementById('menuAdmin').style.display = 'none';
      }

      document.addEventListener('click', function(event) {
          const menu = document.getElementById('menuAdmin');
          const button = event.target.closest('button[onclick="toggleMenuAdmin(event)"]');
          if (!button && menu.style.display === 'block') {
              closeMenuAdmin();
          }
      });
  </script>

  <script>
    <?php if (esAdmin()): ?>
        <div class="dashboard-section">
            <h2>Gestión de Asistentes</h2>
            <button onclick="abrirModalNuevoAsistente()">+ Nuevo Asistente</button>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Fecha Ingreso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_asistentes as $asistente): ?>
                    <tr>
                        <td><?= htmlspecialchars($asistente['nombre']) ?></td>
                        <td><?= htmlspecialchars($asistente['email']) ?></td>
                        <td><?= date('d/m/Y', strtotime($asistente['fecha_asignacion'])) ?></td>
                        <td>
                            <button onclick="editarAsistente(<?= $asistente['id_socio'] ?>)">Editar</button>
                            <button onclick="eliminarAsistente(<?= $asistente['id_socio'] ?>)" style="background:#f44336;">Dar de Baja</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

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

    async function cargarPlanillaReservas() {
      const fechaParaUsar = window.fechaPlanillaActual || new Date().toISOString().split('T')[0];
      const deporteSelect = document.getElementById('filtroDeporte');
      const deporte = deporteSelect ? (deporteSelect.value || "") : "";
      
      console.log(`📡 Iniciando carga... Fecha: ${fechaParaUsar}, Deporte: ${deporte}`);
      console.log(` Cookie de sesión actual: ${document.cookie ? 'Presente' : 'Ausente'}`); // Debug útil

      try {
          // Asegúrate que la ruta '../api/...' sea correcta desde donde está alojado recinto_dashboard.php
          const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaParaUsar}&deporte=${encodeURIComponent(deporte)}`;
          
          const response = await fetch(url, {
              method: 'GET',
              credentials: 'include', // ✅ Esto envía las cookies
              headers: {
                  'Content-Type': 'application/json' // Opcional, pero buena práctica
              }
          });
          
          if (response.status === 401) {
              const errorText = await response.text();
              console.error("❌ Error 401 Detallado:", errorText);
              throw new Error("Sesión expirada o no válida. Por favor inicia sesión nuevamente.");
          }

          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          
          const data = await response.json();
          
          if (data.error) throw new Error(data.error);
          
          renderizarPlanilla(data);
          console.log("✅ Planilla cargada");
          
      } catch (error) {
          console.error("❌ Error:", error);
          alert('Error al cargar datos: ' + error.message);
      }
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