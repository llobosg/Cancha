<?php
// pages/recinto_dashboard.php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$rol_actual = $_SESSION['recinto_rol'] ?? '';
$roles_validos = ['admin', 'asistente'];
if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_validos)) {
    header('Location: login_recintos.php'); exit;
}
require_once __DIR__ . '/../includes/permisos.php';

// Datos Usuario
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

// Datos Recinto
$id_recinto = $_SESSION['id_recinto'];
$stmt_recinto = $pdo->prepare("SELECT nombre FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_recinto->execute([$id_recinto]);
$recinto = $stmt_recinto->fetch();
$recinto_nombre = $recinto['nombre'] ?? 'Recinto Deportivo';

// === CÁLCULO KPIs ===
$hoy = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');
$primer_dia_mes_ant = date('Y-m-01', strtotime('-1 month'));
$ultimo_dia_mes_ant = date('Y-m-t', strtotime('-1 month'));

function getSuma($pdo, $id, $fecha_cond, $pago_cond) {
    $q = "SELECT COALESCE(SUM(r.monto_total), 0) FROM reservas r JOIN canchas c ON r.id_cancha = c.id_cancha WHERE c.id_recinto = :id AND r.fecha $fecha_cond AND r.estado_pago $pago_cond AND r.estado != 'cancelada'";
    $s = $pdo->prepare($q); $s->execute([':id' => $id]); return $s->fetchColumn();
}

$ingresos_act = getSuma($pdo, $id_recinto, ">= '$primer_dia_mes'", "= 'pagado'");
$ingresos_ant = getSuma($pdo, $id_recinto, "BETWEEN '$primer_dia_mes_ant' AND '$ultimo_dia_mes_ant'", "= 'pagado'");
$var_ing = ($ingresos_ant > 0) ? (($ingresos_act - $ingresos_ant) / $ingresos_ant) * 100 : (($ingresos_act > 0) ? 100 : 0);
$parcial_act = getSuma($pdo, $id_recinto, ">= '$primer_dia_mes'", "= 'parcial'");

$q_res = "SELECT COUNT(*) FROM reservas r JOIN canchas c ON r.id_cancha = c.id_cancha WHERE c.id_recinto = :id AND r.fecha > '$hoy' AND r.estado_pago != 'pagado' AND r.estado != 'cancelada'";
$s_res = $pdo->prepare($q_res); $s_res->execute([':id' => $id_recinto]);
$cant_reserva = $s_res->fetchColumn();

$q_deuda = "SELECT COALESCE(SUM(r.monto_total), 0) FROM reservas r JOIN canchas c ON r.id_cancha = c.id_cancha WHERE c.id_recinto = :id AND r.fecha < '$hoy' AND r.estado_pago != 'pagado' AND r.estado != 'cancelada'";
$s_deuda = $pdo->prepare($q_deuda); $s_deuda->execute([':id' => $id_recinto]);
$monto_deuda = $s_deuda->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard - <?= htmlspecialchars($recinto_nombre) ?></title>
<style>
   :root { --bg-primary: #071289; --accent: #4ECDC4; --font-main: 'Segoe UI', sans-serif; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
        background-blend-mode: multiply; color: white; font-family: var(--font-main); min-height: 100vh; padding: 0; overflow-x: hidden;
    }
    
    /* TOP BAR */
    .top-bar {
        background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
        padding: 0.5rem 1.5rem; display: flex; justify-content: space-between; align-items: center;
        position: sticky; top: 0; left: 0; width: 100%; z-index: 1000; 
        box-shadow: 0 2px 8px rgba(186, 104, 200, 0.2); height: 50px;
    }
    .brand-logo { color: white; font-weight: 900; font-size: 1.3rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    .menu-btn { background: rgba(255,255,255,0.2); border: none; font-size: 1.5rem; cursor: pointer; color: white; padding: 0.2rem 0.6rem; border-radius: 6px; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: white; border-radius: 8px; z-index: 1001; min-width: 200px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .dropdown-menu a { display: block; padding: 0.6rem 0.8rem; text-decoration: none; color: #333; font-size: 0.9rem; }
    .btn-logout { text-decoration: none; padding: 0.4rem 1rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 6px; font-weight: bold; font-size: 0.85rem; }

    /* LAYOUT PRINCIPAL CENTRADO */
    .main-layout {
        display: grid; grid-template-columns: 200px 1fr 220px; gap: 1.5rem;
        width: 98%; margin: 0 auto; padding: 0.5rem; height: calc(100vh - 60px); align-items: start;
    }

    /* Columna Izquierda: Acciones */
    .actions-column { 
        display: flex; flex-direction: column; gap: 1rem; 
        padding-left: 1rem; /* Espacio desde el borde izquierdo */
        margin-top: 60px; /* Alinear con KPIs */
    }
    .action-btn-sidebar { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); color: #071289; border: none; padding: 0.8rem; border-radius: 10px; font-weight: bold; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin-bottom: 0.8rem; }
    .action-btn-sidebar:hover { transform: translateY(-2px); }

    .planilla-column {
        background: transparent; display: flex; flex-direction: column; height: 100%; position: relative;
        justify-content: flex-start; align-items: center;
    }
    .planilla-header-controls { 
        background: rgba(21, 101, 192, 0.85); backdrop-filter: blur(10px); padding: 0.8rem 1.5rem;
        border-radius: 12px; margin-bottom: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.2); min-width: 940px; max-width: 1380px; width: fit-content;
        display: flex; flex-wrap: nowrap; gap: 1.5rem; align-items: center; justify-content: space-between; color: white;
    }
    .planilla-table-container {
        flex: 1; overflow: auto; padding: 4px; width: max-content !important;
        min-width: 940px; background: transparent;
    }
    /* Columna Derecha: KPIs */
    .kpi-column, .actions-column { margin-top: 50px; padding: 0 1rem; }
    .kpi-card-mini { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border-left: 4px solid #ccc; padding: 0.8rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin-bottom: 0.8rem; }
    .kpi-card-mini:hover { transform: translateX(-3px); }
    .kpi-card-mini div:first-child { font-size: 0.8rem; text-transform: uppercase; font-weight: bold; opacity: 0.8; }
    .kpi-card-mini div:nth-child(2) { font-size: 1.4rem; font-weight: 900; line-height: 1.2; margin: 0.3rem 0; }
    .kpi-card-mini div:last-child { font-size: 0.7rem; opacity: 0.7; }

    /* Colores KPI */
    .kpi-ingresos { border-left-color: #4CAF50; background: #E8F5E9; }
    .kpi-ingresos div:nth-child(2) { color: #1B5E20 !important; }
    .kpi-parcial { border-left-color: #FBC02D; background: #FFFDE7; cursor: pointer; }
    .kpi-parcial div:nth-child(2) { color: #EF6C00 !important; }
    .kpi-reserva { border-left-color: #2196F3; background: #E3F2FD; }
    .kpi-reserva div:nth-child(2) { color: #0D47A1 !important; }
    .kpi-deuda { border-left-color: #EF5350; background: #FFEBEE; cursor: pointer; }
    .kpi-deuda div:nth-child(2) { color: #B71C1C !important; }

    /* PANEL TORNEOS (Debajo de la planilla) */
    .torneos-panel-container {
        grid-column: 1 / -1; /* Ocupa todo el ancho */
        margin-top: 1rem;
        background: rgba(255,255,255,0.95);
        border-radius: 12px;
        padding: 1.5rem;
        color: #333;
        display: none; /* Oculto por defecto */
        box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
    }

    /* ESTILOS TABLA */
   .planilla-table {
    width: auto; border-collapse: separate; border-spacing: 6px; background: transparent; table-layout: fixed;
    }
    .planilla-table th:first-child, .planilla-table td:first-child {
        position: sticky; left: 0; z-index: 20; background: rgba(255,255,255,0.95) !important; color: #555; font-weight: 600;
        border: none; border-radius: 10px; width: 65px !important; text-align: center; box-shadow: 2px 0 5px rgba(0,0,0,0.15);
    }
    .planilla-table th, .planilla-table td {
        padding: 4px; vertical-align: middle; text-align: center; border-radius: 8px;
        width: 110px !important; min-width: 110px !important; max-width: 110px !important;
    }
    .planilla-table thead th {
        background: rgba(255,255,255,0.9) !important; color: #333; position: sticky; top: 0; z-index: 10;
        border: none; border-radius: 10px; padding: 10px 6px; font-weight: bold; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

     /* Color estado Celdas disponibles: fondo transparente */
    .planilla-table td:hover { transform: scale(1.04); box-shadow: 0 4px 10px rgba(0,0,0,0.15); z-index: 5; position: relative; }
    .planilla-table tbody td:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.2); z-index: 10; position: relative; }

    /* Celdas disponibles: fondo transparente */
    td.estado-disponible { background: rgba(255,255,255,0.1) !important; border: 1px dashed rgba(255,255,255,0.3) !important; }
    /* Celdas reservadas: ROJO INTENSO */
    td.estado-pendiente { background: #FF5252 !important; color: white !important; border: none !important; }
    /* Opcional: mantener verde/amarillo según estado de pago */
    td.estado-pagado { background: #4CAF50 !important; color: white !important; border: none !important; }
    td.estado-parcial { background: #FFEB3B !important; color: #333 !important; border: none !important; }
    /* Hover suave */
    td:hover { transform: scale(1.02); box-shadow: 0 4px 10px rgba(0,0,0,0.2); transition: 0.2s; }

    
    /* Modales */
    #modalDetalleReserva, #modalPago, #modalListaKPI {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center; backdrop-filter: blur(4px);
    }
    #modalPago { z-index: 2500; }
    #modalListaKPI { z-index: 3000; }

    @media (max-width: 1024px) {
        .main-layout { grid-template-columns: 1fr !important; height: auto; display: block; }
        .planilla-header-controls { min-width: 100%; width: 100%; overflow-x: auto; }
        .kpi-column { margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; padding-right: 0; }
        .actions-column { margin-top: 1rem; flex-direction: row; overflow-x: auto; padding-left: 0; }
        .torneos-panel-container { grid-column: auto; }
    }
    /* Estilos específicos para el Modal de Pago */
    #modalPago label {
        color: #333 !important; /* Forzar color oscuro */
        font-weight: bold;
    }
    #modalPago small, 
    #modalPago span,
    #modalPago div {
        color: #555 !important; /* Gris oscuro para textos secundarios */
    }
    #modalPago h3 {
        color: #071289 !important; /* Azul para el título */
    }
        .section-divider {
        display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
        color: white; font-weight: bold; font-size: 0.9rem;
        margin-bottom: 0.5rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        opacity: 0.9; padding: 0.5rem; border-radius: 6px;
        transition: background 0.2s;
    }
    .section-divider:hover {
        background: rgba(255,255,255,0.1);
    }
    /* Animación para la flechita */
    .rotated {
        transform: rotate(-180deg);
    }
    /* === CORRECCIÓN COLOR TEXTO MODAL LISTA KPI === */
    #modalListaKPI {
        color: #333 !important; /* Fuerza color oscuro en todo el modal */
    }
    
    #modalListaKPI h3, 
    #modalListaKPI th, 
    #modalListaKPI td, 
    #modalListaKPI span, 
    #modalListaKPI div {
        color: #333 !important; /* Asegura que hijos también sean oscuros */
    }
    
    /* Excepción para el botón de cerrar (la X) */
    #modalListaKPI span[onclick="cerrarModalListaKPI()"] {
        color: #999 !important;
    }
    
    /* Excepción para el saldo pendiente (que debe ser rojo) */
    #modalListaKPI td:last-child:nth-last-child(2) { 
        color: #c62828 !important; 
    }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes pulse { 0% { transform:scale(1); } 50% { transform:scale(1.03); } 100% { transform:scale(1); } }
    .planilla-table td { animation: fadeInUp 0.3s ease-out; }
    .planilla-table td.estado-ocupado:hover { animation: pulse 1.5s infinite; }

    /* === DRAG & DROP & HIGHLIGHTS === */
    .cell-reserva { cursor: grab !important; transition: transform 0.2s, opacity 0.2s; }
    .cell-reserva:active { cursor: grabbing; }
    .dragging { opacity: 0.3 !important; pointer-events: none !important; border: 2px dashed #333; transform: scale(0.95); z-index: 9999; }
    .drop-target { background: #FFCDD2 !important; box-shadow: 0 0 12px rgba(255,82,82,0.6); transform: scale(1.03); z-index: 5; position: relative; }
    .coord-highlight { background: #FFF8E1 !important; font-weight: 800 !important; transform: scale(1.1); transition: all 0.2s cubic-bezier(0.4,0,0.2,1); z-index: 15; }
    .drop-zone { transition: all 0.2s ease; }

    /* Animación suave al soltar */
    @keyframes dropSuccess { 0% { transform: scale(1.03); } 50% { transform: scale(0.98); } 100% { transform: scale(1); } }
    .drop-anim { animation: dropSuccess 0.3s ease-out; }

    /* Elemento siendo arrastrado: transparente a eventos para no tapar destino */
    .cell-reserva.dragging { 
        opacity: 0.3; 
        pointer-events: none !important; /* CLAVE: deja pasar clicks/hover */
        border: 2px dashed #333;
        transform: scale(0.95);
        z-index: 9999;
    }

    /* Destino activo */
    .drop-zone.highlight { 
        background: #FFCDD2 !important; 
        box-shadow: 0 0 0 2px #EF5350; 
        transform: scale(1.02);
    }

    /* Coordenadas visuales (Hora y Cancha) */
    .time-label.highlight, .court-header.highlight {
        transform: scale(1.15);
        font-weight: 800;
        background: #FFF3E0 !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 15;
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
                <div style="padding: 0.6rem 0.8rem; border-bottom: 1px solid #f0f0f0; display:flex; justify-content:space-between;">
                    <span style="font-size: 0.75rem; font-weight: bold; color: #999;">MENÚ</span>
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

<div class="main-layout">

    <!-- COLUMNA 1: ACCIONES (Izquierda - Ocultas por defecto) -->
    <div class="actions-column">
        <!-- Título Clickable -->
        <div class="section-divider" onclick="toggleAcciones()" style="cursor: pointer; user-select: none;">
            <span>🎾 Operaciones</span>
            <span id="icon-operaciones" style="font-size: 0.8rem; transition: transform 0.3s;">▼</span>
        </div>
        
        <!-- Contenedor de Botones (Oculto inicialmente) -->
        <div id="contenedor-acciones" style="display: none; flex-direction: column; gap: 1rem;">
            <button class="action-btn-sidebar" onclick="window.location.href='gestion_canchas.php'">
                <span>🛠️</span> Crear Canchas
            </button>
            
            <button class="action-btn-sidebar" id="btnTorneosActivos">
                <span>🏆</span> Ver Torneos
            </button>

            <button class="action-btn-sidebar" onclick="window.location.href='crear_torneo.php'">
                <span>➕</span> Crear Torneo
            </button>
        </div>
    </div>

    <!-- COLUMNA 2: PLANILLA (Centro) -->
    <div class="planilla-column">
        <div class="planilla-header-controls">
            <div class="control-group">
                <input type="date" id="fechaPlanillaInput" class="control-input">
                <button onclick="irAHoyPlanilla()" class="control-btn">Hoy</button>
                <button onclick="cambiarDiaPlanilla(-1)" class="control-btn">&lt;</button>
                <button onclick="cambiarDiaPlanilla(1)" class="control-btn">&gt;</button>
            </div>
            
            <div class="control-group">
                <span class="control-label">Deportes:</span>
                <select id="filtroDeporte" class="control-select">
                    <option value="todos">Todos</option>
                    <option value="padel">Pádel</option>
                    <option value="futbol">Fútbol</option>
                    <option value="tenis">Tenis</option>
                </select>
            </div>

            <div class="control-group">
                <span class="control-label">Estado:</span>
                <select id="filtroEstado" class="control-select">
                    <option value="">Todos</option>
                    <option value="pagadas">Pagadas</option>
                    <option value="parcial">Parcial</option>
                    <option value="no_pagadas">No Pagadas</option>
                </select>
            </div>
        </div>
        
        <div class="planilla-table-container">
            <table id="tablaPlanilla" class="planilla-table">
                <!-- Se llena con JS -->
            </table>
        </div>
    </div>

    <!-- COLUMNA 3: KPIs (Derecha) -->
    <div class="kpi-column">
        <?php if ($rol_actual === 'admin'): ?>
        <div class="kpi-card-mini kpi-ingresos">
            <div>Ingresos Mes</div>
            <div>$<?= number_format($ingresos_act, 0, ',', '.') ?></div>
            <div><?= $var_ing >= 0 ? '▲' : '▼' ?> <?= number_format(abs($var_ing), 1) ?>%</div>
        </div>
        <?php endif; ?>

        <div class="kpi-card-mini kpi-parcial" onclick="abrirListaKPI('parcial')">
            <div>Pago Parcial</div>
            <div>$<?= number_format($parcial_act, 0, ',', '.') ?></div>
            <div>Ver detalles</div>
        </div>

        <?php if ($rol_actual === 'admin'): ?>
        <div class="kpi-card-mini kpi-reserva">
            <div>En Reserva</div>
            <div><?= $cant_reserva ?></div>
            <div>Próximas no pagadas</div>
        </div>
        <?php endif; ?>

        <div class="kpi-card-mini kpi-deuda" onclick="abrirListaKPI('deuda')">
            <div>Deuda Vencida</div>
            <div>$<?= number_format($monto_deuda, 0, ',', '.') ?></div>
            <div>Ver deudores</div>
        </div>
    </div>

    <!-- PANEL TORNEOS (Debajo de todo, ancho completo) -->
    <div id="panelTorneos" class="torneos-panel-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 style="margin:0; color:#071289;">🏆 Torneos Activos</h3>
            <button onclick="document.getElementById('panelTorneos').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#666;">&times;</button>
        </div>
        <div id="listaTorneos" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:1rem;">
            <p style="color:#666;">Cargando torneos...</p>
        </div>
    </div>

</div>

<!-- MODALES (Detalle, Pago, Lista KPI) -->
<div id="modalDetalleReserva">
    <div style="background:white; padding:2rem; border-radius:16px; max-width:600px; width:90%; position:relative; max-height:90vh; overflow-y:auto;">
        <span onclick="cerrarModalDetalle()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
        <h3 style="color:#071289; margin-bottom:1.5rem; text-align:center;">📋 Detalle de Reserva</h3>
        <div id="contenidoDetalle"><p style="text-align:center;">Cargando...</p></div>
    </div>
</div>

<div id="modalPago">
    <div style="background:white; padding:2rem; border-radius:16px; max-width:500px; width:90%; position:relative;">
        <span onclick="volverAlDetalle()" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
        <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">💳 Registrar Pago</h3>
        <div style="margin-bottom:1.5rem; font-size:0.9rem; color:#555; background:#f8f9fa; padding:1rem; border-radius:8px; text-align:center;">
            <div><strong>ID:</strong> <span id="infoIdReserva">...</span></div>
            <div><strong>Total:</strong> <span id="infoMontoTotal" style="font-weight:bold; color:#071289;">$0</span></div>
        </div>
        <form id="formPago">
            <div style="margin-bottom:1rem;">
                <label>Monto a Abonar ($)</label>
                <input type="number" id="montoPagar" name="monto_pagar" step="100" required style="width:100%; padding:0.8rem; border:2px solid #4CAF50; border-radius:8px; font-size:1.2rem; font-weight:bold; color:#2e7d32; text-align:right;">
            </div>
            <div style="margin-bottom:1rem;">
                <label>Método de Pago</label>
                <select name="metodo_pago" id="metodoPago" required style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc;">
                    <option value="">Seleccionar...</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="webpay">Webpay</option>
                    <option value="efectivo">Efectivo</option>
                </select>
            </div>
            <div id="campoTransaccion" style="display:none; margin-bottom:1rem;">
                <label>Nº Comprobante</label>
                <input type="text" id="transaccionId" style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc;">
            </div>
            <div style="margin-bottom:1.5rem;">
                <label>Notas</label>
                <textarea id="notasPago" rows="3" style="width:100%; padding:0.7rem; border-radius:8px; border:1px solid #ccc;"></textarea>
            </div>
            <button type="submit" style="width:100%; background:#4CAF50; color:white; border:none; padding:1rem; border-radius:8px; font-weight:bold; cursor:pointer;">Confirmar Pago</button>
        </form>
    </div>
</div>

<div id="modalListaKPI">
    <div style="background:white; padding:0; border-radius:16px; max-width:900px; width:95%; max-height:90vh; display:flex; flex-direction:column;">
        <div style="padding:1.5rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f8f9fa; border-radius:16px 16px 0 0;">
            <h3 id="tituloListaKPI" style="margin:0; color:#333;">Lista</h3>
            <span onclick="cerrarModalListaKPI()" style="font-size:28px; cursor:pointer; color:#999;">&times;</span>
        </div>
        <div style="overflow-y:auto; padding:1rem; flex:1;">
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <thead><tr style="background:#f1f1f1; text-align:left;"><th style="padding:10px;">Fecha</th><th style="padding:10px;">Cancha</th><th style="padding:10px;">Cliente</th><th style="padding:10px;">Teléfono</th><th style="padding:10px; text-align:right;">Total</th><th style="padding:10px; text-align:right;">Abonado</th><th style="padding:10px; text-align:right; color:#c62828;">Saldo</th><th style="padding:10px;">Acción</th></tr></thead>
                <tbody id="cuerpoTablaKPI"><tr><td colspan="8" style="text-align:center; padding:2rem;">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<script>
// === VARIABLES GLOBALES ===
const iconosDeporte = { 1: '🎾', 2: '🎾', 3: '🏐', 10: '⚽', 11: '⚽', 'default': '🏟️' };
let fechaPlanillaActual = new Date().toISOString().split('T')[0];
let estadoSeleccionadoPlanilla = "";
let reservaActualSeleccionada = null;
let tipoListaActual = '';

// === INICIALIZACIÓN ===
document.addEventListener('DOMContentLoaded', () => {
    // Fecha
    const fechaInput = document.getElementById('fechaPlanillaInput');
    if (fechaInput) {
        fechaInput.value = fechaPlanillaActual;
        fechaInput.addEventListener('change', function() { 
            fechaPlanillaActual = this.value; 
            cargarPlanillaReservas(); 
        });
    }
    
    // Filtros
    document.getElementById('filtroDeporte')?.addEventListener('change', cargarPlanillaReservas);
    document.getElementById('filtroEstado')?.addEventListener('change', function() { 
        estadoSeleccionadoPlanilla = this.value; 
        cargarPlanillaReservas(); 
    });

    // Botón Torneos (si existe)
    document.getElementById('btnTorneosActivos')?.addEventListener('click', () => {
        const panel = document.getElementById('panelTorneos');
        if(panel) {
            panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
            if(panel.style.display === 'block') cargarTorneos();
        }
    });

    cargarPlanillaReservas();
});

// === FUNCIONES DE NAVEGACIÓN FECHA ===
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

// === CARGA DE PLANILLA ===
async function cargarPlanillaReservas() {
    const deporte = document.getElementById('filtroDeporte')?.value || "todos";
    console.log(`📡 Cargando... Fecha: ${fechaPlanillaActual}, Deporte: ${deporte}`);
    
    try {
        const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
        const response = await fetch(url, { credentials: 'include' });
        
        if (response.status === 401) {
            showToast("Sesión expirada. Redirigiendo...", "warning");
            setTimeout(() => window.location.href = 'login_recintos.php', 2000);
            return;
        }
        
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        
        renderizarPlanilla(data, estadoSeleccionadoPlanilla);
    } catch (error) {
        console.error(error);
        document.getElementById('tablaPlanilla').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red;">Error: ${error.message}</td></tr>`;
    }
}

function renderizarPlanilla(data, filtroEstado) {
    const table = document.getElementById('tablaPlanilla');
    if (!table) return;
    if (!data.canchas || !data.canchas.length) {
        table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">No hay canchas operativas.</td></tr>';
        return;
    }

    let html = `<thead><tr>`;
    html += `<th style="background:#AB47BC; color:white; position:sticky; left:0; z-index:20; width:60px; min-width:60px; max-width:60px;">Hora</th>`;
    
    data.canchas.forEach(c => {
        const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
        html += `<th style="background:#AB47BC; color: black; width:110px; min-width:110px; max-width:110px; font-size:0.75rem; padding:4px;">
                    <div style="white-space:normal; line-height:1.1;">${c.nombre_cancha}</div>
                 </th>`;
    });
    html += `</tr></thead><tbody>`;

    const ahora = new Date();
    let skipCells = {};
    let celdasPintadas = 0;

    data.slots.forEach(slot => {
        if (slot.is_label_row) {
            html += `<tr>`;
            html += `<td style="background:rgba(255,255,255,0.9); font-weight:bold; position:sticky; left:0; z-index:1; width:60px; font-size:0.75rem; text-align:center; border-right:1px solid #eee;">${slot.label}</td>`;

            data.canchas.forEach((cancha, idxCancha) => {
                if (skipCells[idxCancha] && skipCells[idxCancha] > 0) {
                    skipCells[idxCancha]--;
                    return;
                }

                const key = `${cancha.id_cancha}_${slot.label}`;
                const res = data.reservas[key];

                if (res) {
                    let bgClass = 'estado-pendiente';
                    if (res.estado_pago === 'pagado') bgClass = 'estado-pagado';
                    else if (res.estado_pago === 'parcial') bgClass = 'estado-parcial';

                    const hIni = parseInt(res.hora_inicio.substring(0,2)) * 60 + parseInt(res.hora_inicio.substring(3,5));
                    const hFin = parseInt(res.hora_fin.substring(0,2)) * 60 + parseInt(res.hora_fin.substring(3,5));
                    const duracionMin = hFin - hIni;
                    const rowspan = Math.max(1, Math.ceil(duracionMin / 30));

                    if (rowspan > 1) skipCells[idxCancha] = rowspan - 1;

                    const nombre = (res.nombre_cliente || res.nombre_socio || 'Reserva').substring(0, 10);
                    
                    // ✅ AGREGADOS: draggable, ondragstart, ondragend
                    html += `<td class="${bgClass} cell-reserva" 
                                draggable="true" 
                                ondragstart="dragStart(event, ${res.id_reserva})" 
                                ondragend="dragEnd(event)"
                                style="height:${rowspan * 40}px; vertical-align:middle; cursor:grab;" 
                                onclick="abrirDetalleDesdePlanilla(${res.id_reserva})">
                                <div style="font-size:0.7rem; font-weight:bold;">${nombre}</div>
                                <div style="font-size:0.6rem; opacity:0.9;">${res.hora_inicio.substring(0,5)}-${res.hora_fin.substring(0,5)}</div>
                            </td>`;
                } else {
                    const slotFecha = new Date(`${fechaPlanillaActual}T${slot.label}:00`);
                    const esPasado = slotFecha <= new Date();
                    
                    if (esPasado) {
                        html += `<td class="estado-disponible" style="opacity:0.3; cursor:not-allowed;"></td>`;
                    } else {
                        // ✅ AGREGADOS: ondragover, ondrop
                        html += `<td class="estado-disponible drop-zone" 
                                    ondragover="dragOver(event)" 
                                    ondrop="dropReserva(event, '${cancha.id_cancha}', '${slot.label}')"
                                    onclick="abrirReservaAdmin('${cancha.id_cancha}', '${fechaPlanillaActual}', '${slot.label}')"></td>`;
                    }
                }
            });
            html += `</tr>`;
        }
    });
    html += `</tbody>`;
    table.innerHTML = html;
}

let draggedReservaId = null;

function dragStart(e, id) {
    draggedReservaId = id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', id);
    e.target.classList.add('dragging');
}

function dragEnd(e) {
    e.target.classList.remove('dragging');
    limpiarHighlights();
    draggedReservaId = null;
}

function dragOver(e) {
    e.preventDefault();
    const td = e.target.closest('td.estado-disponible');
    limpiarHighlights();
    if (td) {
        td.classList.add('drop-target');
        highlightCoordinates(td);
    }
}

function highlightCoordinates(td) {
    const row = td.closest('tr');
    const colIndex = Array.from(row.children).indexOf(td);
    
    // Resaltar Hora (primera columna)
    const timeCell = row.querySelector('td:first-child');
    timeCell?.classList.add('coord-highlight');
    
    // Resaltar Cancha (header correspondiente)
    const headerRow = document.querySelector('#tablaPlanilla thead tr');
    if(headerRow && headerRow.children[colIndex]) {
        headerRow.children[colIndex].classList.add('coord-highlight');
    }
}

function limpiarHighlights() {
    document.querySelectorAll('.drop-target, .coord-highlight').forEach(el => {
        el.classList.remove('drop-target', 'coord-highlight');
    });
}

async function dropReserva(e, canchaId, hora) {
    e.preventDefault();
    const targetCell = e.target.closest('td');
    targetCell?.classList.add('drop-anim');
    limpiarHighlights();
    
    if (!draggedReservaId) return;

    if (confirm(`📅 ¿Mover reserva a las ${hora} en Cancha ID ${canchaId}?`)) {
        try {
            const res = await fetch('../api/mover_reserva.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_reserva: draggedReservaId,
                    id_cancha: canchaId,
                    hora_inicio: hora + ':00',
                    fecha: fechaPlanillaActual
                })
            });
            const data = await res.json();
            showToast(data.success ? '✅ Reserva movida correctamente' : '❌ ' + data.message, data.success ? 'success' : 'error');
            if (data.success) cargarPlanillaReservas();
        } catch (err) {
            showToast('❌ Error al mover reserva', 'error');
        }
    }
    draggedReservaId = null;
}

function dragReserva(e, id) {
    draggedReservaId = id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', id);
    e.target.classList.add('dragging');
}

document.addEventListener('dragend', (e) => {
    e.target.classList.remove('dragging');
    limpiarHighlights();
    draggedReservaId = null;
});

function highlightDrop(e) {
    e.preventDefault();
    if (!e.target.classList.contains('drop-zone')) return;
    
    e.target.classList.add('highlight');
    
    // Iluminar Hora (primera columna)
    const row = e.target.closest('tr');
    const timeCell = row.querySelector('td:first-child');
    timeCell?.classList.add('highlight');

    // Iluminar Cancha (header correspondiente)
    const colIndex = Array.from(e.target.parentNode.children).indexOf(e.target);
    const headerRow = document.querySelector('#tablaPlanilla thead tr');
    const courtHeader = headerRow?.children[colIndex];
    courtHeader?.classList.add('highlight');
}

function unhighlightDrop(e) {
    limpiarHighlights();
}

function limpiarHighlights() {
    document.querySelectorAll('.highlight').forEach(el => el.classList.remove('highlight'));
}

async function dropReserva(e, canchaId, hora) {
    e.preventDefault();
    limpiarHighlights();
    if (!draggedReservaId) return;

    if (confirm(`📅 ¿Mover reserva a las ${hora} en Cancha ID ${canchaId}?`)) {
        try {
            const res = await fetch('../api/mover_reserva.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_reserva: draggedReservaId,
                    id_cancha: canchaId,
                    hora_inicio: hora + ':00',
                    fecha: fechaPlanillaActual // Mismo día
                })
            });
            const data = await res.json();
            showToast(data.success ? '✅ Reserva movida correctamente' : '❌ ' + data.message, data.success ? 'success' : 'error');
            
            // ✅ ESTA LÍNEA REFRESCA LA PLANILLA Y MUEVE EL BLOQUE VISUALMENTE
            if (data.success) cargarPlanillaReservas(); 
        } catch (err) {
            showToast('❌ Error de conexión al mover', 'error');
        }
    }
    draggedReservaId = null;
}

// === DETALLE DE RESERVA (CORREGIDO) ===
async function abrirDetalleDesdePlanilla(idReserva) {
    if (!idReserva) return;
    
    const modal = document.getElementById('modalDetalleReserva');
    const container = document.getElementById('contenidoDetalle');
    
    if (modal) modal.style.display = 'flex';
    if (container) container.innerHTML = '<p style="text-align:center;">Cargando...</p>';

    try {
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

        if (container) {
            const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
            const money = (v) => '$' + parseInt(v || 0).toLocaleString();
            
            const montoTotal = parseFloat(detalle.monto_total || 0);
            const montoRecaudado = parseFloat(detalle.monto_recaudacion || 0);
            const saldoPendiente = montoTotal - montoRecaudado;
            const esParcial = (detalle.estado_pago === 'parcial');
            
            let estadoColor = 'red';
            if (detalle.estado_pago === 'pagado') estadoColor = 'green';
            else if (detalle.estado_pago === 'parcial') estadoColor = '#F57F17';

            // Construcción del HTML principal
            let html = `
                <div style="font-size: 0.95rem; line-height: 1.6; color: #333;">
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        <h4 style="margin: 0; color: #0d47a1;">${val(detalle.fecha)}</h4>
                        <div style="font-size: 1.1rem; font-weight: bold;">${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div><strong>Cancha:</strong> ${val(detalle.nombre_cancha)}</div>
                        <div><strong>Deporte:</strong> ${val(detalle.id_deporte)}</div>
                        <div style="grid-column: span 2;"><strong>Cliente:</strong> ${val(detalle.nombre_cliente || detalle.nombre_responsable)}</div>
                        <div style="grid-column: span 2;"><strong>Contacto:</strong> ${val(detalle.telefono_cliente)}</div>
                    </div>
                    <div style="background: #fafafa; padding: 1rem; border-radius: 8px; border: 1px solid #eee; margin-bottom: 1rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                            <span style="color:#666; font-size:0.9rem;">Monto Total</span>
                            <span style="font-weight:bold;">${money(montoTotal)}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                            <span style="color:#666; font-size:0.9rem;">Abonado</span>
                            <span style="font-weight:bold; color:#2e7d32;">${money(montoRecaudado)}</span>
                        </div>
                        ${esParcial ? `
                        <div style="display:flex; justify-content:space-between; padding-top:0.5rem; border-top:1px dashed #ccc;">
                            <span style="color:#c62828; font-weight:bold;">Saldo Pendiente</span>
                            <span style="font-weight:bold; color:#c62828;">${money(saldoPendiente)}</span>
                        </div>` : ''}
                        <div style="margin-top:0.5rem; text-align:right;">
                            <span style="font-size:0.8rem; color:#666;">Estado: </span>
                            <span style="font-weight:bold; color:${estadoColor};">${val(detalle.estado_pago).toUpperCase()}</span>
                        </div>
                    </div>
            `;

            // === SECCIÓN DE NOTAS (CORREGIDA PARA EVITAR SYNTAX ERROR) ===
            const notas = val(detalle.notas, '');
            if (notas && notas !== 'null' && notas !== '') {
                // Definir colores según estado
                const bgNota = esParcial ? '#FFF3E0' : '#FFFDE7';
                const borderNota = esParcial ? '#FFB74D' : '#FFF59D';
                
                // Usar concatenación simple para evitar problemas con template literals anidados complejos
                html += '<div style="background: ' + bgNota + '; padding: 0.8rem; border-radius: 6px; border-left: 4px solid ' + borderNota + '; margin-bottom: 1rem;">';
                html += '<div style="font-size: 0.8rem; font-weight: bold; color: #555; margin-bottom: 0.3rem; text-transform: uppercase;">📝 Historial / Notas</div>';
                html += '<div style="font-size: 0.9rem; color: #333; white-space: pre-wrap; font-family: sans-serif;">' + notas + '</div>';
                html += '</div>';
            }

            html += '</div>'; // Cierre contenedor principal
            
            container.innerHTML = html;

            // Agregar botones de acción
            const actionContainer = document.createElement('div');
            actionContainer.style.marginTop = '1rem';
            actionContainer.style.textAlign = 'center';
            actionContainer.innerHTML = `
                <button onclick="toggleActionMenuModal()" style="background:#071289; color:white; border:none; padding:0.6rem 1.5rem; border-radius:8px; cursor:pointer; width:100%;">⚙️ Opciones</button>
                <div id="actionMenuModal" style="display:none; margin-top:10px; border:1px solid #ddd; border-radius:8px; background:white;">
                    <button onclick="anularReserva()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee;">🗑️ Anular</button>
                    <button onclick="abrirModalMover()" style="width:100%; padding:10px; border:none; background:#E3F2FD; color:#0D47A1; text-align:left; font-weight:bold; cursor:pointer; border-bottom:1px solid #eee;">📅 Mover Fecha/Hora</button>
                    ${detalle.estado_pago !== 'pagado' ? `<button onclick="abrirModalPagoDesdeDetalle()" style="width:100%; padding:10px; border:none; background:#e8f5e9; color:#2e7d32; text-align:left; font-weight:bold;">💳 Pagar</button>` : ''}
                </div>
            `;
            container.appendChild(actionContainer);
        }
    } catch (err) {
        console.error(err);
        if (container) container.innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
    }
}

// === FUNCIONES DE MODALES Y ACCIONES ===
function toggleActionMenuModal() {
    const menu = document.getElementById('actionMenuModal');
    if (menu) menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

function toggleAcciones() {
    const contenedor = document.getElementById('contenedor-acciones');
    const icono = document.getElementById('icon-operaciones');
    if (contenedor && icono) {
        if (contenedor.style.display === 'none') {
            contenedor.style.display = 'flex';
            icono.classList.add('rotated');
        } else {
            contenedor.style.display = 'none';
            icono.classList.remove('rotated');
        }
    }
}

function cerrarModalDetalle() { document.getElementById('modalDetalleReserva').style.display = 'none'; }
function volverAlDetalle() { 
    document.getElementById('modalPago').style.display = 'none'; 
    document.getElementById('modalDetalleReserva').style.display = 'flex'; 
}
function cerrarModalListaKPI() { document.getElementById('modalListaKPI').style.display = 'none'; }

function anularReserva() { alert("Función Anular: En desarrollo"); }

function abrirModalPagoDesdeDetalle() {
    if (!window.reservaActualSeleccionada) return;
    const d = window.reservaActualSeleccionada;
    document.getElementById('infoIdReserva').textContent = d.id_reserva;
    document.getElementById('infoMontoTotal').textContent = '$' + parseFloat(d.monto_total).toLocaleString();
    document.getElementById('montoPagar').value = d.monto_total;
    document.getElementById('formPago').dataset.idReserva = d.id_reserva;
    document.getElementById('formPago').dataset.montoOriginal = d.monto_total;
    document.getElementById('modalDetalleReserva').style.display = 'none';
    document.getElementById('modalPago').style.display = 'flex';
}

// Listener Método de Pago
document.getElementById('metodoPago')?.addEventListener('change', function() {
    const campo = document.getElementById('campoTransaccion');
    if (['transferencia', 'webpay'].includes(this.value)) { campo.style.display = 'block'; } 
    else { campo.style.display = 'none'; }
});

// Submit Pago
document.getElementById('formPago')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const idReserva = this.dataset.idReserva;
    const montoOriginal = parseFloat(this.dataset.montoOriginal);
    const montoPagado = parseFloat(document.getElementById('montoPagar').value);
    const metodo = document.getElementById('metodoPago').value;
    const transaccion = document.getElementById('transaccionId').value;
    const notas = document.getElementById('notasPago').value;

    if (montoPagado <= 0) { showToast("Monto inválido", "error"); return; }

    try {
        const formData = new FormData();
        formData.append('action', 'procesar_pago_parcial');
        formData.append('id_reserva', idReserva);
        formData.append('monto_pagado', montoPagado);
        formData.append('monto_total_original', montoOriginal);
        formData.append('metodo_pago', metodo);
        formData.append('transaccion_id', transaccion || '');
        formData.append('notas_pago', notas);

        const res = await fetch('../api/gestion_reservas.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            let msg = "✅ Pago registrado.";
            let type = "success";
            if (montoPagado < montoOriginal) {
                msg = `⚠️ Pago Parcial. Faltan $${(montoOriginal - montoPagado).toLocaleString()}.`;
                type = "warning";
            }
            showToast(msg, type);
            document.getElementById('modalPago').style.display = 'none';
            document.getElementById('modalDetalleReserva').style.display = 'none';
            cargarPlanillaReservas();
        } else {
            showToast("❌ Error: " + data.message, "error");
        }
    } catch (err) {
        showToast("❌ Error de conexión", "error");
    }
});

// === LISTA KPI ===
async function abrirListaKPI(tipo) {
    tipoListaActual = tipo;
    const modal = document.getElementById('modalListaKPI');
    const tbody = document.getElementById('cuerpoTablaKPI');
    document.getElementById('tituloListaKPI').textContent = (tipo === 'parcial') ? '📋 Pagos Parciales' : '🚨 Deuda Vencida';
    
    modal.style.display = 'flex';
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem;">Cargando...</td></tr>';

    try {
        const res = await fetch(`../api/canchaboard.php?action=get_lista_kpi&tipo=${tipo}`, { credentials: 'include' });
        const data = await res.json();
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2rem;">Sin registros.</td></tr>';
            return;
        }

        let html = '';
        data.forEach(row => {
            const total = parseFloat(row.monto_total) || 0;
            const abonado = parseFloat(row.monto_recaudacion) || 0;
            const saldo = parseFloat(row.saldo_pendiente) || (total - abonado);
            const fmt = (n) => '$' + parseInt(n).toLocaleString();
            
            html += `
                <tr style="border-bottom:1px solid #eee; cursor:pointer;" onclick="verDetalleDesdeLista(${row.id_reserva})">
                    <td style="padding:10px;">${row.fecha}</td>
                    <td style="padding:10px;">${row.nombre_cancha}</td>
                    <td style="padding:10px; font-weight:bold;">${row.nombre_cliente || 'N/A'}</td>
                    <td style="padding:10px;">${row.telefono_cliente || '-'}</td>
                    <td style="padding:10px; text-align:right;">${fmt(total)}</td>
                    <td style="padding:10px; text-align:right; color:green;">${fmt(abonado)}</td>
                    <td style="padding:10px; text-align:right; font-weight:bold; color:#c62828;">${fmt(saldo)}</td>
                    <td style="padding:10px; text-align:center;"><span style="background:#e3f2fd; color:#1565c0; padding:2px 6px; border-radius:4px; font-size:0.7rem;">Ver</span></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error al cargar.</td></tr>';
    }
}

async function verDetalleDesdeLista(idReserva) {
    cerrarModalListaKPI();
    await abrirDetalleDesdePlanilla(idReserva);
}

// Toast System
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const bg = type === 'success' ? '#4CAF50' : (type === 'warning' ? '#FF9800' : '#F44336');
    toast.style.cssText = `background: ${bg}; color: white; padding: 15px 25px; border-radius: 8px; margin-top: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); font-weight: bold; animation: slideIn 0.3s ease-out forwards;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// Menú Admin
function toggleMenu(e) { e.stopPropagation(); document.getElementById('adminMenu').style.display = 'block'; }
function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
document.addEventListener('click', () => { if(document.getElementById('adminMenu').style.display === 'block') closeMenu(); });

// Torneos (Stub)
async function cargarTorneos() {
    const c = document.getElementById('listaTorneos');
    if(c) c.innerHTML = '<p style="color:#666;">No hay torneos activos.</p>';
}

function buscarSocioAdmin(query) {
  clearTimeout(debounceTimer);
  if(query.length < 2) { document.getElementById('searchResultsAdmin').style.display='none'; return; }
  
  debounceTimer = setTimeout(async () => {
    try {
      const res = await fetch(`../api/search_socios.php?q=${encodeURIComponent(query)}`);
      const data = await res.json();
      const container = document.getElementById('searchResultsAdmin');
      container.innerHTML = '';
      
      if(data.length === 0) { container.innerHTML = '<div style="padding:10px; color:#666;">Sin coincidencias. Crear nuevo registro.</div>'; }
      else {
        data.forEach(s => {
          container.innerHTML += `<div onclick="seleccionarSocioAdmin(${s.id_socio}, '${s.nombre}', '${s.email}', '${s.celular}')" 
            style="padding:10px; cursor:pointer; border-bottom:1px solid #f0f0f0; font-size:0.9rem;">
            <strong>${s.nombre}</strong> <span style="color:#666; font-size:0.8rem;">| ${s.email}</span></div>`;
        });
      }
      container.style.display = 'block';
    } catch(e) { console.error(e); }
  }, 300);
}

document.getElementById('formReservaManual')?.addEventListener('submit', async(e) => {
  e.preventDefault();
  
  const canchaId = document.getElementById('admin_cancha_id').value;
  const fecha = document.getElementById('admin_fecha').value;
  const horaInicio = document.getElementById('admin_hora').value;
  const duracion = parseInt(document.querySelector('input[name="duracion_manual"]:checked')?.value || 60);
  
  // Calcular hora fin exacta
  const [h, m] = horaInicio.split(':').map(Number);
  const finMin = m + duracion;
  const finH = h + Math.floor(finMin / 60);
  const finM = finMin % 60;
  const horaFin = `${String(finH).padStart(2,'0')}:${String(finM).padStart(2,'0')}:00`;

  const data = {
    id_cancha: canchaId,
    fecha: fecha,
    hora_inicio: horaInicio,
    hora_fin: horaFin,
    duracion_minutos: duracion,
    id_socio: document.getElementById('admin_socio_id').value || null,
    nombre_cliente: document.getElementById('admin_nombre').value,
    email_cliente: document.getElementById('admin_email').value,
    celular_cliente: document.getElementById('admin_celular').value
  };
  
  try {
    const res = await fetch('../api/admin/manual_booking.php', { 
        method:'POST', 
        headers:{'Content-Type':'application/json'}, 
        body:JSON.stringify(data) 
    });
    const result = await res.json();
    cerrarModalReservaAdmin();
    showToast(result.success ? '✅ Reserva creada y correo enviado' : '❌ '+result.message, result.success?'success':'error');
    if(result.success) cargarPlanillaReservas();
  } catch(err) {
    cerrarModalReservaAdmin();
    showToast('❌ Error de red', 'error');
  }
});

// Permitir drop en celdas disponibles
document.addEventListener('dragover', (e) => {
    if (e.target.classList.contains('estado-disponible')) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
});

// Manejar drop
document.addEventListener('drop', async (e) => {
    e.preventDefault();
    if (!draggedReservaId || !e.target.classList.contains('estado-disponible')) return;
    
    // Extraer datos de la celda destino (cancha y hora)
    const cell = e.target;
    const row = cell.closest('tr');
    const horaLabel = row.querySelector('td:first-child')?.textContent?.trim();
    const colIndex = Array.from(cell.parentNode.children).indexOf(cell);
    
    // Obtener ID de cancha desde el header (columna correspondiente)
    const headerRow = document.querySelector('#tablaPlanilla thead tr');
    const canchaHeader = headerRow?.children[colIndex];
    // Aquí necesitarías almacenar el ID de cancha en un data-attribute en el header
    // O buscarlo desde los datos originales. Simplificamos asumiendo que lo tienes.
    
    if (!horaLabel) {
        showToast('❌ No se pudo determinar la hora destino', 'error');
        return;
    }
    
    if (confirm(`¿Mover reserva a las ${horaLabel}?`)) {
        try {
            const res = await fetch('../api/mover_reserva.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id_reserva: draggedReservaId,
                    id_cancha: 1, // ← Reemplazar con lógica real para obtener ID cancha
                    hora_inicio: horaLabel + ':00'
                })
            });
            const data = await res.json();
            showToast(data.success ? '✅ Reserva movida y correo enviado' : '❌ ' + data.message, 
                     data.success ? 'success' : 'error');
            if (data.success) cargarPlanillaReservas();
        } catch (err) {
            showToast('❌ Error de conexión al mover reserva', 'error');
        }
    }
    draggedReservaId = null;
});

// === MODAL RESERVA MANUAL ADMIN ===
let debounceTimer;
function debounceBuscar(val) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscarSocioAdmin(val), 300);
}

