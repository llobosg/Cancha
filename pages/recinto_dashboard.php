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
  $ingresos_mes = 0; 

  // Cargar datos del recinto
  $id_recinto = $_SESSION['id_recinto'];
  $stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
  $stmt_recinto->execute([$id_recinto]);
  $recinto = $stmt_recinto->fetch();
  $recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';

  // === CÁLCULO DE KPIs FINANCIEROS Y OPERATIVOS ===

  // Fechas clave
  $hoy = date('Y-m-d');
  $primer_dia_mes_actual = date('Y-m-01');
  $primer_dia_mes_anterior = date('Y-m-01', strtotime('-1 month'));
  $ultimo_dia_mes_anterior = date('Y-m-t', strtotime('-1 month'));

  // Función auxiliar para ejecutar consultas de suma
  function getSumaReservas($pdo, $id_recinto, $condicion_fecha, $condicion_pago, $params = []) {
      $sql = "SELECT COALESCE(SUM(r.monto_total), 0) as total 
              FROM reservas r 
              JOIN canchas c ON r.id_cancha = c.id_cancha 
              WHERE c.id_recinto = ? 
              AND r.fecha $condicion_fecha 
              AND r.estado_pago $condicion_pago 
              AND r.estado != 'cancelada'"; // Excluir canceladas
      
      // Si la condición de pago requiere parámetros (ej: IN ('pagado', 'parcial'))
      // Ajustamos la query dinámicamente si es necesario, pero para simplificar usaremos strings directos seguros
      
      $stmt = $pdo->prepare($sql);
      // Merge params con id_recinto
      $final_params = array_merge([$id_recinto], $params);
      $stmt->execute($final_params); // Nota: La query arriba usa ? directo, ajustemos para seguridad
      
      // Re-escribiendo para usar PDO seguro con placeholders si fuera complejo, 
      // pero como las condiciones son fijas, podemos inyectarlas con cuidado o usar prepare simple.
      // Para este ejemplo, usaremos una aproximación segura simple:
      
      $query = "SELECT COALESCE(SUM(r.monto_total), 0) as total 
                FROM reservas r 
                JOIN canchas c ON r.id_cancha = c.id_cancha 
                WHERE c.id_recinto = :id_recinto 
                AND r.fecha $condicion_fecha 
                AND r.estado_pago $condicion_pago 
                AND r.estado != 'cancelada'";
                
      $stmt = $pdo->prepare($query);
      $stmt->execute([':id_recinto' => $id_recinto]);
      return $stmt->fetchColumn();
  }

  // 1. INGRESOS ESTE MES (Pagados)
  $ingresos_mes_actual = getSumaReservas($pdo, $id_recinto, ">= '$primer_dia_mes_actual'", "= 'pagado'");
  $ingresos_mes_anterior = getSumaReservas($pdo, $id_recinto, "BETWEEN '$primer_dia_mes_anterior' AND '$ultimo_dia_mes_anterior'", "= 'pagado'");

  // Calcular % Variación
  $variacion_ingresos = 0;
  if ($ingresos_mes_anterior > 0) {
      $variacion_ingresos = (($ingresos_mes_actual - $ingresos_mes_anterior) / $ingresos_mes_anterior) * 100;
  } elseif ($ingresos_mes_actual > 0) {
      $variacion_ingresos = 100; // De 0 a algo es 100% crecimiento
  }

  // 2. PAGO PARCIAL (Acumulado del mes actual)
  $parcial_mes_actual = getSumaReservas($pdo, $id_recinto, ">= '$primer_dia_mes_actual'", "= 'parcial'");

  // 3. EN RESERVA (Futuras, No Pagadas)
  // Fecha > hoy Y Estado Pago != pagado
  $en_reserva_query = "SELECT COUNT(*) FROM reservas r 
                      JOIN canchas c ON r.id_cancha = c.id_cancha 
                      WHERE c.id_recinto = :id_recinto 
                      AND r.fecha > '$hoy' 
                      AND r.estado_pago != 'pagado' 
                      AND r.estado != 'cancelada'";
  $stmt_en_reserva = $pdo->prepare($en_reserva_query);
  $stmt_en_reserva->execute([':id_recinto' => $id_recinto]);
  $cantidad_en_reserva = $stmt_en_reserva->fetchColumn();

  // 4. DEUDA (Vencidas, No Pagadas)
  // Fecha < hoy Y Estado Pago != pagado
  $deuda_query = "SELECT COALESCE(SUM(r.monto_total), 0) as total FROM reservas r 
                  JOIN canchas c ON r.id_cancha = c.id_cancha 
                  WHERE c.id_recinto = :id_recinto 
                  AND r.fecha < '$hoy' 
                  AND r.estado_pago != 'pagado' 
                  AND r.estado != 'cancelada'";
  $stmt_deuda = $pdo->prepare($deuda_query);
  $stmt_deuda->execute([':id_recinto' => $id_recinto]);
  $monto_deuda = $stmt_deuda->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - <?= htmlspecialchars($recinto_nombre) ?> | CanchaSport</title>
    <style>
      /* =========================================
      1. RESET Y BASE
      ========================================= */
      :root { --bg-primary: #071289; --accent: #4ECDC4; --font-main: 'Segoe UI', sans-serif; }
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body {
          background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
          background-blend-mode: multiply;
          color: white; font-family: var(--font-main); min-height: 100vh; padding: 0; overflow-x: hidden;
      }

      /* =========================================
        2. TOP BAR
        ========================================= */
      .top-bar {
          background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
          padding: 0.8rem 1.5rem; display: flex; justify-content: space-between; align-items: center;
          position: sticky; top: 0; left: 0; width: 100%; z-index: 1000; box-shadow: 0 4px 12px rgba(186, 104, 200, 0.2);
      }
      .brand-logo { color: white; font-weight: 900; font-size: 1.5rem; text-decoration: none; display: flex; align-items: center; gap: 0.8rem; }
      .menu-btn { background: rgba(255,255,255,0.2); border: none; font-size: 1.8rem; cursor: pointer; color: white; padding: 0.4rem 0.8rem; border-radius: 8px; }
      .dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background: white; border-radius: 12px; z-index: 1001; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
      .dropdown-menu a { display: block; padding: 0.8rem 1rem; text-decoration: none; color: #333; }
      .btn-logout { text-decoration: none; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; font-weight: bold; }

      /* =========================================
        3. LAYOUT PRINCIPAL (FORZADO PARA 12 CANCHAS)
        ========================================= */
      .main-layout {
          display: grid;
          /* Acciones (180px) | Planilla (AUTO/FLEX) | KPIs (180px) */
          grid-template-columns: 180px 1fr 180px; 
          gap: 1rem;
          width: 99%; margin: 0 auto; padding: 0.5rem;
          height: calc(100vh - 70px);
      }

      /* Columna Izquierda: Acciones */
      .actions-column { display: flex; flex-direction: column; gap: 1rem; }
      .action-btn-sidebar {
          background: white; color: #071289; border: none; padding: 0.8rem; border-radius: 8px;
          font-weight: bold; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 10px;
      }

      /* Columna Central: Planilla (EL CONTENEDOR CLAVE) */
      .planilla-column {
          background: white; border-radius: 12px; display: flex; flex-direction: column;
          overflow: hidden; height: 100%; position: relative;
      }

      /* Controles Superiores Planilla */
      .planilla-header-controls { 
          background: linear-gradient(90deg, #CE93D8, #AB47BC); padding: 0.6rem 1rem;
          display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: space-between; color: white; 
      }
      .control-group { display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.25); padding: 0.3rem 0.8rem; border-radius: 15px; }
      .control-input { background: transparent; border: none; outline: none; color: white; font-weight: bold; text-align: center; width: 100px; }
      .control-btn { background: white; color: #8E24AA; border: none; border-radius: 4px; padding: 0.3rem 0.6rem; font-weight: bold; cursor: pointer; }
      .control-select { background: rgba(255,255,255,0.9); border: none; border-radius: 4px; padding: 0.3rem; font-size: 0.8rem; color: #333; }

      /* TABLA CONTAINER: AQUÍ ESTÁ LA MAGIA PARA LAS 12 CANCHAS */
      .planilla-table-container {
          flex: 1; overflow: auto; padding: 4px;
          /* FORZAMOS ANCHO MÍNIMO PARA QUE QUEPAN 12 CANCHAS + HORA */
          /* 12 canchas * 110px + 1 hora * 60px = 1380px. Ponemos 1400px para seguridad */
          min-width: 1400px !important; 
          width: max-content !important; /* Importante: que la tabla mida lo que mide su contenido */
          background-color: #f4f6f9;
      }

      /* Columna Derecha: KPIs (ANGOSTOS) */
      .kpi-column {
          display: flex; flex-direction: column; gap: 0.6rem; overflow-y: auto;
      }
      .kpi-card-mini {
          background: white; border-left: 3px solid #ccc; padding: 0.6rem; border-radius: 6px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #333; transition: transform 0.2s;
      }
      .kpi-card-mini:hover { transform: translateX(-2px); }
      .kpi-card-mini div:first-child { font-size: 0.65rem; text-transform: uppercase; font-weight: bold; opacity: 0.8; }
      .kpi-card-mini div:nth-child(2) { font-size: 1.1rem; font-weight: 900; line-height: 1; margin: 0.1rem 0; }
      .kpi-card-mini div:last-child { font-size: 0.6rem; opacity: 0.7; }

      /* Colores KPI */
      .kpi-ingresos { border-left-color: #4CAF50; background: #E8F5E9; }
      .kpi-ingresos div:nth-child(2) { color: #1B5E20 !important; }
      .kpi-parcial { border-left-color: #FBC02D; background: #FFFDE7; cursor: pointer; }
      .kpi-parcial div:nth-child(2) { color: #EF6C00 !important; }
      .kpi-reserva { border-left-color: #2196F3; background: #E3F2FD; }
      .kpi-reserva div:nth-child(2) { color: #0D47A1 !important; }
      .kpi-deuda { border-left-color: #EF5350; background: #FFEBEE; cursor: pointer; }
      .kpi-deuda div:nth-child(2) { color: #B71C1C !important; }

      /* =========================================
        4. ESTILOS DE TABLA (SOBREESCRITURA FUERTE)
        ========================================= */
      .planilla-table {
          width: 100%; border-collapse: separate; border-spacing: 3px; table-layout: fixed;
      }

      /* Hora Sticky */
      .planilla-table th:first-child,
      .planilla-table td:first-child {
          position: sticky; left: 0; z-index: 20;
          background: #f8f9fa !important; color: #333; font-weight: bold;
          border-right: 2px solid #e0e0e0; border-radius: 4px;
          width: 60px !important; min-width: 60px !important; max-width: 60px !important;
          padding: 2px !important; font-size: 0.7rem; text-align: center;
      }

      /* Canchas (Ancho Fijo Reducido para que quepan 12) */
      .planilla-table th, .planilla-table td {
          width: 110px !important; min-width: 110px !important; max-width: 110px !important;
          padding: 2px; vertical-align: middle; text-align: center; border-radius: 6px;
      }

      .planilla-table thead th {
          background: #AB47BC !important; color: white; position: sticky; top: 0; z-index: 5;
          border-radius: 6px; height: 50px; font-size: 0.75rem;
      }

      /* Estados */
      td.estado-pagado { background-color: #4CAF50 !important; border: 1px solid #388E3C !important; color: white; }
      td.estado-parcial { background-color: #FFEB3B !important; border: 1px solid #FBC02D !important; color: #333; }
      td.estado-pendiente { background-color: #FF5252 !important; border: 1px solid #D32F2F !important; color: white; }
      td.estado-disponible { background-color: #FAFAFA !important; border: 1px dashed #E0E0E0 !important; }

      /* Hover */
      .planilla-table tbody td:hover { transform: scale(1.02); box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 2; position: relative; }

      /* =========================================
        5. MODALES
        ========================================= */
      #modalDetalleReserva { z-index: 2000; }
      #modalPago {
          z-index: 2500; display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
          background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center;
      }
      #modalPago .submodal-content {
          background: white; padding: 1.5rem; border-radius: 12px; max-width: 450px; width: 90%; position: relative;
      }
      #modalListaKPI {
          display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6);
          z-index:3000; justify-content:center; align-items:center; backdrop-filter: blur(4px);
      }

      /* =========================================
        6. RESPONSIVE
        ========================================= */
      @media (max-width: 1024px) {
          .main-layout { grid-template-columns: 1fr !important; height: auto; }
          .actions-column { flex-direction: row; overflow-x: auto; }
          .kpi-column { flex-direction: row; overflow-x: auto; }
          .kpi-card-mini { min-width: 120px; }
      }
      @media (max-width: 768px) {
          .top-bar { padding: 0.6rem 1rem; }
          .brand-logo { font-size: 1.1rem; }
          .kpi-column { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; }
          .kpi-card-mini { min-width: auto; padding: 0.5rem; }
          .planilla-table th:first-child, .planilla-table td:first-child { width: 50px !important; min-width: 50px !important; font-size: 0.65rem; }
          .planilla-table th, .planilla-table td { width: 85px !important; min-width: 85px !important; font-size: 0.65rem; }
      }
  </style>
  </head>
<body>

  <!-- TOP BAR -->
  <div class="top-bar">
      <a href="../index.php" class="brand-logo">CanchaSport</a>
      <div style="display: flex; align-items: center; gap: 1rem;">
          <div style="position: relative;">
              <button class="menu-btn" onclick="toggleMenu(event)">⚙️</button>
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

  <div class="main-layout" style="display: grid; grid-template-columns: 220px 1fr 260px; gap: 1.5rem; max-width: 1600px; margin: 0 auto; padding: 1rem; height: calc(100vh - 80px);">

    <!-- COLUMNA 1: ACCIONES RÁPIDAS (Solo visible si hay acciones o para Asistente) -->
    <div class="actions-column" style="display: flex; flex-direction: column; gap: 1rem;">
        
        <?php if ($rol_actual === 'asistente'): ?>
            <button class="action-btn-sidebar" onclick="window.location.href='gestion_canchas.php'" style="background: white; color: #071289; border: none; padding: 1rem; border-radius: 12px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: left; display: flex; align-items: center; gap: 10px;">
                <span>🎾</span> Crear Canchas
            </button>
            
            <button class="action-btn-sidebar" id="btnTorneosActivos" style="background: white; color: #071289; border: none; padding: 1rem; border-radius: 12px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: left; display: flex; align-items: center; gap: 10px;">
                <span>🏆</span> Torneos Activos
            </button>

            <button class="action-btn-sidebar" onclick="alert('Reserva Manual')" style="background: white; color: #071289; border: none; padding: 1rem; border-radius: 12px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: left; display: flex; align-items: center; gap: 10px;">
                <span>📝</span> Reserva Manual
            </button>
        <?php endif; ?>

        <!-- Panel Torneos (Oculto por defecto) -->
        <div id="panelTorneos" style="display:none; background: white; padding: 1rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-height: 300px; overflow-y: auto;">
            <h4 style="margin:0 0 1rem 0; color:#071289;">Torneos</h4>
            <div id="listaTorneos">Cargando...</div>
        </div>
    </div>

    <!-- COLUMNA 2: PLANILLA DE RESERVAS (Centro, ocupa todo el alto) -->
    <div class="planilla-column" style="background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; flex-direction: column; overflow: hidden; height: 100%;">
        
        <!-- Controles Planilla (Header Interno) -->
        <div style="background: linear-gradient(90deg, #CE93D8, #AB47BC); padding: 0.8rem 1rem; display: flex; flex-wrap: wrap; gap: 0.8rem; align-items: center; justify-content: space-between; color: white;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="date" id="fechaPlanillaInput" style="background: rgba(255,255,255,0.2); border: none; border-radius: 6px; padding: 0.4rem; color: white; font-weight: bold;">
                <button onclick="irAHoyPlanilla()" style="background: white; color: #8E24AA; border: none; border-radius: 6px; padding: 0.4rem 0.8rem; font-weight: bold; cursor: pointer;">Hoy</button>
                <button onclick="cambiarDiaPlanilla(-1)" style="background: rgba(255,255,255,0.3); border: none; border-radius: 50%; width: 28px; height: 28px; color: white; cursor: pointer;">&lt;</button>
                <button onclick="cambiarDiaPlanilla(1)" style="background: rgba(255,255,255,0.3); border: none; border-radius: 50%; width: 28px; height: 28px; color: white; cursor: pointer;">&gt;</button>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <select id="filtroDeporte" style="background: rgba(255,255,255,0.9); border: none; border-radius: 6px; padding: 0.4rem; font-size: 0.85rem;">
                    <option value="todos">Todos</option>
                    <option value="padel">Pádel</option>
                    <option value="futbol">Fútbol</option>
                    <option value="tenis">Tenis</option>
                </select>
                <select id="filtroEstado" style="background: rgba(255,255,255,0.9); border: none; border-radius: 6px; padding: 0.4rem; font-size: 0.85rem;">
                    <option value="">Estados</option>
                    <option value="pagadas">Pagadas</option>
                    <option value="parcial">Parcial</option>
                    <option value="no_pagadas">No Pagadas</option>
                </select>
            </div>
        </div>

        <!-- Tabla Scrollable -->
        <div class="planilla-table-container" style="flex: 1; overflow: auto; padding: 4px;">
            <table id="tablaPlanilla" class="planilla-table" style="width: 100%; border-collapse: separate; border-spacing: 4px; table-layout: fixed;">
                <!-- Se llena con JS -->
            </table>
        </div>
    </div>

    <!-- COLUMNA 3: KPIs FINANCIEROS (Derecha, Vertical) -->
    <div class="kpi-column" style="display: flex; flex-direction: column; gap: 1rem; overflow-y: auto;">
        
        <?php if ($rol_actual === 'admin'): ?>
        <!-- 1. Ingresos -->
        <div class="kpi-card-mini kpi-ingresos">
            <div>Ingresos Mes</div>
            <div>$<?= number_format($ingresos_mes_actual, 0, ',', '.') ?></div>
            <div><?= $variacion_ingresos >= 0 ? '▲' : '▼' ?> <?= number_format(abs($variacion_ingresos), 1) ?>%</div>
        </div>
        <?php endif; ?>

        <!-- 2. Parcial -->
        <div class="kpi-card-mini kpi-parcial" onclick="abrirListaKPI('parcial')">
            <div>Pago Parcial</div>
            <div>$<?= number_format($parcial_mes_actual, 0, ',', '.') ?></div>
            <div>Ver detalles</div>
        </div>

        <?php if ($rol_actual === 'admin'): ?>
        <!-- 3. En Reserva -->
        <div class="kpi-card-mini kpi-reserva">
            <div>En Reserva</div>
            <div><?= $cantidad_en_reserva ?></div>
            <div>Próximas no pagadas</div>
        </div>
        <?php endif; ?>

        <!-- 4. Deuda Vencida (Click) -->
        <div class="kpi-card-mini kpi-deuda" onclick="abrirListaKPI('deuda')" style="cursor: pointer; background: #FFEBEE; border-left: 4px solid #EF5350; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s;">
            <div>Deuda Vencida</div>
            <div>$<?= number_format($monto_deuda, 0, ',', '.') ?></div>
            <div>Ver deudores</div>
        </div>

    </div>
  </div>

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

  <!-- Scripts -->
  <script>
    // === INICIO CODIGO DE CALENDARIO_RESERVAS.JS INTEGRADO Y ADAPTADO PARA ESTE DASHBOARD ===

    // === VARIABLES GLOBALES PARA LA PLANILLA ===
    const iconosDeporte = { 1: '🎾', 2: '🎾', 3: '🏐', 10: '⚽', 11: '⚽', 'default': '🏟️' };
    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    let estadoSeleccionadoPlanilla = "";
    let reservaActualSeleccionada = null;
 
  

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
        const deporte = document.getElementById('filtroDeporte')?.value || "todos";
        try {
            const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
            const response = await fetch(url, { credentials: 'include' });
            if (response.status === 401) { window.location.href = 'login_recintos.php'; return; }
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            renderizarPlanilla(data, estadoSeleccionadoPlanilla);
        } catch (error) { console.error(error); }
    }

    // === RENDERIZADO CON FILTRO DE ESTADO CORREGIDO ===
    function renderizarPlanilla(data, filtroEstado) {
      const table = document.getElementById('tablaPlanilla');
      if (!table) return;
      if (!data.canchas || !data.canchas.length) { table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">Sin canchas.</td></tr>'; return; }

      let html = `<thead><tr><th style="background:#AB47BC; color:white; position:sticky; left:0; z-index:20;">Hora</th>`;
      data.canchas.forEach(c => {
          const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
          html += `<th style="background:#AB47BC; color:white;"><div style="font-size:1rem;">${icono}</div><div style="font-size:0.7rem;">${c.nombre_cancha}</div></th>`;
      });
      html += `</tr></thead><tbody>`;

      const hoy = new Date(); hoy.setHours(0,0,0,0);
      data.slots.forEach(slot => {
          if (slot.is_label_row) {
              html += `<tr><td style="background:#f8f9fa; font-weight:bold; position:sticky; left:0; z-index:1;">${slot.label}</td>`;
              data.canchas.forEach(cancha => {
                  const key = `${cancha.id_cancha}_${slot.label}`;
                  const res = data.reservas[key];
                  let bgClass = 'estado-disponible';
                  let cellContent = '';
                  let clickEvt = '';
                  let opacity = '1';
                  let cumpleFiltro = true;

                  if (res) {
                      let estadoLogico = '';
                      if (res.estado_pago === 'pagado') estadoLogico = 'pagadas';
                      else if (res.estado_pago === 'parcial') estadoLogico = 'parcial';
                      else {
                          const fechaRes = new Date(res.fecha + 'T00:00:00');
                          estadoLogico = (fechaRes < hoy) ? 'no_pagadas' : 'reservada';
                      }
                      if (filtroEstado && filtroEstado !== '') {
                          if (filtroEstado === 'disponible') cumpleFiltro = false;
                          else if (filtroEstado !== estadoLogico) cumpleFiltro = false;
                      }
                      if (cumpleFiltro) {
                          if (res.estado_pago === 'pagado') bgClass = 'estado-pagado';
                          else if (res.estado_pago === 'parcial') bgClass = 'estado-parcial';
                          else bgClass = 'estado-pendiente';
                          cellContent = `<div style="font-size:0.7rem; font-weight:bold;">${(res.nombre_cliente || 'Reserva').substring(0, 10)}</div>`;
                          if (res.id_reserva) clickEvt = `onclick="abrirDetalleDesdePlanilla(${res.id_reserva})"`;
                      } else { opacity = '0.05'; cellContent = ''; }
                  } else {
                      if (filtroEstado && filtroEstado !== 'disponible') opacity = '0.05';
                  }
                  html += `<td class="${bgClass}" style="height:40px; cursor:${clickEvt ? 'pointer' : 'default'}; opacity:${opacity};" ${clickEvt}>${cellContent}</td>`;
              });
              html += `</tr>`;
          }
      });
      table.innerHTML = html;
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

    // --- Lógica Menú ---
    function toggleMenu(e) { e.stopPropagation(); document.getElementById('adminMenu').style.display = 'block'; }
    function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
    function cerrarModalDetalle() { document.getElementById('modalDetalleReserva').style.display = 'none'; }
    function volverAlDetalle() { document.getElementById('modalPago').style.display = 'none'; document.getElementById('modalDetalleReserva').style.display = 'flex'; }
    function cerrarModalListaKPI() { document.getElementById('modalListaKPI').style.display = 'none'; }
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

    // Cargar planilla al inicio
    document.addEventListener('DOMContentLoaded', cargarPlanillaReservas);

    // Función auxiliar por si la API no trae slots (ajusta horas según tu recinto)
    function generarSlotsBase() {
        const slots = [];
        for (let h = 8; h <= 23; h++) {
            const hora = h.toString().padStart(2, '0') + ':00';
            slots.push({ label: hora, is_label_row: true });
        }
        return slots;
    }

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
    function cerrarModalPago() {
        document.getElementById('modalPago').style.display = 'none';
    }

    // === FUNCIONES PARA MODAL DE LISTA KPI ===

    let tipoListaActual = ''; // Para saber si estamos viendo 'deuda' o 'parcial'

    async function abrirListaKPI(tipo) {
        tipoListaActual = tipo;
        const modal = document.getElementById('modalListaKPI');
        const titulo = document.getElementById('tituloListaKPI');
        const tbody = document.getElementById('cuerpoTablaKPI');
        
        modal.style.display = 'flex';
        titulo.textContent = (tipo === 'parcial') ? '📋 Pagos Parciales del Mes' : '🚨 Deuda Vencida';
        // Título con color oscuro por defecto
        titulo.style.color = '#333'; 
        
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#666;">Cargando datos...</td></tr>';

        try {
            const res = await fetch(`../api/canchaboard.php?action=get_lista_kpi&tipo=${tipo}`, { credentials: 'include' });
            const data = await res.json();
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#666;">No hay registros para mostrar.</td></tr>';
                return;
            }

            let html = '';
            data.forEach(row => {
                const saldo = parseFloat(row.saldo_pendiente);
                // Formato de moneda
                const fmt = (n) => '$' + parseInt(n).toLocaleString();
                
                // AQUÍ ESTÁ EL CAMBIO: color:#333 en el TR y en los TD específicos si es necesario
                html += `
                    <tr style="border-bottom:1px solid #eee; cursor:pointer; hover:bg-gray-50; color:#333;" onclick="verDetalleDesdeLista(${row.id_reserva})">
                        <td style="padding:10px; color:#333;">${row.fecha}</td>
                        <td style="padding:10px; color:#333;">${row.nombre_cancha}</td>
                        <td style="padding:10px; font-weight:bold; color:#333;">${row.nombre_cliente || 'N/A'}</td>
                        <td style="padding:10px; color:#333;">${row.telefono_cliente || '-'}</td>
                        <td style="padding:10px; text-align:right; color:#333;">${fmt(row.monto_total)}</td>
                        <td style="padding:10px; text-align:right; color:green;">${fmt(row.monto_recaudacion)}</td>
                        <td style="padding:10px; text-align:right; font-weight:bold; color:#c62828;">${fmt(saldo)}</td>
                        <td style="padding:10px; text-align:center;">
                            <span style="background:#e3f2fd; color:#1565c0; padding:4px 8px; border-radius:4px; font-size:0.75rem;">Ver Detalle</span>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error al cargar datos.</td></tr>';
        }
    }

    // Función puente: Cierra la lista y abre el detalle de la reserva
    async function verDetalleDesdeLista(idReserva) {
        // 1. Cerrar modal de lista
        cerrarModalListaKPI();
        
        // 2. Llamar a la función existente que abre el detalle
        // Asegúrate que esta función ya exista en tu código (la que usamos antes)
        await abrirDetalleDesdePlanilla(idReserva);
    }

    // Cerrar modal si se hace click fuera del contenido
    window.onclick = function(event) {
        const modalLista = document.getElementById('modalListaKPI');
        if (event.target == modalLista) {
            cerrarModalListaKPI();
        }
        // ... (tu lógica existente para otros modales)
    }
  </script>
      <!-- === MODAL LISTA DEUDORES / PARCIALES === -->
      <div id="modalListaKPI" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter: blur(4px);">
          <div style="background:white; padding:0; border-radius:16px; max-width:900px; width:95%; max-height:90vh; display:flex; flex-direction:column; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
              
              <!-- Header del Modal -->
              <div style="padding:1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f8f9fa; border-radius:16px 16px 0 0;">
                  <h3 id="tituloListaKPI" style="margin:0; color:#333; font-size:1.3rem;">Lista</h3>
                  <span onclick="cerrarModalListaKPI()" style="font-size:28px; cursor:pointer; color:#999; line-height:1;">&times;</span>
              </div>

              <!-- Tabla Scrollable -->
              <div style="overflow-y:auto; padding:1rem; flex:1;">
                  <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                      <thead>
                          <tr style="background:#f1f1f1; text-align:left;">
                              <th style="padding:10px;">Fecha</th>
                              <th style="padding:10px;">Cancha</th>
                              <th style="padding:10px;">Socio/Cliente</th>
                              <th style="padding:10px;">Teléfono</th>
                              <th style="padding:10px; text-align:right;">Total</th>
                              <th style="padding:10px; text-align:right;">Abonado</th>
                              <th style="padding:10px; text-align:right; color:#c62828;">Saldo</th>
                              <th style="padding:10px;">Acción</th>
                          </tr>
                      </thead>
                      <tbody id="cuerpoTablaKPI">
                          <!-- Se llena con JS -->
                          <tr><td colspan="8" style="text-align:center; padding:2rem;">Cargando...</td></tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
</body>
</html>