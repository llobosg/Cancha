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

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    /* === ESTILOS DE TABLA Y COLUMNAS FIJAS === */
    .planilla-table {
        width: auto; /* IMPORTANTE: Que la tabla mida lo que midan sus columnas, no el 100% */
        min-width: 100%; /* Pero que al menos ocupe todo el ancho disponible si hay muchas canchas */
        border-collapse: separate; 
        border-spacing: 4px;
        table-layout: fixed; /* CLAVE: Respeta los anchos definidos estrictamente */
    }

   /* Columna de HORAS (Sticky Left) */
    .planilla-table th:first-child,
    .planilla-table td:first-child {
        position: sticky;
        left: 0;
        z-index: 20;
        background: #f8f9fa !important;
        color: #333;
        font-weight: bold;
        border-right: 2px solid #e0e0e0;
        border-radius: 6px;
        
        /* ANCHO FIJO E INVARIABLE */
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
        
        padding: 4px !important;
        font-size: 0.75rem;
        text-align: center;
    }

    /* Columnas de CANCHAS */
    .planilla-table th, 
    .planilla-table td {
        /* ANCHO FIJO PARA CADA CANCHA */
        width: 120px !important;
        min-width: 120px !important;
        max-width: 120px !important;
        
        padding: 4px;
        vertical-align: middle;
        text-align: center;
    }

    /* Ajuste para Móvil */
    @media (max-width: 768px) {
        .planilla-table th:first-child,
        .planilla-table td:first-child {
            width: 60px !important;
            min-width: 60px !important;
            max-width: 60px !important;
            font-size: 0.7rem;
        }
        /* Columnas de CANCHAS */
        .planilla-table th, 
        .planilla-table td {
            width: 100px !important;
            min-width: 100px !important; /* Un poco más anchas para leer nombres cortos */
            max-width: 100px !important;
        }
        .dashboard-header { flex-direction: column; align-items: stretch; }
        .income-card { margin-left: 0; width: 100%; max-width: 100%; text-align: center; }
        .quick-actions { grid-template-columns: 1fr 1fr; }

        /* Contenedor con scroll horizontal si es necesario */
        .planilla-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            max-height: 70vh;
            padding: 4px; 
            /* Si quieres que el fondo gris se vea a la derecha cuando sobra espacio: */
            background-color: #f4f6f9; 
        }

        /* Tabla */
        .planilla-table {
            font-size: 0.75rem; /* Fuente más pequeña */
        }

        /* Encabezados de Canchas */
        .planilla-table thead th div {
            font-size: 0.7rem; /* Icono más pequeño */
        }
        .planilla-table thead th div:last-child {
            font-size: 0.65rem; /* Nombre de cancha más pequeño */
            white-space: normal; /* Permite que el nombre baje de línea si es largo */
            line-height: 1.1;
        }

        /* Contenido de la celda (Nombre Cliente) */
        .planilla-table tbody td div {
            font-size: 0.65rem;
            line-height: 1.1;
        }
    }

    /* Encabezados de Canchas */
    .planilla-table thead th {
        background: #AB47BC !important;
        color: white;
        position: sticky;
        top: 0;
        z-index: 5;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        height: 60px; /* Altura fija para headers */
    }

    /* === CELDAS DE RESERVA (El toque UX) === */
    .planilla-table tbody td {
        transition: all 0.2s ease;
        border-radius: 8px; /* Borde redondeado suave */
        border: 1px solid transparent; /* Borde invisible por defecto */
    }

    /* Efecto Hover en celdas */
    .planilla-table tbody td:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        z-index: 2;
        position: relative;
    }

    /* Colores de Estado con Bordes Suaves */
    /* Verde (Pagado) */
    td.estado-pagado {
        background-color: #4CAF50 !important; /* <--- CAMBIA ESTO (Verde Estándar) */
        border: 1px solid #388E3C !important;
        color: white; /* Texto blanco */
    }

    /* Amarillo (Parcial) */
    td.estado-parcial {
        background-color: #FFEB3B !important; /* <--- CAMBIA ESTO (Amarillo Intenso) */
        border: 1px solid #FBC02D !important;
        color: #333; /* Texto oscuro para que se lea bien sobre amarillo */
    }

    /* Rojo (Reservada/Pendiente) */
    td.estado-pendiente {
        background-color: #FF5252 !important; /* <--- CAMBIA ESTO (Rojo Intenso) */
        border: 1px solid #D32F2F !important;
        color: white; /* Texto blanco para mejor contraste con rojo fuerte */
    }

    /* Gris (Disponible) */
    td.estado-disponible {
        background-color: #FAFAFA !important;
        border: 1px dashed #E0E0E0 !important; /* Línea punteada sutil */
    }

    /* Separación visual entre filas (Líneas de cuadrícula) */
    .planilla-table tbody tr td {
        border-bottom: 1px solid #f0f0f0; /* Línea horizontal muy suave */
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
          <a href="recinto_logout.php" class="btn-logout">Salir</a>
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

    // === VARIABLES GLOBALES ===
    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    let estadoSeleccionadoPlanilla = ""; // Inicializar vacío

    // === LISTENERS CORREGIDOS (Sin Loop) ===
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Listener Fecha
        const fechaInput = document.getElementById('fechaPlanillaInput');
        if (fechaInput) {
            fechaInput.value = fechaPlanillaActual;
            fechaInput.addEventListener('change', function() {
                fechaPlanillaActual = this.value;
                cargarPlanillaReservas();
            });
        }

        // 2. Listener Deporte
        const filtroDeporte = document.getElementById('filtroDeporte');
        if (filtroDeporte) {
            filtroDeporte.addEventListener('change', function() {
                // No necesitamos guardar deporte en variable global si la API lo lee directo del select,
                // pero sí llamamos a cargar.
                cargarPlanillaReservas();
            });
        }

        // 3. Listener Estado (CORREGIDO)
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', function() {
                estadoSeleccionadoPlanilla = this.value; // Guardar estado seleccionado
                console.log(`🔍 Filtro Estado cambiado a: '${estadoSeleccionadoPlanilla}'`);
                cargarPlanillaReservas();
            });
        }

        // 4. Carga Inicial
        cargarPlanillaReservas();
    });

    // === FUNCIÓN DE CARGA OPTIMIZADA ===
    async function cargarPlanillaReservas() {
        const deporteSelect = document.getElementById('filtroDeporte');
        const deporte = deporteSelect ? deporteSelect.value : "todos";
        
        // Evitar llamadas duplicadas si ya está cargando (opcional, pero útil)
        // if (window.isLoadingPlanilla) return;
        // window.isLoadingPlanilla = true;

        console.log(`📡 Cargando planilla... Fecha: ${fechaPlanillaActual}, Deporte: ${deporte}, Estado: ${estadoSeleccionadoPlanilla}`);

        try {
            // La API debe recibir el estado también si queremos filtrar desde el backend, 
            // O bien, la API devuelve todo y JS filtra. 
            // NOTA: Tu API actual 'get_planilla_reservas' NO recibe estado. 
            // Por lo tanto, el filtrado por estado debe hacerse en JS (renderizarPlanilla) 
            // O debemos actualizar la API. 
            
            // OPCIÓN A: Filtrado en Frontend (Más rápido si no son miles de reservas)
            // Pedimos todos los datos según fecha/deporte y filtramos visualmente al renderizar.
            
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
            
            const response = await fetch(url, { credentials: 'include' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            
            // Pasamos el estado seleccionado a la función de renderizado
            renderizarPlanilla(data, estadoSeleccionadoPlanilla);
            
        } catch (error) {
            console.error("❌ Error al cargar:", error);
            document.getElementById('tablaPlanilla').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red; text-align:center;">Error: ${error.message}</td></tr>`;
        } finally {
            // window.isLoadingPlanilla = false;
        }
    }

    // === RENDERIZADO CON FILTRO DE ESTADO CORREGIDO ===
    function renderizarPlanilla(data, filtroEstado) {
        const table = document.getElementById('tablaPlanilla');
        if (!table) return;

        if (!data.canchas || !data.canchas.length) {
            table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">No hay canchas operativas para esta selección.</td></tr>';
            return;
        }

        let html = `<thead><tr>`;
        // Header Hora
        html += `<th style="background:#AB47BC; color:white; position:sticky; left:0; z-index:20;">Hora</th>`;

        // Headers Canchas
        data.canchas.forEach(c => {
            const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
            html += `
                <th style="background:#AB47BC; color:white; font-size:0.8rem;">
                    <div style="font-size:0.7rem; margin-top:2px; white-space:normal;">${c.nombre_cancha}</div>
                </th>
            `;
        });
        html += `</tr></thead><tbody>`;

        const hoy = new Date(); hoy.setHours(0,0,0,0);

        data.slots.forEach(slot => {
            if (slot.is_label_row) {
                html += `<tr>`;
                // Celda Hora
                html += `<td style="background:#f8f9fa; font-weight:bold; position:sticky; left:0; z-index:1; border-right:2px solid #ccc; font-size:0.75rem;">${slot.label}</td>`;
                
                data.canchas.forEach(cancha => {
                    const key = `${cancha.id_cancha}_${slot.label}`;
                    const res = data.reservas[key];
                    
                    let bgClass = 'estado-disponible';
                    let cellContent = '';
                    let clickEvt = '';
                    let opacity = '1';
                    let cumpleFiltro = true;

                    if (res) {
                        // 1. Determinar Estado Lógico Real
                        let estadoLogico = '';
                        if (res.estado_pago === 'pagado') estadoLogico = 'pagadas';
                        else if (res.estado_pago === 'parcial') estadoLogico = 'parcial';
                        else {
                            // Si no está pagado, depende de la fecha
                            const fechaRes = new Date(res.fecha + 'T00:00:00');
                            estadoLogico = (fechaRes < hoy) ? 'no_pagadas' : 'reservada';
                        }

                        // 2. Aplicar Filtro de Estado
                        if (filtroEstado && filtroEstado !== '') {
                            // Si el filtro es 'disponible', ocultamos las reservadas
                            if (filtroEstado === 'disponible') cumpleFiltro = false;
                            // Si el filtro es específico, debe coincidir
                            else if (filtroEstado !== estadoLogico) cumpleFiltro = false;
                        }

                        // 3. Definir apariencia si cumple filtro
                        if (cumpleFiltro) {
                            if (res.estado_pago === 'pagado') bgClass = 'estado-pagado';
                            else if (res.estado_pago === 'parcial') bgClass = 'estado-parcial';
                            else bgClass = 'estado-pendiente';

                            const nombre = (res.nombre_cliente || res.nombre_socio || 'Reserva').substring(0, 15); // Limitar a 15 caracteres
                            cellContent = `<div style="font-size:0.7rem; font-weight:bold;">${nombre}</div>`;
                            
                            if (res.id_reserva) {
                                clickEvt = `onclick="abrirDetalleDesdePlanilla(${res.id_reserva})"`;
                            }
                        } else {
                            // NO CUMPLE FILTRO: Atenuar y vaciar
                            opacity = '0.05'; // Casi invisible
                            cellContent = '';
                            clickEvt = '';
                        }
                    } else {
                        // Es Disponible
                        if (filtroEstado && filtroEstado !== 'disponible' && filtroEstado !== '') {
                            // Si filtramos por algo que no sea disponible, ocultamos las libres
                            opacity = '0.05';
                        }
                    }

                    html += `<td class="${bgClass}" style="height:40px; cursor:${clickEvt ? 'pointer' : 'default'}; opacity:${opacity};" ${clickEvt}>${cellContent}</td>`;
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
        const deporteSelect = document.getElementById('filtroDeporte');
        const deporte = deporteSelect ? deporteSelect.value : "todos";
        
        console.log(`📡 Cargando planilla... Fecha: ${fechaPlanillaActual}, Deporte: ${deporte}`);

        try {
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
            
            const response = await fetch(url, { credentials: 'include' });
            
            // === DETECCIÓN DE SESIÓN EXPIRADA ===
            if (response.status === 401) {
                console.warn("⚠️ Sesión expirada. Redirigiendo al login...");
                showToast("Tu sesión ha expirado. Por favor, inicia sesión nuevamente.", "warning");
                
                // Esperar 2 segundos para que el usuario lea el mensaje y redirigir
                setTimeout(() => {
                    window.location.href = 'login_recintos.php';
                }, 2000);
                return; // Detener la ejecución
            }

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            
            renderizarPlanilla(data, estadoSeleccionadoPlanilla);
            console.log("✅ Planilla cargada exitosamente");
            
        } catch (error) {
            console.error("❌ Error al cargar:", error);
            // Solo mostrar error si NO es un 401 (porque el 401 ya redirige)
            if (error.message !== 'Acceso no autorizado') {
                document.getElementById('tablaPlanilla').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red; text-align:center;">Error: ${error.message}</td></tr>`;
            }
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

    // === FUNCIÓN ÚNICA PARA ABRIR DETALLE CON NOTAS Y ACCIONES ===
    async function abrirDetalleDesdePlanilla(idReserva) {
        console.log("🖱️ Click en Reserva ID:", idReserva);
        
        if (!idReserva) {
            alert("Error: ID de reserva inválido");
            return;
        }

        // 1. Mostrar modal inmediatamente con estado de carga
        const modal = document.getElementById('modalDetalleReserva');
        const container = document.getElementById('contenidoDetalle');
        
        if (modal) modal.style.display = 'flex';
        if (container) container.innerHTML = '<p style="text-align:center; color:#666;">Cargando detalles...</p>';

        try {
            // 2. Llamar a la API
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

            // 3. Guardar globalmente para usar en las acciones (ej. Pagar)
            window.reservaActualSeleccionada = detalle;

            // 4. Renderizar contenido bonito + NOTAS + BOTONES
            if (container) {
                const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
                const money = (v) => '$' + parseInt(v || 0).toLocaleString();

                // Cálculos financieros
                const montoTotal = parseFloat(detalle.monto_total || 0);
                const montoRecaudado = parseFloat(detalle.monto_recaudacion || 0);
                const saldoPendiente = montoTotal - montoRecaudado;
                const esParcial = (detalle.estado_pago === 'parcial');

                // Color del estado
                let estadoColor = 'red';
                if (detalle.estado_pago === 'pagado') estadoColor = 'green';
                else if (detalle.estado_pago === 'parcial') estadoColor = '#F57F17';

                // Construcción del HTML
                let html = `
                    <div style="font-size: 0.95rem; line-height: 1.6; color: #333;">
                        <!-- Encabezado Fecha/Hora -->
                        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h4 style="margin: 0; color: #0d47a1;">${val(detalle.fecha)}</h4>
                            <div style="font-size: 1.1rem; font-weight: bold;">${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</div>
                        </div>
                        
                        <!-- Datos Principales -->
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div><strong>Cancha:</strong> ${val(detalle.nombre_cancha)}</div>
                            <div><strong>Deporte:</strong> ${val(detalle.id_deporte)}</div>
                            <div style="grid-column: span 2;"><strong>Cliente:</strong> ${val(detalle.nombre_cliente || detalle.nombre_responsable)}</div>
                            <div style="grid-column: span 2;"><strong>Contacto:</strong> ${val(detalle.telefono_cliente)}</div>
                        </div>

                        <!-- Sección Financiera Detallada -->
                        <div style="background: #fafafa; padding: 1rem; border-radius: 8px; border: 1px solid #eee; margin-bottom: 1rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                                <span style="color:#666; font-size:0.9rem;">Monto Total</span>
                                <span style="font-weight:bold; font-size:1.1rem;">${money(montoTotal)}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                                <span style="color:#666; font-size:0.9rem;">Abonado</span>
                                <span style="font-weight:bold; color:#2e7d32;">${money(montoRecaudado)}</span>
                            </div>
                            
                            ${esParcial ? `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding-top:0.5rem; border-top:1px dashed #ccc;">
                                <span style="color:#c62828; font-size:0.9rem; font-weight:bold;">Saldo Pendiente</span>
                                <span style="font-weight:bold; color:#c62828; font-size:1.1rem;">${money(saldoPendiente)}</span>
                            </div>
                            ` : ''}
                            
                            <div style="margin-top:0.5rem; text-align:right;">
                                <span style="font-size:0.8rem; color:#666;">Estado: </span>
                                <span style="font-weight:bold; color:${estadoColor}; text-transform:uppercase;">${val(detalle.estado_pago)}</span>
                            </div>
                        </div>
                `;

                // === SECCIÓN DE NOTAS (Destacada si es parcial o si hay notas) ===
                const notas = val(detalle.notas, '');
                if (notas && notas !== 'null') {
                    const bgNota = esParcial ? '#FFF3E0' : '#FFFDE7';
                    const borderNota = esParcial ? '#FFB74D' : '#FFF59D';
                    
                    html += `
                        <div style="background: ${bgNota}; padding: 0.8rem; border-radius: 6px; border-left: 4px solid ${borderNota}; margin-bottom: 1rem;">
                            <div style="font-size: 0.8rem; font-weight: bold; color: #555; margin-bottom: 0.3rem; text-transform: uppercase;">
                                📝 Historial / Notas
                            </div>
                            <div style="font-size: 0.9rem; color: #333; white-space: pre-wrap; font-family: sans-serif;">
                                ${notas}
                            </div>
                        </div>
                    `;
                }

                html += `</div>`; // Cierre del contenedor principal de datos

                // Inyectar HTML base
                container.innerHTML = html;

                // === AGREGAR BOTONES DE ACCIÓN AL FINAL ===
                const actionContainer = document.createElement('div');
                actionContainer.style.marginTop = '1rem';
                actionContainer.style.textAlign = 'center';
                actionContainer.innerHTML = `
                    <button onclick="toggleActionMenuModal()" style="background:#071289; color:white; border:none; padding:0.6rem 1.5rem; border-radius:8px; cursor:pointer; width:100%; font-weight:bold;">
                        ⚙️ Opciones de Gestión
                    </button>
                    <div id="actionMenuModal" style="display:none; margin-top:10px; border:1px solid #ddd; border-radius:8px; background:white; overflow:hidden;">
                        <button onclick="anularReserva()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee; cursor:pointer;">🗑️ Anular Reserva</button>
                        <button onclick="cancelarReserva()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee; cursor:pointer;">❌ Cancelar Reserva</button>
                        <button onclick="cambiarCancha()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee; cursor:pointer;">🔄 Cambiar Cancha</button>
                        ${detalle.estado_pago !== 'pagado' ? 
                            `<button onclick="abrirModalPagoDesdeDetalle()" style="width:100%; padding:10px; border:none; background:#e8f5e9; color:#2e7d32; text-align:left; font-weight:bold; cursor:pointer;">💳 Pagar / Abonar</button>` 
                            : ''}
                    </div>
                `;
                container.appendChild(actionContainer);
            }

        } catch (err) {
            console.error(err);
            if (container) container.innerHTML = `<p style="color:red; text-align:center;">Error: ${err.message}</p>`;
        }
    }

    // Función auxiliar para el menú de acciones
    function toggleActionMenuModal() {
        const menu = document.getElementById('actionMenuModal');
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
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
    <!-- === SISTEMA DE TOAST NOTIFICATIONS === -->
    <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

    <script>
    // Función para mostrar Toast
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        
        // Estilos según tipo
        const bg = type === 'success' ? 'linear-gradient(135deg, #4CAF50, #2E7D32)' : 
                  (type === 'warning' ? 'linear-gradient(135deg, #FF9800, #EF6C00)' : 'linear-gradient(135deg, #F44336, #C62828)');
        
        toast.style.cssText = `
            background: ${bg};
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-weight: bold;
            font-family: sans-serif;
            animation: slideIn 0.3s ease-out forwards;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        
        // Icono
        const icon = type === 'success' ? '✅' : (type === 'warning' ? '⚠️' : '❌');
        toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
        
        container.appendChild(toast);
        
        // Eliminar después de 4 segundos
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Animaciones CSS para Toast
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    `;
    document.head.appendChild(style);

    // === FUNCIONES DE MODALES Y PAGO ===

    // Volver al detalle desde el modal de pago
    function volverAlDetalle() {
        document.getElementById('modalPago').style.display = 'none';
        document.getElementById('modalDetalleReserva').style.display = 'flex';
    }

    // Abrir modal de pago desde el detalle
    function abrirModalPagoDesdeDetalle() {
        if (!window.reservaActualSeleccionada) return;
        
        const detalle = window.reservaActualSeleccionada;
        const idReserva = detalle.id_reserva;
        const montoTotal = parseFloat(detalle.monto_total);

        // Llenar datos
        document.getElementById('infoIdReserva').textContent = idReserva;
        document.getElementById('infoMontoTotal').textContent = '$' + montoTotal.toLocaleString();
        document.getElementById('montoPagar').value = montoTotal;
        
        // Resetear form
        document.getElementById('formPago').reset();
        document.getElementById('montoPagar').value = montoTotal;
        document.getElementById('campoTransaccion').style.display = 'none';
        
        // Guardar IDs para el submit
        document.getElementById('formPago').dataset.idReserva = idReserva;
        document.getElementById('formPago').dataset.montoOriginal = montoTotal;

        // Ocultar menú de acciones y mostrar modal de pago
        const menu = document.getElementById('actionMenuModal');
        if (menu) menu.style.display = 'none';
        
        document.getElementById('modalDetalleReserva').style.display = 'none'; // Ocultar detalle
        document.getElementById('modalPago').style.display = 'flex'; // Mostrar pago
    }

    // Listener para método de pago (mostrar campo transacción)
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

    // SUBMIT DEL FORMULARIO DE PAGO (Conexión con API y Toast)
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const idReserva = this.dataset.idReserva;
        const montoOriginal = parseFloat(this.dataset.montoOriginal);
        const montoPagado = parseFloat(document.getElementById('montoPagar').value);
        const metodo = document.getElementById('metodoPago').value;
        const transaccion = document.getElementById('transaccionId').value;
        const notas = document.getElementById('notasPago').value;

        if (montoPagado <= 0) { 
            showToast("El monto debe ser mayor a 0", "error"); 
            return; 
        }

        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago_parcial');
            formData.append('id_reserva', idReserva);
            formData.append('monto_pagado', montoPagado);
            formData.append('monto_total_original', montoOriginal);
            formData.append('metodo_pago', metodo);
            formData.append('transaccion_id', transaccion || '');
            formData.append('notas_pago', notas);

            console.log(`📝 [AUDITORÍA] Iniciando pago Reserva ID: ${idReserva} | Monto: $${montoPagado}`);

            const res = await fetch('../api/gestion_reservas.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                let msg = "✅ Pago registrado correctamente.";
                let type = "success";

                if (montoPagado < montoOriginal) {
                    msg = `⚠️ Pago Parcial registrado. Faltan $${(montoOriginal - montoPagado).toLocaleString()}.`;
                    type = "warning";
                } else {
                    msg = "✅ ¡Reserva Pagada Completamente!";
                }

                showToast(msg, type);
                console.log(`✅ [AUDITORÍA] Éxito. Reserva ${idReserva} actualizada.`);

                // Cerrar modales
                document.getElementById('modalPago').style.display = 'none';
                document.getElementById('modalDetalleReserva').style.display = 'none';

                // Recargar la planilla para ver el cambio de color
                if (typeof cargarPlanillaReservas === 'function') {
                    cargarPlanillaReservas();
                } else {
                    location.reload();
                }

            } else {
                showToast("❌ Error: " + data.message, "error");
                console.error(`❌ [AUDITORÍA] Error: ${data.message}`);
            }
        } catch (err) {
            console.error(err);
            showToast("❌ Error de conexión al procesar pago", "error");
        }
    });

    // Funciones auxiliares de cierre
    function cerrarModalDetalle() {
        document.getElementById('modalDetalleReserva').style.display = 'none';
    }
    function cerrarModalPago() {
        document.getElementById('modalPago').style.display = 'none';
    }
    </script>
</body>
</html>