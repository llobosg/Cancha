<?php
// pages/recinto_dashboard.php

// 1. Incluir config.php (Maneja sesión y DB)
require_once __DIR__ . '/../includes/config.php';

// 2. Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Validación de Roles (Admin y Asistente)
$rol_actual = $_SESSION['recinto_rol'] ?? '';
$roles_validos = ['admin', 'asistente'];

if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_validos)) {
    // Si no tiene rol válido, redirigir al login
    header('Location: login_recintos.php');
    exit;
}

// Cargar datos del usuario logueado
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

// Cargar datos del recinto
$id_recinto = $_SESSION['id_recinto'];
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';

// Simulación de datos para Ingresos (Reemplazar con consulta real más adelante)
$ingresos_mes = 1250000; 
$variacion_mes = "+12%";
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
      /* Eliminamos padding global para que el top-bar pegue arriba */
      padding: 0; 
    }
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 1rem; /* Padding interno solo para el contenido */
    }
    
    /* --- TOP BAR CORREGIDO --- */
    .top-bar {
        background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
        padding: 1rem 2rem;
        box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0; /* Pegado arriba */
        left: 0;
        width: 100%;
        z-index: 1000;
        margin: 0; /* Sin margen externo */
    }
    .brand-logo {
        color: white;
        font-weight: 900;
        font-size: 1.5rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .menu-container { position: relative; }
    .menu-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        font-size: 1.8rem;
        cursor: pointer;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        transition: 0.2s;
    }
    .menu-btn:hover { background: rgba(255,255,255,0.3); }
    
    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 120%;
        background: white;
        border: 1px solid #eee;
        border-radius: 12px;
        z-index: 1001;
        min-width: 220px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        animation: fadeIn 0.2s ease;
    }
    .dropdown-header {
        padding: 0.8rem 1rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        background: #fafafa;
    }
    .dropdown-menu a {
        display: block;
        padding: 0.8rem 1rem;
        text-decoration: none;
        color: #333;
        transition: 0.2s;
        font-weight: 500;
    }
    .dropdown-menu a:hover {
        background-color: #f3e5f5;
        color: #AB47BC;
    }
    .btn-logout {
        text-decoration: none;
        padding: 0.6rem 1.2rem;
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 8px;
        font-weight: bold;
        font-size: 0.9rem;
        transition: 0.2s;
        backdrop-filter: blur(5px);
    }
    .btn-logout:hover { background: rgba(255,255,255,0.3); }

    /* --- ESTILOS GENERALES DASHBOARD --- */
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid rgba(255,255,255,0.3);
    }
    .stats-grid {
      display: grid;
      gap: 1.2rem;
      margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
      .stats-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
    }
    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(10px);
      padding: 1.2rem;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }
    .stat-title {
      font-size: 0.95rem;
      opacity: 0.9;
      margin-bottom: 0.8rem;
    }
    .chart { height: 60px; margin: 0.5rem 0; }
    
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
    .action-btn:hover { transform: translateY(-2px); }
    
    .dynamic-panel {
      background: var(--card-bg);
      padding: 1.5rem;
      border-radius: 14px;
      min-height: 200px;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- TOP BAR CANCHASPORT -->
  <div class="top-bar">
      <a href="../index.php" class="brand-logo">
          <span style="font-size: 1.8rem;">🏟️</span> CanchaSport
      </a>
      
      <div style="display: flex; align-items: center; gap: 1rem;">
          <!-- Menú Kebab -->
          <div class="menu-container">
              <button class="menu-btn" onclick="toggleMenuAdmin(event)">⋮</button>
              
              <div id="menuAdmin" class="dropdown-menu">
                  <div class="dropdown-header">
                      <span style="font-size: 0.8rem; font-weight: bold; color: #999; text-transform: uppercase;">Menú</span>
                      <span onclick="closeMenuAdmin()" style="cursor: pointer; font-size: 1.2rem; color: #999;">&times;</span>
                  </div>
                  
                  <!-- Opción Solo Admin -->
                  <?php if ($rol_actual === 'admin'): ?>
                      <a href="gestion_asistentes.php" onclick="closeMenuAdmin()">
                          👥 Gestionar Asistentes
                      </a>
                  <?php endif; ?>
                  
                  <!-- Opción Todos -->
                  <a href="mantenedor_admin_recinto.php?id=<?= $usuario_actual['id_admin'] ?>" onclick="closeMenuAdmin()">
                      ️ Mi Perfil
                  </a>
              </div>
          </div>
          
          <a href="logout.php" class="btn-logout">Salir</a>
      </div>
  </div>

  <div class="container">
    
    <!-- VISTA ADMIN: Tarjeta de Ingresos -->
    <?php if ($rol_actual === 'admin'): ?>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); margin-top: 1rem;">
            <div class="stat-card" style="background: linear-gradient(135deg, #2E7D32, #4CAF50); color: white;">
                <div class="stat-title" style="color: rgba(255,255,255,0.9);">Ingresos Este Mes</div>
                <div style="font-size: 2.2rem; font-weight: 900; margin: 0.5rem 0;">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
                <div style="font-size: 0.9rem; color: #A8E6CF;"><?= $variacion_mes ?> vs mes anterior</div>
            </div>
            <!-- Aquí podrías agregar más tarjetas financieras si quieres -->
        </div>
    <?php endif; ?>

    <!-- VISTA ASISTENTE: Acciones Rápidas -->
    <?php if ($rol_actual === 'asistente'): ?>
        <div class="quick-actions" style="margin-top: 1rem;">
            <button class="action-btn" id="btnGestionCancha">Crear Canchas 🎾</button>
            <!-- Botón Calendario eliminado porque cargamos la planilla automático -->
            <button class="action-btn" onclick="alert('Función en desarrollo: Reserva Manual')">Reserva Manual</button>
            <button class="action-btn" id="btnCrearTorneo">Crear Torneo 🎾</button>
        </div>
    <?php endif; ?>

    <!-- Panel de Torneos (Común o específico según necesites) -->
    <div class="dynamic-panel" id="panelTorneos" style="margin-top: 1rem;">
      <h3 style="margin-top: 0; color: white;"> Torneos Activos</h3>
      <div id="listaTorneos" style="margin-top: 1rem; color: #ddd;">
        <p>Cargando torneos...</p>
      </div>
    </div>

    <!-- AQUÍ SE CARGARÁ LA PLANILLA AUTOMÁTICAMENTE -->
    <!-- Puedes incluir un iframe o un div donde JS inyecte la tabla -->
    <div id="contenedorPlanilla" style="margin-top: 2rem; background: white; border-radius: 12px; min-height: 400px; overflow: hidden;">
        <!-- La lógica de carga de la planilla iría aquí -->
        <div style="padding: 2rem; text-align: center; color: #333;">
            <h3> Cargando Planilla de Reservas...</h3>
            <p>Si no carga, verifica la conexión a la API.</p>
        </div>
    </div>

  </div>

  <script>
    // Lógica del Menú Kebab
    function toggleMenuAdmin(event) {
        event.stopPropagation();
        const menu = document.getElementById('menuAdmin');
        const isVisible = menu.style.display === 'block';
        menu.style.display = isVisible ? 'none' : 'block';
    }

    function closeMenuAdmin() {
        document.getElementById('menuAdmin').style.display = 'none';
    }

    // Cerrar menú al hacer click fuera
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('menuAdmin');
        const button = event.target.closest('.menu-btn');
        if (!button && menu.style.display === 'block') {
            closeMenuAdmin();
        }
    });

    // Eventos de Botones de Acción (Asistente)
    document.getElementById('btnGestionCancha')?.addEventListener('click', () => {
        window.location.href = 'gestion_canchas.php';
    });

    document.getElementById('btnCrearTorneo')?.addEventListener('click', () => {
        window.location.href = 'crear_torneo.php';
    });

    // NOTA: Aquí deberías llamar a la función que carga la planilla
    // Ejemplo: cargarPlanillaEnDashboard();
  </script>
</body>
</html>