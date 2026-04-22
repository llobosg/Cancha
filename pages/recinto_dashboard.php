<?php
// pages/recinto_dashboard.php

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rol_actual = $_SESSION['recinto_rol'] ?? 'NO_EXISTE';
$id_recinto_actual = $_SESSION['id_recinto'] ?? 'NO_EXISTE';

// Validación de roles
$roles_validos = ['admin', 'asistente'];
if (!isset($_SESSION['id_recinto']) || !isset($_SESSION['recinto_rol']) || !in_array($rol_actual, $roles_validos)) {
    header('Location: login_recintos.php');
    exit;
}

require_once __DIR__ . '/../includes/permisos.php';

// Obtener datos del usuario
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

// Obtener datos del recinto
$id_recinto = $_SESSION['id_recinto'];
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';

// === FIX ERROR: Definir ingresos_mes si no existe la lógica aún ===
// Si eres Admin, aquí deberías hacer la query SQL real. Por ahora ponemos 0 para evitar el crash.
$ingresos_mes = 0; 
if (esAdmin()) {
    // Ejemplo de cómo sería la consulta real (descomentar cuando tengas la tabla de pagos lista):
    // $stmt_ing = $pdo->prepare("SELECT SUM(monto_total) FROM reservas WHERE id_recinto = ? AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND estado_pago = 'pagado'");
    // $stmt_ing->execute([$id_recinto]);
    // $ingresos_mes = $stmt_ing->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - <?= htmlspecialchars($recinto_nombre) ?> | CanchaSport</title>
  <style>
    :root { --bg-primary: #071289; --accent: #4ECDC4; --gold: #FFD700; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      color: white;
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
    }
    /* Top Bar Styles */
    .top-bar {
        background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
        padding: 1rem 2rem;
        box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    .brand-logo { color: white; font-weight: 900; font-size: 1.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.8rem; }
    .menu-container { position: relative; }
    .menu-btn { background: rgba(255,255,255,0.2); border: none; font-size: 1.8rem; cursor: pointer; color: white; padding: 0.4rem 0.8rem; border-radius: 8px; }
    .dropdown-menu {
        display: none; position: absolute; right: 0; top: 120%; background: white; border: 1px solid #eee;
        border-radius: 12px; z-index: 1001; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .dropdown-menu a { display: block; padding: 0.8rem 1rem; text-decoration: none; color: #333; border-radius: 8px; }
    .dropdown-menu a:hover { background-color: #f3e5f5; color: #AB47BC; }
    .btn-logout { text-decoration: none; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; font-weight: bold; }
    
    /* Dashboard Content */
    .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
    .planilla-frame {
        width: 100%;
        height: 82vh;
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        background: white;
    }
    .header-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .badge-role { background: white; color: #333; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.9rem; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
  </style>
</head>
<body>

  <!-- TOP BAR -->
  <div class="top-bar">
      <a href="../index.php" class="brand-logo">🏟️ CanchaSport</a>
      
      <div style="display: flex; align-items: center; gap: 1rem;">
          <div class="menu-container">
              <button class="menu-btn" onclick="toggleMenu(event)">⋮</button>
              <div id="adminMenu" class="dropdown-menu">
                  <div style="padding: 0.8rem 1rem; border-bottom: 1px solid #f0f0f0; display:flex; justify-content:space-between;">
                      <span style="font-size: 0.8rem; font-weight: bold; color: #999;">MENÚ</span>
                      <span onclick="closeMenu()" style="cursor: pointer;">&times;</span>
                  </div>
                  <?php if (esAdmin()): ?>
                      <a href="gestion_asistentes.php" onclick="closeMenu()"> Gestionar Asistentes</a>
                  <?php endif; ?>
                  <a href="mantenedor_admin_recinto.php?id=<?= $usuario_actual['id_admin'] ?>" onclick="closeMenu()">⚙️ Mi Perfil</a>
              </div>
          </div>
          <a href="logout.php" class="btn-logout">Salir</a>
      </div>
  </div>

  <!-- CONTENIDO PRINCIPAL -->
  <div class="container">
      <div class="header-info">
          <h2 style="color: #333; font-size: 1.4rem; font-weight: 800; text-shadow: 0 1px 2px rgba(255,255,255,0.5);">📅 Planilla de Reservas - Hoy</h2>
          <div class="badge-role">Rol: <?= ucfirst($rol_actual) ?></div>
      </div>

      <!-- IFRAME QUE CARGA LA PLANILLA -->
      <!-- Apunta a calendario_reservas.php con parámetro embed=true para ocultar su propio header -->
      <iframe src="calendario_reservas.php?embed=true&fecha=<?= date('Y-m-d') ?>" class="planilla-frame" title="Planilla de Reservas"></iframe>
  </div>

  <script>
      function toggleMenu(e) {
          e.stopPropagation();
          const menu = document.getElementById('adminMenu');
          menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
      }
      function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
      document.addEventListener('click', () => { document.getElementById('adminMenu').style.display = 'none'; });
  </script>
</body>
</html>