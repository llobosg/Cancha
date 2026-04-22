<?php
// pages/recinto_dashboard.php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol_actual = $_SESSION['recinto_rol'] ?? '';
$roles_validos = ['admin', 'asistente'];

if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_validos)) {
    header('Location: login_recintos.php');
    exit;
}

require_once __DIR__ . '/../includes/permisos.php';

// Obtener datos del usuario
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

// Cargar datos del recinto
$id_recinto = $_SESSION['id_recinto'];
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';

// Simulación de ingresos (Reemplazar con consulta real si es necesario)
$ingresos_mes = 1250000; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($recinto_nombre) ?> | CanchaSport</title>
  <style>
    :root { --bg-primary: #071289; --accent: #4ECDC4; --gold: #FFD700; --card-bg: rgba(255, 255, 255, 0.15); --text-light: white; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      color: var(--text-light);
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      padding: 0; /* Sin padding global para que el top-bar pegue */
    }
    .container { max-width: 1400px; margin: 0 auto; padding: 1rem; }
    
    /* Top Bar */
    .top-bar {
        background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
        padding: 1rem 2rem;
        box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2);
        display: flex; justify-content: space-between; align-items: center;
        position: sticky; top: 0; left: 0; width: 100%; z-index: 1000; margin: 0;
    }
    .brand-logo { color: white; font-weight: 900; font-size: 1.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.8rem; }
    .menu-btn { background: rgba(255,255,255,0.2); border: none; font-size: 1.8rem; cursor: pointer; color: white; padding: 0.4rem 0.8rem; border-radius: 8px; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background: white; border: 1px solid #eee; border-radius: 12px; z-index: 1001; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); animation: fadeIn 0.2s ease; }
    .dropdown-menu a { display: block; padding: 0.8rem 1rem; text-decoration: none; color: #333; transition: 0.2s; }
    .dropdown-menu a:hover { background-color: #f3e5f5; color: #AB47BC; }
    .btn-logout { text-decoration: none; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; font-weight: bold; }

    /* Layout General */
    .dashboard-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
    
    /* Tarjeta de Ingresos (Compacta y Alineada a la Derecha) */
    .income-card {
        background: linear-gradient(135deg, #2E7D32, #4CAF50);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        text-align: right;
        /* Ancho ajustado para ~13 dígitos + símbolo + espaciado */
        min-width: 220px; 
        max-width: 280px; 
        flex-shrink: 0; /* Evita que se encoja demasiado */
        margin-left: auto; /* Fuerza alineación a la derecha en flex container */
    }
    .income-title { font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.3rem; }
    .income-value { font-size: 1.8rem; font-weight: 900; line-height: 1.2; }
    .income-detail { font-size: 0.8rem; opacity: 0.8; margin-top: 0.2rem; }

    /* Acciones Rápidas */
    .quick-actions { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; width: 100%; }
    .action-btn { padding: 0.8rem 0.5rem; background: var(--accent); color: var(--bg-primary); border: none; border-radius: 10px; font-weight: bold; cursor: pointer; transition: transform 0.2s; text-align: center; }
    .action-btn:hover { transform: translateY(-2px); }

    /* Contenedor Planilla */
    .planilla-wrapper { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 1rem; }
    
    /* Estilos Planilla Interna */
    .planilla-header-controls { background: linear-gradient(90deg, #CE93D8, #AB47BC); padding: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: center; color: white; }
    .control-group { display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.25); padding: 0.4rem 1rem; border-radius: 20px; }
    .control-input { background: transparent; border: none; outline: none; color: white; font-weight: bold; text-align: center; width: 120px; }
    .control-btn { background: white; color: #8E24AA; border: none; padding: 0.3rem 0.8rem; border-radius: 15px; font-weight: bold; cursor: pointer; font-size: 0.8rem; }
    .control-select { background: rgba(255,255,255,0.9); color: #333; border: none; padding: 0.4rem; border-radius: 6px; font-size: 0.85rem; }
    
    .planilla-table-container { overflow-x: auto; max-height: 65vh; }
    .planilla-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 0.85rem; }
    .planilla-table th, .planilla-table td { border: 1px solid #ddd; text-align: center; padding: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .planilla-table th { background: #AB47BC; color: white; position: sticky; top: 0; z-index: 10; }
    .planilla-table td:first-child, .planilla-table th:first-child { position: sticky; left: 0; background: #f8f9fa; z-index: 5; border-right: 2px solid #ccc; color: #333; font-weight: bold; }
    .planilla-table thead th:first-child { z-index: 20; background: #AB47BC; color: white; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    
    /* Responsive Móvil */
    @media (max-width: 768px) {
        .dashboard-header { flex-direction: column; align-items: stretch; }
        .income-card { margin-left: 0; width: 100%; max-width: 100%; text-align: center; }
        .quick-actions { grid-template-columns: 1fr 1fr; }
    }

    /* Estilos específicos para la planilla de Reservas (pueden ser ajustados según el diseño final) */
    .planilla-table th, .planilla-table td {
    border: 1px solid #ddd;
    text-align: center;
    padding: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    }
    .planilla-table th {
        background: #AB47BC;
        color: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .planilla-table td:first-child, .planilla-table th:first-child {
        position: sticky;
        left: 0;
        background: #f8f9fa;
        z-index: 5;
        border-right: 2px solid #ccc;
        color: #333;
        font-weight: bold;
    }

    /* Modal Detalle (Base) */
    #modalDetalleReserva {
        z-index: 2000; /* Nivel base */
    }

    /* Modal Pago (Debe estar ENCIMA del detalle) */
    #modalPago {
        z-index: 2500; /* Nivel superior */
        display: none; /* Por defecto oculto */
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); /* Fondo oscuro */
        backdrop-filter: blur(5px); /* Efecto borroso */
        justify-content: center;
        align-items: center;
    }

    /* Contenido del Modal Pago */
    #modalPago .submodal-content {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        max-width: 500px;
        width: 90%;
        position: relative; /* Para posicionar la X */
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        animation: fadeIn 0.3s ease-out;
    }
  </style>
</head>
<body>

  <!-- TOP BAR -->
  <div class="top-bar">
      <a href="../index.php" class="brand-logo">CanchaSport</a>
      <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="position: relative;">
              <button class="menu-btn" onclick="toggleMenu(event)"></button>
              <div id="adminMenu" class="dropdown-menu">
                  <div style="padding: 0.8rem 1rem; border-bottom: 1px solid #f0f0f0; display:flex; justify-content:space-between;">
                      <span style="font-size: 0.8rem; font-weight: bold; color: #999;">MENÚ</span>
                      <span onclick="closeMenu()" style="cursor: pointer;">&times;</span>
                  </div>
                  <?php if ($rol_actual === 'admin'): ?>
                      <a href="gestion_asistentes.php" onclick="closeMenu()">👥 Gestionar Asistentes</a>
                  <?php endif; ?>
                  <a href="mantenedor_admin_recinto.php?id=<?= $usuario_actual['id_admin'] ?>" onclick="closeMenu()">⚙️ Mi Perfil</a>
              </div>
          </div>
          <a href="logout.php" class="btn-logout">Salir</a>
      </div>
  </div>

  <div class="container">
      
      <!-- Header Dashboard: Título + Tarjeta Ingresos (Solo Admin) -->
      <div class="dashboard-header">
          <div>
              <h1 style="color: white; font-size: 1.8rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Panel de Control</h1>
              <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Recinto: <?= htmlspecialchars($recinto_nombre) ?></p>
          </div>
          
          <?php if ($rol_actual === 'admin'): ?>
          <div class="income-card">
              <div class="income-title">Ingresos Este Mes</div>
              <div class="income-value">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
              <div class="income-detail">+12% vs mes anterior</div>
          </div>
          <?php endif; ?>
      </div>

      <!-- Acciones Rápidas (Solo Asistente) -->
      <?php if ($rol_actual === 'asistente'): ?>
      <div class="quick-actions">
          <button class="action-btn" id="btnGestionCancha">Crear Canchas 🎾</button>
          <button class="action-btn" id="btnTorneosActivos">Torneos Activos </button>
          <button class="action-btn" onclick="alert('Función en desarrollo: Reserva Manual')">Reserva Manual 📝</button>
      </div>

      <!-- === CONTENEDOR DE LA PLANILLA === -->
      <div class="planilla-wrapper" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 2rem;">
          
          <!-- Controles Superiores -->
          <div style="background: linear-gradient(90deg, #CE93D8, #AB47BC); padding: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: center; color: white;">
              <div style="display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.25); padding: 0.4rem 1rem; border-radius: 20px;">
                  <span style="font-size:0.8rem; font-weight:600;">Fecha:</span>
                  <input type="date" id="fechaPlanillaInput" style="background: transparent; border: none; outline: none; color: white; font-weight: bold; width: 130px;">
                  <button onclick="irAHoyPlanilla()" style="background: white; color: #8E24AA; border: none; padding: 0.3rem 0.8rem; border-radius: 15px; font-weight: bold; cursor: pointer; font-size: 0.8rem;">Hoy</button>
                  <button onclick="cambiarDiaPlanilla(-1)" style="background: rgba(255,255,255,0.9); border: none; width: 25px; height: 25px; border-radius: 50%; cursor: pointer;">&lt;</button>
                  <button onclick="cambiarDiaPlanilla(1)" style="background: rgba(255,255,255,0.9); border: none; width: 25px; height: 25px; border-radius: 50%; cursor: pointer;">&gt;</button>
              </div>
              
              <div style="display: flex; gap: 0.5rem;">
                  <select id="filtroDeporte" style="background: rgba(255,255,255,0.9); color: #333; border: none; padding: 0.5rem; border-radius: 6px;">
                      <option value="todos">Todos los deportes</option>
                      <option value="padel">Pádel</option>
                      <option value="futbol">Fútbol</option>
                      <option value="tenis">Tenis</option>
                      <!-- Agrega más según tus IDs -->
                  </select>
                  <select id="filtroEstado" style="background: rgba(255,255,255,0.9); color: #333; border: none; padding: 0.5rem; border-radius: 6px;">
                      <option value="">Todos los estados</option>
                      <option value="pagadas">Pagadas</option>
                      <option value="parcial">Pago Parcial</option>
                      <option value="no_pagadas">No Pagadas</option>
                  </select>
              </div>
          </div>

          <!-- Tabla -->
          <div style="overflow-x: auto; max-height: 65vh;">
              <table id="tablaPlanilla" class="planilla-table" style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 0.85rem;">
                  <!-- Se llena con JS -->
              </table>
          </div>
      </div>

      
      
      <!-- Panel Torneos (Oculto por defecto, se muestra con botón) -->
      <div id="panelTorneos" class="planilla-wrapper" style="display: none; margin-bottom: 1.5rem; background: rgba(255,255,255,0.95);">
          <div style="padding: 1rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
              <h3 style="margin: 0; color: #071289;">🏆 Torneos Americanos Activos</h3>
              <button onclick="document.getElementById('panelTorneos').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer;">&times;</button>
          </div>
          <div id="listaTorneos" style="padding: 1rem;">
              <p style="color:#666;">Cargando torneos...</p>
          </div>
      </div>
      <?php endif; ?>

      <!-- PLANILLA DE RESERVAS (Visible para Ambos) -->
      <div class="planilla-wrapper">
          <!-- Controles Planilla -->
          <div class="planilla-header-controls">
              <div class="control-group">
                  <span style="font-size:0.8rem; font-weight:600;">Fecha:</span>
                  <input type="date" id="fechaPlanillaInput" class="control-input" value="<?= date('Y-m-d') ?>">
                  <button onclick="irAHoyPlanilla()" class="control-btn">Hoy</button>
                  <button onclick="cambiarDiaPlanilla(-1)" class="control-btn">&lt;</button>
                  <button onclick="cambiarDiaPlanilla(1)" class="control-btn">&gt;</button>
              </div>
              <div class="control-group">
                  <select id="filtroDeporte" class="control-select">
                      <option value="todos" selected>Todos los deportes</option>
                      <option value="futbol">Fútbol</option>
                      <option value="padel">Pádel</option> 
                      <option value="tenis">Tenis</option>
                      <option value="voleyball">Voleyball</option>
                  </select>
                  <select id="filtroEstado" class="control-select">
                      <option value="">Todos los estados</option>
                      <option value="pagadas">Pagadas</option>
                      <option value="parcial">Pago Parcial</option>
                      <option value="no_pagadas">No Pagadas</option>
                      <option value="reservada">Reservadas</option>
                  </select>
              </div>
          </div>
          
          <!-- Tabla -->
          <div class="planilla-table-container">
              <table id="tablaPlanilla" class="planilla-table">
                  <!-- Se llena con JS -->
              </table>
          </div>
      </div>

  </div>

  <!-- Scripts -->
  <script>
    // Mapeo de ID_Deporte a Íconos (Basado en tu tabla SQL)
    const iconosDeporte = {
        1: '🎾',   // Pádel
        2: '🎾',   // Tenis (usamos misma raqueta o 🥎 si prefieres)
        3: '🏐',   // Volley
        4: '🏋️',   // Gimnasio
        5: '👤',   // Clases partic.
        6: '🏃',   // Entrenamiento
        7: '🏊',   // Natación
        8: '🧘',   // Pilates
        9: '💪',   // Pesas
        10: '⚽',  // Fútbol
        11: '⚽',  // Futbolito
        12: '⚽',  // Futsal
        13: '⚽',  // Escuela Fútbol
        14: '🏊',  // Escuela natación
        15: '🚴',  // Ciclismo
        'default': '🏟️' // Por defecto para cualquier otro deporte nuevo
    };

    // === INICIO CODIGO DE CALENDARIO_RESERVAS.JS INTEGRADO Y ADAPTADO PARA ESTE DASHBOARD ===

    // === VARIABLES GLOBALES PARA LA PLANILLA ===
    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    let estadoSeleccionadoPlanilla = "";
    let reservaActualSeleccionada = null; // Para guardar datos al hacer click

    // === FUNCIONES DE NAVEGACIÓN DE FECHA ===
    function cambiarDiaPlanilla(dias) {
        const fechaObj = new Date(fechaPlanillaActual);
        fechaObj.setDate(fechaObj.getDate() + dias);
        fechaPlanillaActual = fechaObj.toISOString().split('T')[0];
        
        const inputFecha = document.getElementById('fechaPlanillaInput');
        if (inputFecha) inputFecha.value = fechaPlanillaActual;
        
        cargarPlanillaReservas();
    }

    function irAHoyPlanilla() {
        fechaPlanillaActual = new Date().toISOString().split('T')[0];
        const inputFecha = document.getElementById('fechaPlanillaInput');
        if (inputFecha) inputFecha.value = fechaPlanillaActual;
        cargarPlanillaReservas();
    }

    // Listeners para los controles
    document.addEventListener('DOMContentLoaded', () => {
        const fechaInput = document.getElementById('fechaPlanillaInput');
        if (fechaInput) {
            fechaInput.value = fechaPlanillaActual;
            fechaInput.addEventListener('change', function() {
                fechaPlanillaActual = this.value;
                cargarPlanillaReservas();
            });
        }
        
        const filtroDeporte = document.getElementById('filtroDeporte');
        if (filtroDeporte) {
            filtroDeporte.addEventListener('change', cargarPlanillaReservas);
        }
        
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', function() {
                estadoSeleccionadoPlanilla = this.value;
                cargarPlanillaReservas();
            });
        }

        // Carga inicial
        cargarPlanillaReservas();
    });

    // === FUNCIÓN PRINCIPAL DE CARGA ===
    async function cargarPlanillaReservas() {
        const deporteSelect = document.getElementById('filtroDeporte');
        let deporte = deporteSelect ? deporteSelect.value : "todos";
        
        // Si viene vacío o "todos", la API debe manejarlo (asegúrate que tu API acepte 'todos' o vacío)
        if (!deporte) deporte = "todos";

        console.log(`📡 Cargando planilla... Fecha: ${fechaPlanillaActual}, Deporte: ${deporte}`);

        try {
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
            
            const response = await fetch(url, { credentials: 'include' });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            
            renderizarPlanilla(data);
            console.log("✅ Planilla cargada exitosamente");
            
        } catch (error) {
            console.error("❌ Error al cargar:", error);
            document.getElementById('tablaPlanilla').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red; text-align:center;">Error: ${error.message}</td></tr>`;
        }
    }

    // === FUNCIÓN DE RENDERIZADO (COPIADA Y ADAPTADA) ===
    function renderizarPlanilla(data) {
        const table = document.getElementById('tablaPlanilla');
        if (!table) return;

        if (!data.canchas || !data.canchas.length) {
            table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">No hay canchas operativas.</td></tr>';
            return;
        }

        let html = `<thead><tr>`;
        // Columna Hora Sticky
        html += `<th style="min-width:80px; background:#AB47BC; color:white; position:sticky; left:0; z-index:20;">Hora</th>`;

        // Headers de Canchas con Íconos
        data.canchas.forEach(c => {
            const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
            html += `
                <th style="min-width:120px; background:#AB47BC; color:white; font-size:0.9rem;">
                    <div style="font-size:0.8rem; margin-top:4px;">${c.nombre_cancha}</div>
                </th>
            `;
        });
        html += `</tr></thead><tbody>`;

        // Iterar Slots (Horarios)
        data.slots.forEach(slot => {
            if (slot.is_label_row) {
                html += `<tr>`;
                // Celda Hora
                html += `<td style="background:#f8f9fa; font-weight:bold; position:sticky; left:0; z-index:1; border-right:2px solid #ccc;">${slot.label}</td>`;
                
                // Celdas de Canchas
                data.canchas.forEach(cancha => {
                    const key = `${cancha.id_cancha}_${slot.label}`;
                    const res = data.reservas[key];
                    
                    let bgClass = '#e0e0e0'; // Gris (Disponible)
                    let cellContent = '';
                    let clickEvt = '';
                    let opacity = '1';

                    if (res) {
                        // Lógica de Colores Solicitada
                        if (res.estado_pago === 'pagado') {
                            bgClass = '#4CAF50'; // Verde
                        } else if (res.estado_pago === 'parcial') {
                            bgClass = '#FFC107'; // Amarillo
                        } else {
                            bgClass = '#F44336'; // Rojo (Pendiente/No pagada)
                        }
                        
                        const nombre = (res.nombre_cliente || res.nombre_socio || 'Reserva').substring(0, 10) + '..';
                        cellContent = `<div style="font-size:0.75rem; font-weight:bold; color:#333;">${nombre}</div>`;
                        
                        // Habilitar Click
                        if (res.id_reserva) {
                            clickEvt = `onclick="abrirDetalleDesdePlanilla(${res.id_reserva})"`;
                        }
                    }

                    // Filtro visual simple (opcional)
                    if (estadoSeleccionadoPlanilla) {
                        // Aquí podrías agregar lógica para atenuar si no cumple el filtro
                    }

                    html += `<td style="background:${bgClass}; height:45px; cursor:${clickEvt ? 'pointer' : 'default'}; opacity:${opacity};" ${clickEvt}>${cellContent}</td>`;
                });
                html += `</tr>`;
            }
        });

        html += `</tbody>`;
        table.innerHTML = html;
    }
    // === FIN CODIGO DE CALENDARIO_RESERVAS.JS INTEGRADO ===


    // --- Lógica Menú ---
    function toggleMenu(e) { e.stopPropagation(); const m = document.getElementById('adminMenu'); m.style.display = m.style.display === 'block' ? 'none' : 'block'; }
    function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
    document.addEventListener('click', () => { if(document.getElementById('adminMenu').style.display === 'block') closeMenu(); });

    // --- Lógica Botones Asistente ---
    <?php if ($rol_actual === 'asistente'): ?>
    document.getElementById('btnGestionCancha')?.addEventListener('click', () => window.location.href = 'gestion_canchas.php');
    document.getElementById('btnTorneosActivos')?.addEventListener('click', () => {
        const panel = document.getElementById('panelTorneos');
        if(panel.style.display === 'none') {
            panel.style.display = 'block';
            cargarTorneos(); // Cargar solo cuando se abre
        } else {
            panel.style.display = 'none';
        }
    });
    <?php endif; ?>

    // --- Lógica Planilla ---
    // Variables Globales
    let deporteSeleccionado = 'padel'; // Valor por defecto inicial

    // Función para cambiar día (Anterior / Siguiente)
    function cambiarDiaPlanilla(dias) {
        const fechaObj = new Date(fechaPlanillaActual);
        fechaObj.setDate(fechaObj.getDate() + dias);
        
        // Actualizar variable global
        fechaPlanillaActual = fechaObj.toISOString().split('T')[0];
        
        // Actualizar visualmente el input date
        const inputFecha = document.getElementById('fechaPlanillaInput');
        if (inputFecha) inputFecha.value = fechaPlanillaActual;
        
        console.log(`🔄 Cambiando fecha a: ${fechaPlanillaActual} | Deporte: ${deporteSeleccionado}`);
        
        // Cargar con los datos correctos
        cargarPlanillaReservas();
    }

    // Función Ir a Hoy
    function irAHoyPlanilla() {
        fechaPlanillaActual = new Date().toISOString().split('T')[0];
        document.getElementById('fechaPlanillaInput').value = fechaPlanillaActual;
        cargarPlanillaReservas();
    }

    // Listener para cambio manual de fecha
    document.getElementById('fechaPlanillaInput')?.addEventListener('change', function() {
        fechaPlanillaActual = this.value;
        cargarPlanillaReservas();
    });

    // Listener para cambio de deporte (Actualiza la variable global)
    document.getElementById('filtroDeporte')?.addEventListener('change', function() {
        deporteSeleccionado = this.value || 'todos'; // Si es vacío, usa todos o deja vacío según prefieras
        console.log(` Deporte cambiado a: ${deporteSeleccionado}`);
        cargarPlanillaReservas();
    });

    

    // Función Principal de Carga
    async function cargarPlanillaReservas() {
        // 1. Obtener deporte de la variable global (no del select directamente para evitar errores)
        // Pero sincronizamos por si el usuario cambió el select manualmente
        const selectDeporte = document.getElementById('filtroDeporte');
        if (selectDeporte && selectDeporte.value) {
            deporteSeleccionado = selectDeporte.value;
      }
        
        // Si sigue vacío tras todo, usamos el default
        if (!deporteSeleccionado) deporteSeleccionado = 'todos';

        console.log(` Cargando planilla... Fecha: ${fechaPlanillaActual}, Deporte: ${deporteSeleccionado}`);

        try {
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporteSeleccionado)}`;
            
            const response = await fetch(url, { credentials: 'include' });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            renderizarPlanilla(data);
            console.log("✅ Planilla cargada exitosamente");
            
        } catch (error) {
            console.error("❌ Error cargando planilla:", error);
            document.getElementById('tablaPlanilla').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red;">Error: ${error.message}</td></tr>`;
        }
    }

    // Función auxiliar por si la API no trae slots (ajusta horas según tu recinto)
    function generarSlotsBase() {
        const slots = [];
        for (let h = 8; h <= 23; h++) {
            const hora = h.toString().padStart(2, '0') + ':00';
            slots.push({ label: hora, is_label_row: true });
        }
        return slots;
    }

    // Cargar planilla al inicio
    document.addEventListener('DOMContentLoaded', cargarPlanillaReservas);

    // --- Función Cargar Torneos (Solo Asistente) ---
    <?php if ($rol_actual === 'asistente'): ?>
    async function cargarTorneos() {
        const contenedor = document.getElementById('listaTorneos');
        try {
            const res = await fetch('../api/get_torneos_recinto.php');
            const data = await res.json();
            if (data.error || data.length === 0) {
                contenedor.innerHTML = '<p>No hay torneos activos.</p>';
                return;
            }
            let html = '<div style="display:flex; flex-direction:column; gap:0.8rem;">';
            data.forEach(t => {
                html += `<div style="background:#f8f9fa; padding:1rem; border-radius:8px; border-left:4px solid #AB47BC;">
                    <strong>${t.nombre}</strong><br>
                    <small>${t.estado} | ${t.parejas_inscritas}/${t.num_parejas_max} parejas</small>
                </div>`;
            });
            html += '</div>';
            contenedor.innerHTML = html;
        } catch (e) {
            contenedor.innerHTML = '<p>Error al cargar torneos.</p>';
        }
    }
    <?php endif; ?>

    // Función para cerrar el modal (agrégala si no la tienes)
    function cerrarModalDetalleReserva() {
        document.getElementById('modalDetalleReserva').style.display = 'none';
    }

    // === FUNCIÓN ÚNICA PARA ABRIR DETALLE CON ACCIONES ===
    async function abrirDetalleDesdePlanilla(idReserva) {
        console.log("🖱️ Click en Reserva ID:", idReserva);
        if (!idReserva) return;

        const modal = document.getElementById('modalDetalleReserva');
        const container = document.getElementById('contenidoDetalle');
        
        // 1. Mostrar modal y estado de carga
        if (modal) modal.style.display = 'flex';
        if (container) container.innerHTML = '<p style="text-align:center;">Cargando...</p>';

        try {
            // 2. Llamar a API
            const formData = new URLSearchParams();
            formData.append('action', 'get_detalle_reserva');
            formData.append('id_reserva', idReserva);

            const response = await fetch('../api/canchaboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData,
                credentials: 'include'
            });

            const detalle = await response.json();
            if (detalle.error) throw new Error(detalle.error);

            window.reservaActualSeleccionada = detalle;

            // 3. Renderizar contenido dinámico + BOTONES
            if (container) {
                const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
                const money = (v) => '$' + parseInt(v || 0).toLocaleString();
                
                let estadoColor = detalle.estado_pago === 'pagado' ? 'green' : (detalle.estado_pago === 'parcial' ? 'orange' : 'red');

                container.innerHTML = `
                    <div style="font-size: 0.95rem; line-height: 1.6; color: #333;">
                        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h4 style="margin: 0; color: #0d47a1;">${val(detalle.fecha)}</h4>
                            <div style="font-size: 1.1rem; font-weight: bold;">${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div><strong>Cancha:</strong> ${val(detalle.nombre_cancha)}</div>
                            <div><strong>Cliente:</strong> ${val(detalle.nombre_cliente || 'N/A')}</div>
                        </div>
                        <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid #eee; display:flex; justify-content:space-between;">
                            <div><strong>Total:</strong> ${money(detalle.monto_total)}</div>
                            <div style="color:${estadoColor}; font-weight:bold;">${val(detalle.estado_pago).toUpperCase()}</div>
                        </div>
                    </div>

                    <!-- BOTONES DE ACCIÓN -->
                    <div style="margin-top:2rem; border-top:1px solid #eee; padding-top:1rem; text-align:center;">
                        <button onclick="toggleActionMenuModal()" style="background:#071289; color:white; border:none; padding:0.6rem 1.5rem; border-radius:8px; cursor:pointer; width:100%;">⚙️ Opciones</button>
                        <div id="actionMenuModal" style="display:none; margin-top:10px; border:1px solid #ddd; border-radius:8px;">
                            <button onclick="anularReserva()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee;">🗑️ Anular</button>
                            <button onclick="enviarMensaje()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee;">💬 Mensaje</button>
                            ${detalle.estado_pago !== 'pagado' ? 
                                `<button onclick="abrirModalPagoDesdeDetalle()" style="width:100%; padding:10px; border:none; background:#e8f5e9; color:green; text-align:left; font-weight:bold;">💳 Pagar</button>` : ''}
                        </div>
                    </div>
                `;
            }
        } catch (err) {
            if (container) container.innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
        }
    }

    function toggleActionMenuModal() {
        const menu = document.getElementById('actionMenuModal');
        if (menu) menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    // === FUNCIONES AUXILIARES PARA EL MENÚ DE ACCIONES ===
    function toggleActionMenuModal() {
        const menu = document.getElementById('actionMenuModal');
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    // === STUBS PARA LAS ACCIONES (Para que no den error al hacer click) ===
    function anularReserva() {
        if(confirm("¿Estás seguro de ANULAR esta reserva?")) {
            alert("Función Anular: Aquí iría la llamada a la API para anular.");
            // TODO: Implementar llamada API
        }
    }
    function cancelarReserva() {
        alert("Función Cancelar: En desarrollo.");
    }
    function cambiarCancha() {
        alert("Función Cambiar Cancha: En desarrollo.");
    }
    function enviarMensaje() {
        const modalMsg = document.getElementById('mensajeModal');
        if(modalMsg) modalMsg.style.display = 'flex';
    }
    function closeMensajeModal() {
        const modalMsg = document.getElementById('mensajeModal');
        if(modalMsg) modalMsg.style.display = 'none';
    }

    // === FUNCIONES DE NAVEGACIÓN DE MODALES ===
    // Función para la "X" del modal de pago: Vuelve al modal de Detalle
    function volverAlDetalle() {
        document.getElementById('modalPago').style.display = 'none';
        // El modalDetalleReserva debería seguir visible debajo
    }

    // Función para cerrar TODO (usada si quieres salir completamente)
    function cerrarModalPago() {
        document.getElementById('modalPago').style.display = 'none';
        document.getElementById('modalDetalleReserva').style.display = 'none';
    }

    // Función para abrir el modal de pago DESDE el detalle
    function abrirModalPagoDesdeDetalle() {
        if (!window.reservaActualSeleccionada) return;
        
        const detalle = window.reservaActualSeleccionada;
        const idReserva = detalle.id_reserva;
        const montoTotal = parseFloat(detalle.monto_total);

        // 1. Llenar datos
        document.getElementById('infoIdReserva').textContent = idReserva;
        document.getElementById('infoMontoTotal').textContent = '$' + montoTotal.toLocaleString();
        document.getElementById('montoPagar').value = montoTotal; // Pre-llenar con el total
        
        // Resetear form
        document.getElementById('formPago').reset();
        document.getElementById('montoPagar').value = montoTotal;
        document.getElementById('campoTransaccion').style.display = 'none';
        
        // Guardar IDs para el submit
        document.getElementById('formPago').dataset.idReserva = idReserva;
        document.getElementById('formPago').dataset.montoOriginal = montoTotal;

        // 2. Ocultar menú de acciones si está abierto
        const menu = document.getElementById('actionMenuModal');
        if (menu) menu.style.display = 'none';

        // 3. MOSTRAR modal de pago ENCIMA del detalle
        document.getElementById('modalPago').style.display = 'flex';
    }

    // Listener para mostrar/ocultar campo de transacción
    document.getElementById('metodoPago')?.addEventListener('change', function() {
        const campo = document.getElementById('campoTransaccion');
        const input = document.getElementById('transaccionId');
        if (['transferencia', 'webpay'].includes(this.value)) {
            campo.style.display = 'block';
            input.required = true;
        } else {
            campo.style.display = 'none';
            input.required = false;
        }
    });

    // Submit del formulario de pago (Lógica Parcial/Total)
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const idReserva = this.dataset.idReserva;
        const montoOriginal = parseFloat(this.dataset.montoOriginal);
        const montoPagado = parseFloat(document.getElementById('montoPagar').value);
        const metodo = document.getElementById('metodoPago').value;
        const transaccion = document.getElementById('transaccionId').value;
        const notas = document.getElementById('notasPago').value;

        if (montoPagado <= 0) { alert("El monto debe ser mayor a 0"); return; }

        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago_parcial'); // Acción que maneja parciales
            formData.append('id_reserva', idReserva);
            formData.append('monto_pagado', montoPagado);
            formData.append('monto_total_original', montoOriginal);
            formData.append('metodo_pago', metodo);
            formData.append('transaccion_id', transaccion || '');
            formData.append('notas_pago', notas);

            const res = await fetch('../api/gestion_reservas.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                let msg = "✅ Pago registrado correctamente.";
                if (montoPagado < montoOriginal) {
                    msg += " La reserva queda con saldo pendiente (Pago Parcial).";
                }
                alert(msg);
                
                // Cerrar modales y recargar para ver cambios en la planilla
                document.getElementById('modalPago').style.display = 'none';
                document.getElementById('modalDetalleReserva').style.display = 'none';
                location.reload(); 
            } else {
                alert("❌ Error: " + data.message);
            }
        } catch (err) {
            console.error(err);
            alert("❌ Error de conexión al procesar pago");
        }
    });

    // Función para cerrar TODO (usada por la X del modal detalle)
    function cerrarModalDetalle() {
        document.getElementById('modalDetalleReserva').style.display = 'none';
        document.getElementById('modalPago').style.display = 'none'; // Por seguridad
    }
  </script>
    <!-- === MODAL DETALLE DE RESERVA (ESTRUCTURA) === -->
    <div id="modalDetalleReserva" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center; backdrop-filter: blur(4px);">
        <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:600px; width:90%; position:relative; max-height:90vh; overflow-y:auto;">
            <!-- Botón Cerrar X -->
            <span class="close-modal" onclick="cerrarModalDetalle()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1.5rem; text-align:center; font-size:1.5rem;">📋 Detalle de Reserva</h3>
            
            <!-- AQUÍ LA FUNCIÓN JS INYECTARÁ LOS DATOS Y BOTONES -->
            <div id="contenidoDetalle" style="color:#333; width: 100%; box-sizing: border-box;">
                <p style="text-align:center;">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- === SUBMODAL DE PAGO COMPLETO === -->
    <div id="modalPago" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter: blur(5px);">
        <div style="background:white; padding:2rem; border-radius:16px; max-width:500px; width:90%; position:relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            
            <!-- Botón X para VOLVER AL DETALLE -->
            <span onclick="volverAlDetalle()" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; color:#999; line-height:1;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center; font-weight:bold;">💳 Registrar Pago</h3>
            
            <!-- Info Base -->
            <div style="margin-bottom:1.5rem; font-size:0.9rem; color:#555; background:#f8f9fa; padding:1rem; border-radius:8px; text-align:center; border:1px solid #eee;">
                <div style="margin-bottom:0.5rem;"><strong>Reserva ID:</strong> <span id="infoIdReserva">...</span></div>
                <div><strong>Monto Total:</strong> <span id="infoMontoTotal" style="font-weight:bold; color:#071289; font-size:1.1rem;">$0</span></div>
            </div>
            
            <form id="formPago">
                <!-- Monto Editable -->
                <div style="margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.4rem; color:#333;">💰 Monto a Abonar ($)</label>
                    <input type="number" id="montoPagar" name="monto_pagar" step="100" required 
                        style="width:100%; padding:0.8rem; border:2px solid #4CAF50; border-radius:8px; font-size:1.2rem; font-weight:bold; color:#2e7d32; text-align:right; box-sizing:border-box;">
                    <small style="color:#666; font-size:0.8rem; display:block; margin-top:0.3rem;">* Ingresa el monto total o un pago parcial.</small>
                </div>

                <!-- Método de Pago -->
                <div style="margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.4rem; color:#333;">Método de Pago</label>
                    <select name="metodo_pago" id="metodoPago" required style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc; background:white; color:#333; box-sizing:border-box;">
                        <option value="">Seleccionar...</option>
                        <option value="transferencia">Transferencia Bancaria</option>
                        <option value="webpay">Webpay / Tarjeta</option>
                        <option value="efectivo">Efectivo en Recinto</option>
                        <option value="convenio">Convenio Club</option>
                    </select>
                </div>
                
                <!-- ID Transacción (Condicional) -->
                <div id="campoTransaccion" style="display:none; margin-bottom:1rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.4rem; color:#333;">Nº Comprobante / ID Transacción</label>
                    <input type="text" id="transaccionId" placeholder="Ej: 123456789" style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc; box-sizing:border-box;">
                </div>

                <!-- Notas / Comentarios -->
                <div style="margin-bottom:1.5rem;">
                    <label style="font-weight:bold; display:block; margin-bottom:0.4rem; color:#333;">📝 Notas del Pago</label>
                    <textarea id="notasPago" rows="3" placeholder="Ej: Pago parcial de Juan Pérez (1/4). Faltan 3 socios." 
                            style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc; resize:vertical; font-family:sans-serif; box-sizing:border-box;"></textarea>
                </div>
                
                <button type="submit" style="width:100%; background:#4CAF50; color:white; border:none; padding:1rem; border-radius:8px; font-weight:bold; cursor:pointer; font-size:1rem; transition:0.2s;">
                    Confirmar Registro de Pago
                </button>
            </form>
        </div>
    </div>

     <!-- Modal para enviar mensaje -->
     <div id="mensajeModal" class="submodal" style="display:none;">
        <div class="submodal-content" style="max-width: 400px;">
            <span class="close-modal" onclick="closeMensajeModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">💬 Enviar Mensaje</h3>
            
            <form id="formMensaje">
                <div class="form-group">
                    <label>Mensaje para el cliente</label>
                    <textarea id="mensajeCliente" rows="4" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc;" placeholder="Escribe tu mensaje aquí..."></textarea>
                </div>
                <button type="submit" class="btn-submit" style="width:100%; background:#071289; color:white; border:none; padding:0.8rem; border-radius:8px; font-weight:bold; cursor:pointer;">Enviar Mensaje</button>
            </form>
        </div>
    </div>

</body>
</html>