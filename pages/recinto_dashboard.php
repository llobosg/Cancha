<?php
// pages/recinto_dashboard.php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$rol_actual = $_SESSION['recinto_rol'] ?? '';
if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, ['admin', 'asistente'])) {
    header('Location: login_recintos.php'); exit;
}
require_once __DIR__ . '/../includes/permisos.php';

// Datos Usuario y Recinto
$stmt_user = $pdo->prepare("SELECT * FROM admin_recintos WHERE id_admin = ?");
$stmt_user->execute([$_SESSION['id_admin']]);
$usuario_actual = $stmt_user->fetch();

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
    :root { --bg-primary: #071289; --font-main: 'Segoe UI', sans-serif; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
        background-blend-mode: multiply; color: white; font-family: var(--font-main); min-height: 100vh; padding: 0; overflow-x: hidden;
    }
    
    /* TOP BAR DELGADO */
    .top-bar {
        background: linear-gradient(90deg, #CE93D8 0%, #BA68C8 50%, #AB47BC 100%);
        padding: 0.5rem 1.5rem; /* Reducido */
        display: flex; justify-content: space-between; align-items: center;
        position: sticky; top: 0; left: 0; width: 100%; z-index: 1000; 
        box-shadow: 0 2px 8px rgba(186, 104, 200, 0.2); height: 50px;
    }
    .brand-logo { color: white; font-weight: 900; font-size: 1.3rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    .menu-btn { background: rgba(255,255,255,0.2); border: none; font-size: 1.5rem; cursor: pointer; color: white; padding: 0.2rem 0.6rem; border-radius: 6px; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: white; border-radius: 8px; z-index: 1001; min-width: 200px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .dropdown-menu a { display: block; padding: 0.6rem 0.8rem; text-decoration: none; color: #333; font-size: 0.9rem; }
    .btn-logout { text-decoration: none; padding: 0.4rem 1rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 6px; font-weight: bold; font-size: 0.85rem; }

    /* LAYOUT PRINCIPAL */
    .main-layout {
        display: grid;
        /* Acciones (160px) | Planilla (Flexible) | KPIs (160px) */
        grid-template-columns: 160px 1fr 160px; 
        gap: 1rem;
        width: 99%; margin: 0 auto; padding: 0.5rem;
        height: calc(100vh - 50px); /* Ajustado a nueva altura topbar */
    }

    /* Columna Izquierda: Acciones */
    .actions-column { display: flex; flex-direction: column; gap: 0.8rem; }
    .action-btn-sidebar {
        background: white; color: #071289; border: none; padding: 0.7rem; border-radius: 8px;
        font-weight: bold; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 8px; font-size: 0.9rem;
    }

    /* Columna Central: Planilla */
    .planilla-column {
        background: white; border-radius: 12px; display: flex; flex-direction: column;
        overflow: hidden; height: 100%; position: relative;
    }
    
    /* HEADER FILTROS AZUL PADEL */
    .planilla-header-controls { 
        background: linear-gradient(90deg, #1565C0, #0D47A1); /* Azul Pádel */
        padding: 0.5rem 1rem;
        display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; color: white; 
        height: 50px; /* Igual que top bar */
    }
    .control-group { display: flex; align-items: center; gap: 0.5rem; }
    .control-label { font-size: 0.8rem; font-weight: 600; opacity: 0.9; }
    
    /* Input Fecha Corregido */
    .control-input { 
        background: rgba(255,255,255,0.2); border: none; outline: none; color: white; 
        font-weight: bold; text-align: center; width: 130px; padding-right: 25px; /* Espacio para ícono */
        border-radius: 4px; font-size: 0.85rem;
    }
    .control-input::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
    
    .control-btn { background: white; color: #0D47A1; border: none; border-radius: 4px; padding: 0.3rem 0.6rem; font-weight: bold; cursor: pointer; font-size: 0.8rem; }
    .control-select { background: rgba(255,255,255,0.95); border: none; border-radius: 4px; padding: 0.3rem; font-size: 0.8rem; color: #333; min-width: 100px; }

    /* CONTENEDOR TABLA: SIN ESPACIO EXTRA */
    .planilla-table-container {
        flex: 1; overflow: auto; padding: 4px;
        /* Width max-content asegura que la tabla mida exactamente lo que necesitan sus columnas */
        width: max-content !important; 
        min-width: 100%; /* Pero al menos ocupa el ancho del contenedor */
        background-color: #f4f6f9;
    }

    /* Columna Derecha: KPIs (Con aire a la derecha) */
    .kpi-column {
        display: flex; flex-direction: column; gap: 0.6rem; overflow-y: auto;
        padding-right: 0.5rem; /* Aire entre KPIs y borde derecho */
    }
    .kpi-card-mini {
        background: white; border-left: 3px solid #ccc; padding: 0.5rem; border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #333; transition: transform 0.2s;
    }
    .kpi-card-mini:hover { transform: translateX(-2px); }
    .kpi-card-mini div:first-child { font-size: 0.6rem; text-transform: uppercase; font-weight: bold; opacity: 0.8; line-height: 1.1; }
    .kpi-card-mini div:nth-child(2) { font-size: 1rem; font-weight: 900; line-height: 1.1; margin: 0.2rem 0; word-break: break-word; }
    .kpi-card-mini div:last-child { font-size: 0.55rem; opacity: 0.7; line-height: 1.1; }

    /* Colores KPI */
    .kpi-ingresos { border-left-color: #4CAF50; background: #E8F5E9; }
    .kpi-ingresos div:nth-child(2) { color: #1B5E20 !important; }
    .kpi-parcial { border-left-color: #FBC02D; background: #FFFDE7; cursor: pointer; }
    .kpi-parcial div:nth-child(2) { color: #EF6C00 !important; }
    .kpi-reserva { border-left-color: #2196F3; background: #E3F2FD; }
    .kpi-reserva div:nth-child(2) { color: #0D47A1 !important; }
    .kpi-deuda { border-left-color: #EF5350; background: #FFEBEE; cursor: pointer; }
    .kpi-deuda div:nth-child(2) { color: #B71C1C !important; }

    /* ESTILOS DE TABLA */
    .planilla-table { border-collapse: separate; border-spacing: 3px; }
    .planilla-table th:first-child, .planilla-table td:first-child {
        position: sticky; left: 0; z-index: 20; background: #f8f9fa !important; color: #333; font-weight: bold;
        border-right: 2px solid #e0e0e0; min-width: 60px !important; max-width: 60px !important; width: 60px !important;
        padding: 2px !important; font-size: 0.7rem; text-align: center;
    }
    .planilla-table th, .planilla-table td {
        padding: 2px; vertical-align: middle; text-align: center; border-radius: 4px; min-width: 100px;
    }
    .planilla-table thead th {
        background: #AB47BC !important; color: white; position: sticky; top: 0; z-index: 5;
        height: 40px; font-size: 0.7rem; min-width: 110px !important; max-width: 110px !important; width: 110px !important;
    }
    td.estado-pagado { background-color: #4CAF50 !important; color: white; }
    td.estado-parcial { background-color: #FFEB3B !important; color: #333; }
    td.estado-pendiente { background-color: #FF5252 !important; color: white; }
    td.estado-disponible { background-color: #FAFAFA !important; border: 1px dashed #E0E0E0 !important; }

    /* Modales */
    #modalDetalleReserva, #modalPago, #modalListaKPI {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center; backdrop-filter: blur(4px);
    }
    #modalPago { z-index: 2500; }
    #modalListaKPI { z-index: 3000; }

    /* RESPONSIVE MÓVIL */
    @media (max-width: 1024px) {
        .main-layout { 
            grid-template-columns: 1fr !important; 
            height: auto; 
            display: block; /* Cambiamos a bloque para ordenar verticalmente */
        }
        
        /* KPIs ARRIBA Y FIJOS */
        .kpi-column {
            display: grid;
            grid-template-columns: 1fr 1fr; /* 2 por fila */
            gap: 0.5rem;
            position: sticky;
            top: 50px; /* Debajo del top bar */
            z-index: 900;
            background: rgba(0,0,0,0.2); /* Fondo sutil para legibilidad */
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            backdrop-filter: blur(5px);
        }
        .kpi-card-mini { min-width: auto; padding: 0.5rem; margin: 0; }
        
        /* Planilla debajo */
        .planilla-column { height: 70vh; }
        
        /* Acciones en scroll horizontal */
        .actions-column { flex-direction: row; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
        .action-btn-sidebar { min-width: 120px; }
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

    <!-- COLUMNA 1: ACCIONES -->
    <div class="actions-column">
        <?php if ($rol_actual === 'asistente'): ?>
            <button class="action-btn-sidebar" onclick="window.location.href='gestion_canchas.php'"><span>🎾</span> Crear Canchas</button>
            <button class="action-btn-sidebar" id="btnTorneosActivos"><span>🏆</span> Torneos Activos</button>
            <button class="action-btn-sidebar" onclick="alert('Reserva Manual')"><span>📝</span> Reserva Manual</button>
        <?php endif; ?>
        <div id="panelTorneos" style="display:none; background: white; padding: 0.8rem; border-radius: 8px; max-height: 200px; overflow-y: auto; color:#333;">
            <h4 style="margin:0 0 0.5rem 0; color:#071289; font-size:0.9rem;">Torneos</h4>
            <div id="listaTorneos" style="font-size:0.8rem;">Cargando...</div>
        </div>
    </div>

    <!-- COLUMNA 2: PLANILLA -->
    <div class="planilla-column">
        <div class="planilla-header-controls">
            <div style="display: flex; align-items: center; gap: 0.8rem;">
                <input type="date" id="fechaPlanillaInput" class="control-input">
                <button onclick="irAHoyPlanilla()" class="control-btn">Hoy</button>
                <button onclick="cambiarDiaPlanilla(-1)" class="control-btn">&lt;</button>
                <button onclick="cambiarDiaPlanilla(1)" class="control-btn">&gt;</button>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
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
                    <span class="control-label">Estados:</span>
                    <select id="filtroEstado" class="control-select">
                        <option value="">Todos</option>
                        <option value="pagadas">Pagadas</option>
                        <option value="parcial">Parcial</option>
                        <option value="no_pagadas">No Pagadas</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="planilla-table-container">
            <table id="tablaPlanilla" class="planilla-table">
                <!-- Se llena con JS -->
            </table>
        </div>
    </div>

    <!-- COLUMNA 3: KPIs -->
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
const iconosDeporte = { 1: '🎾', 2: '🎾', 3: '🏐', 10: '⚽', 11: '⚽', 'default': '🏟️' };
let fechaPlanillaActual = new Date().toISOString().split('T')[0];
let estadoSeleccionadoPlanilla = "";
let reservaActualSeleccionada = null;

document.addEventListener('DOMContentLoaded', () => {
    const fechaInput = document.getElementById('fechaPlanillaInput');
    if (fechaInput) {
        fechaInput.value = fechaPlanillaActual;
        fechaInput.addEventListener('change', function() { fechaPlanillaActual = this.value; cargarPlanillaReservas(); });
    }
    document.getElementById('filtroDeporte')?.addEventListener('change', cargarPlanillaReservas);
    document.getElementById('filtroEstado')?.addEventListener('change', function() { estadoSeleccionadoPlanilla = this.value; cargarPlanillaReservas(); });
    cargarPlanillaReservas();
});

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

function renderizarPlanilla(data, filtroEstado) {
    const table = document.getElementById('tablaPlanilla');
    if (!table) return;
    if (!data.canchas || !data.canchas.length) { table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">Sin canchas.</td></tr>'; return; }

    let html = `<thead><tr>`;
    html += `<th style="position:sticky; left:0; z-index:20; background:#AB47BC; color:white; width:60px; min-width:60px; max-width:60px; padding:5px;">Hora</th>`;
    data.canchas.forEach(c => {
        const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
        html += `<th style="background:#AB47BC; color:white; width:110px; min-width:110px; max-width:110px; padding:5px; font-size:0.7rem;">
                    <div style="font-size:1.2rem;">${icono}</div>
                    <div style="white-space:normal; line-height:1.1;">${c.nombre_cancha}</div>
                 </th>`;
    });
    html += `</tr></thead><tbody>`;

    const hoy = new Date(); hoy.setHours(0,0,0,0);
    data.slots.forEach(slot => {
        if (slot.is_label_row) {
            html += `<tr>`;
            html += `<td style="background:#f8f9fa; font-weight:bold; position:sticky; left:0; z-index:1; width:60px; min-width:60px; max-width:60px; padding:5px; font-size:0.7rem;">${slot.label}</td>`;
            
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
                        cellContent = `<div style="font-size:0.7rem; font-weight:bold;">${(res.nombre_cliente || 'Reserva').substring(0, 8)}</div>`;
                        if (res.id_reserva) clickEvt = `onclick="abrirDetalleDesdePlanilla(${res.id_reserva})"`;
                    } else { opacity = '0.05'; cellContent = ''; }
                } else {
                    if (filtroEstado && filtroEstado !== 'disponible') opacity = '0.05';
                }
                html += `<td class="${bgClass}" style="height:40px; cursor:${clickEvt ? 'pointer' : 'default'}; opacity:${opacity}; width:110px; min-width:110px; max-width:110px; padding:2px;" ${clickEvt}>${cellContent}</td>`;
            });
            html += `</tr>`;
        }
    });
    table.innerHTML = html;
}

// Funciones de Modal y Detalle
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
        const response = await fetch('../api/canchaboard.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData, credentials: 'include' });
        const detalle = await response.json();
        if (detalle.error) throw new Error(detalle.error);
        window.reservaActualSeleccionada = detalle;

        if (container) {
            const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
            const money = (v) => '$' + parseInt(v || 0).toLocaleString();
            container.innerHTML = `
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
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                            <span style="color:#666; font-size:0.9rem;">Monto Total</span>
                            <span style="font-weight:bold; font-size:1.1rem;">${money(detalle.monto_total)}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                            <span style="color:#666; font-size:0.9rem;">Abonado</span>
                            <span style="font-weight:bold; color:#2e7d32;">${money(detalle.monto_recaudacion || 0)}</span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:1rem; text-align:center;">
                    <button onclick="toggleActionMenuModal()" style="background:#071289; color:white; border:none; padding:0.6rem 1.5rem; border-radius:8px; cursor:pointer; width:100%;">⚙️ Opciones</button>
                    <div id="actionMenuModal" style="display:none; margin-top:10px; border:1px solid #ddd; border-radius:8px; background:white;">
                        <button onclick="anularReserva()" style="width:100%; padding:10px; border:none; background:none; text-align:left; border-bottom:1px solid #eee;">🗑️ Anular</button>
                        ${detalle.estado_pago !== 'pagado' ? `<button onclick="abrirModalPagoDesdeDetalle()" style="width:100%; padding:10px; border:none; background:#e8f5e9; color:#2e7d32; text-align:left; font-weight:bold;">💳 Pagar</button>` : ''}
                    </div>
                </div>
            `;
        }
    } catch (err) { console.error(err); }
}

function toggleMenu(e) { e.stopPropagation(); document.getElementById('adminMenu').style.display = 'block'; }
function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
function cerrarModalDetalle() { document.getElementById('modalDetalleReserva').style.display = 'none'; }
function volverAlDetalle() { document.getElementById('modalPago').style.display = 'none'; document.getElementById('modalDetalleReserva').style.display = 'flex'; }
function cerrarModalListaKPI() { document.getElementById('modalListaKPI').style.display = 'none'; }
function toggleActionMenuModal() {
    const menu = document.getElementById('actionMenuModal');
    if (menu) menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}
function anularReserva() { alert("Función Anular: En desarrollo"); }
function abrirModalPagoDesdeDetalle() {
    if (!window.reservaActualSeleccionada) return;
    const detalle = window.reservaActualSeleccionada;
    document.getElementById('infoIdReserva').textContent = detalle.id_reserva;
    document.getElementById('infoMontoTotal').textContent = '$' + parseFloat(detalle.monto_total).toLocaleString();
    document.getElementById('montoPagar').value = detalle.monto_total;
    document.getElementById('formPago').dataset.idReserva = detalle.id_reserva;
    document.getElementById('formPago').dataset.montoOriginal = detalle.monto_total;
    document.getElementById('modalDetalleReserva').style.display = 'none';
    document.getElementById('modalPago').style.display = 'flex';
}
document.getElementById('metodoPago')?.addEventListener('change', function() {
    const campo = document.getElementById('campoTransaccion');
    if (['transferencia', 'webpay'].includes(this.value)) { campo.style.display = 'block'; } else { campo.style.display = 'none'; }
});
document.getElementById('formPago')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    alert("Pago simulado. Implementar backend.");
    document.getElementById('modalPago').style.display = 'none';
    location.reload();
});

async function abrirListaKPI(tipo) {
    document.getElementById('modalListaKPI').style.display = 'flex';
    document.getElementById('tituloListaKPI').textContent = tipo === 'parcial' ? 'Pagos Parciales' : 'Deuda Vencida';
    document.getElementById('cuerpoTablaKPI').innerHTML = '<tr><td colspan="8" style="text-align:center;">Datos de ejemplo</td></tr>';
}
</script>
</body>
</html>