async function buscarSocioAdmin(query) {
    const container = document.getElementById('searchResultsAdmin');
    if (!container) return;
    if (query.length < 2) { container.style.display = 'none'; return; }

    try {
        const res = await fetch(`../api/search_socios.php?q=${encodeURIComponent(query)}`);
        const text = await res.text(); // Leer como texto primero para evitar crash si viene HTML
        
        let data;
        try { data = JSON.parse(text); } catch (e) {
            console.error('❌ API search_socios devolvió HTML/JSON inválido:', text.substring(0, 150));
            container.innerHTML = '<div style="padding:8px; color:#d32f2f; font-size:0.85rem;">Error en búsqueda. Revisa consola.</div>';
            container.style.display = 'block';
            return;
        }

        container.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
            container.innerHTML = '<div style="padding:10px; color:#666; font-size:0.85rem;">Sin coincidencias. Se creará socio nuevo.</div>';
        } else {
            data.forEach(s => {
                const safeNombre = (s.nombre || '').replace(/'/g, "\\'");
                const safeEmail = (s.email || '').replace(/'/g, "\\'");
                const safeCel = (s.celular || '').replace(/'/g, "\\'");
                container.innerHTML += `
                    <div onclick="seleccionarSocioAdmin(${s.id_socio}, '${safeNombre}', '${safeEmail}', '${safeCel}')"
                         style="padding:10px; cursor:pointer; border-bottom:1px solid #eee; font-size:0.9rem; color:#333; background:#fff;">
                        <strong>${s.nombre}</strong> <span style="color:#666;">| ${s.email}</span>
                    </div>`;
            });
        }
        container.style.display = 'block';
    } catch (err) {
        console.error('Error en buscarSocioAdmin:', err);
    }
}

function seleccionarSocioAdmin(id, nombre, email, celular) {
    document.getElementById('admin_socio_id').value = id;
    document.getElementById('admin_nombre').value = nombre;
    document.getElementById('admin_email').value = email;
    document.getElementById('admin_celular').value = celular;
    document.getElementById('searchResultsAdmin').style.display = 'none';
    document.getElementById('searchAdmin').value = nombre;
}

function abrirReservaAdmin(canchaId, fecha, hora) {
    document.getElementById('admin_cancha_id').value = canchaId;
    document.getElementById('admin_fecha').value = fecha;
    document.getElementById('admin_hora').value = hora;
    document.getElementById('searchAdmin').value = '';
    document.getElementById('formReservaManual')?.reset();
    document.getElementById('admin_socio_id').value = '';
    document.getElementById('modalReservaAdmin').style.display = 'flex';
}

function cerrarModalReservaAdmin() {
    document.getElementById('modalReservaAdmin').style.display = 'none';
}

function abrirModalMover() {
    const res = window.reservaActualSeleccionada;
    if(!res) return;
    
    document.getElementById('modalDetalleReserva').style.display = 'none';
    document.getElementById('modalMoverReserva').style.display = 'flex';
    
    // Llenar datos originales
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('orig_resumen').textContent = `${res.fecha} | ${res.hora_inicio.substring(0,5)}-${res.hora_fin.substring(0,5)} | ${res.nombre_cancha || 'Cancha'}`;
    document.getElementById('moverFecha').value = res.fecha; // o tomorrow.toISOString().split('T')[0]
    document.getElementById('moverFecha').min = tomorrow.toISOString().split('T')[0];
    
    cargarHorasDisponibles(res.fecha);
}

function cerrarModalMover() {
    document.getElementById('modalMoverReserva').style.display = 'none';
    document.getElementById('modalDetalleReserva').style.display = 'flex';
}

async function cargarHorasDisponibles(fecha) {
    fecha = fecha || document.getElementById('moverFecha').value;
    const select = document.getElementById('moverHora');
    select.disabled = true;
    select.innerHTML = '<option>⏳ Buscando disponibilidad...</option>';
    actualizarResumenNuevo();

    try {
        const res = await fetch(`../api/canchaboard.php?action=get_planilla_reservas&fecha=${fecha}&deporte=todos`);
        const data = await res.json();
        if (!data.slots) throw new Error('Formato inválido');

        select.innerHTML = '';
        let disponibles = 0;
        data.slots.forEach(slot => {
            if (slot.is_label_row) {
                const hayLibre = data.canchas.some(c => !data.reservas[`${c.id_cancha}_${slot.label}`]);
                if (hayLibre) {
                    select.innerHTML += `<option value="${slot.label}">${slot.label} hrs</option>`;
                    disponibles++;
                }
            }
        });

        select.disabled = disponibles === 0;
        actualizarResumenNuevo();
    } catch (e) {
        select.innerHTML = '<option>Error al cargar</option>';
    }
}

function actualizarResumenNuevo() {
    const fecha = document.getElementById('moverFecha').value;
    const hora = document.getElementById('moverHora').value;
    document.getElementById('new_resumen').textContent = (fecha && hora) ? `${fecha} | ${hora}:00 - (misma duración)` : '--';
}

async function confirmarMovimiento() {
    const res = window.reservaActualSeleccionada;
    const nuevaFecha = document.getElementById('moverFecha').value;
    const nuevaHora = document.getElementById('moverHora').value;

    if (!res || !nuevaFecha || !nuevaHora) return showToast('❌ Selecciona fecha y hora', 'error');

    try {
        const response = await fetch('../api/mover_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_reserva: res.id_reserva,
                fecha: nuevaFecha,
                hora_inicio: nuevaHora + ':00',
                id_cancha: res.id_cancha // Mantiene misma cancha, cámbialo si quieres
            })
        });
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ Reserva reubicada y correo enviado', 'success');
            cerrarModalMover();
            cargarPlanillaReservas();
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    } catch (err) {
        showToast('❌ Error de red al mover', 'error');
    }
}
</script>
    <!-- Estilos adicionales para animaciones -->
    <style>
    @keyframes slideIn { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .rotated { transform: rotate(-180deg); }
    #modalPago label { color: #333 !important; font-weight: bold; }
    #modalPago small, #modalPago span, #modalPago div { color: #555 !important; }
    #modalPago h3 { color: #071289 !important; }
    </style>
    <div id="modalReservaAdmin" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:3000; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
        <div style="background:white; padding:2rem; border-radius:16px; max-width:480px; width:90%; position:relative; color:#333; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
            <!-- BOTÓN X PARA CERRAR -->
            <span onclick="cerrarModalReservaAdmin()" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; color:#999; line-height:1;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">📅 Reserva Manual</h3>
            
            <form id="formReservaManual">
            <input type="hidden" id="admin_cancha_id">
            <input type="hidden" id="admin_fecha">
            <input type="hidden" id="admin_hora">
            <input type="hidden" id="admin_socio_id">
            
            <!-- Buscador Inteligente -->
            <div style="position:relative; margin-bottom:1rem;">
                <input type="text" id="searchAdmin" placeholder="Buscar socio (nombre, email, celular)..." 
                    oninput="debounceBuscar(this.value)" style="width:100%; padding:10px; border:2px solid #ddd; border-radius:8px;">
                <div id="searchResultsAdmin" style="position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #eee; border-radius:8px; max-height:180px; overflow-y:auto; z-index:10; display:none; box-shadow:0 5px 15px rgba(0,0,0,0.1);"></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:1rem;">
                <input type="text" id="admin_nombre" placeholder="Nombre completo" required style="padding:8px; border:1px solid #ccc; border-radius:6px;">
                <input type="email" id="admin_email" placeholder="Email" required style="padding:8px; border:1px solid #ccc; border-radius:6px;">
                <input type="text" id="admin_celular" placeholder="Celular (+569...)" style="padding:8px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div style="margin: 1rem 0; background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #071289;">
            <label style="font-weight:bold; color:#333; display:block; margin-bottom:6px;">⏱️ Duración a reservar:</label>
            <div style="display:flex; gap:15px;">
                <label style="cursor:pointer; color:#333;">
                <input type="radio" name="duracion_manual" value="60" checked style="margin-right:5px;"> 60 min
                </label>
                <label style="cursor:pointer; color:#333;">
                <input type="radio" name="duracion_manual" value="90" style="margin-right:5px;"> 90 min
                </label>
            </div>
            </div>
            
            <button type="submit" onclick="event.preventDefault(); alert('Funcionalidad de guardado en desarrollo. Integra tu API aquí.')" style="width:100%; padding:10px; background:#4CAF50; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">✅ Confirmar Reserva</button>
            </form>
            <p style="font-size:0.7rem; color:#888; margin-top:0.5rem; text-align:center;">* Si el socio no existe, se creará como "Individual" y recibirá link de registro.</p>
        </div>
    </div>

    

    <div id="modalMoverReserva" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:4000; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
        <div style="background:white; padding:2rem; border-radius:16px; max-width:500px; width:90%; position:relative; color:#333;">
            <span onclick="cerrarModalMover()" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; color:#999;">&times;</span>
            
            <h3 style="color:#071289; margin-bottom:1rem; text-align:center;">🔄 Reubicar Reserva</h3>
            
            <!-- RESUMEN COMPARATIVO -->
            <div style="background:#f8f9fa; padding:1rem; border-radius:8px; margin-bottom:1.5rem; display:grid; gap:0.5rem;">
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding-bottom:0.5rem;">
                <span style="color:#666;">📍 <strong>Actual:</strong></span>
                <span id="orig_resumen" style="font-weight:bold;">--</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding-top:0.5rem;">
                <span style="color:#666;">🎯 <strong>Nueva:</strong></span>
                <span id="new_resumen" style="font-weight:bold; color:#071289;">--</span>
            </div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
            <label style="font-weight:bold; color:#333;">📅 Nueva Fecha:</label>
            <input type="date" id="moverFecha" onchange="cargarHorasDisponibles()" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
            <label style="font-weight:bold; color:#333;">⏰ Nueva Hora:</label>
            <select id="moverHora" disabled onchange="actualizarResumenNuevo()" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; background:#f9f9f9;">
                <option>Selecciona una fecha primero</option>
            </select>
            </div>

            <button onclick="confirmarMovimiento()" style="width:100%; padding:10px; background:#4CAF50; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">✅ Confirmar Cambio</button>
        </div>
    </div>
</body>
</html>