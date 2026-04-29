<?php
// pages/recinto_dashboard.php
require_once __DIR__ . '/../includes/config.php';

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

// === CÁLCULO KPIs - VERSIÓN CORREGIDA (SQL limpio) ===
$hoy = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');
$primer_dia_mes_ant = date('Y-m-01', strtotime('-1 month'));
$ultimo_dia_mes_ant = date('Y-m-t', strtotime('-1 month'));

// Función helper para sumas con prepared statements
function getSuma($pdo, $id_recinto, $fecha_op, $fecha_val, $pago_cond) {
    // Construir condición de fecha
    if ($fecha_op === 'between') {
        $fecha_clause = "r.fecha BETWEEN :fecha_start AND :fecha_end";
    } elseif ($fecha_op === '>') {
        $fecha_clause = "DATE(r.fecha) > :fecha_val";
    } else {
        $fecha_clause = "r.fecha >= :fecha_val";
    }
    
    $q = "SELECT COALESCE(SUM(r.monto_total), 0) 
          FROM reservas r 
          JOIN canchas c ON r.id_cancha = c.id_cancha 
          WHERE c.id_recinto = :id 
          AND $fecha_clause 
          AND r.estado_pago $pago_cond 
          AND r.estado != 'cancelada'";
    
    $s = $pdo->prepare($q);
    
    if ($fecha_op === 'between') {
        $s->execute([
            ':id' => $id_recinto,
            ':fecha_start' => $fecha_val[0],
            ':fecha_end' => $fecha_val[1]
        ]);
    } else {
        $s->execute([':id' => $id_recinto, ':fecha_val' => $fecha_val]);
    }
    
    return $s->fetchColumn();
}

// === CÁLCULO KPIs - VERSIÓN PRODUCCIÓN (variables inicializadas + SQL limpio) ===
$hoy = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');
$primer_dia_mes_ant = date('Y-m-01', strtotime('-1 month'));
$ultimo_dia_mes_ant = date('Y-m-t', strtotime('-1 month'));

// Inicializar TODAS las variables por defecto (evita warnings)
$ingresos_act = 0;
$ingresos_ant = 0;
$var_ing = 0;
$monto_pendiente = 0;      // Saldo pendiente de pagos parciales
$monto_en_reserva = 0;     // Monto total de reservas futuras no pagadas
$cant_en_reserva = 0;      // Cantidad de reservas futuras no pagadas
$monto_deuda = 0;          // Deuda vencida (reservas pasadas no pagadas)

try {
    // === INGRESOS MES ACTUAL (pagados) ===
    $q_act = "SELECT COALESCE(SUM(r.monto_total), 0) 
              FROM reservas r 
              JOIN canchas c ON r.id_cancha = c.id_cancha 
              WHERE c.id_recinto = :id 
              AND r.fecha >= :fecha 
              AND r.estado_pago = 'pagado' 
              AND r.estado != 'cancelada'";
    $s_act = $pdo->prepare($q_act);
    $s_act->execute([':id' => $id_recinto, ':fecha' => $primer_dia_mes]);
    $ingresos_act = $s_act->fetchColumn() ?: 0;

    // === INGRESOS MES ANTERIOR (pagados) ===
    $q_ant = "SELECT COALESCE(SUM(r.monto_total), 0) 
              FROM reservas r 
              JOIN canchas c ON r.id_cancha = c.id_cancha 
              WHERE c.id_recinto = :id 
              AND r.fecha BETWEEN :start AND :end 
              AND r.estado_pago = 'pagado' 
              AND r.estado != 'cancelada'";
    $s_ant = $pdo->prepare($q_ant);
    $s_ant->execute([
        ':id' => $id_recinto, 
        ':start' => $primer_dia_mes_ant, 
        ':end' => $ultimo_dia_mes_ant
    ]);
    $ingresos_ant = $s_ant->fetchColumn() ?: 0;

    // === VARIACIÓN PORCENTUAL ===
    if ($ingresos_ant > 0) {
        $var_ing = round((($ingresos_act - $ingresos_ant) / $ingresos_ant) * 100, 1);
    } elseif ($ingresos_act > 0) {
        $var_ing = 100;
    }

    // === SALDO PENDIENTE (pagos parciales: monto faltante por cobrar) ===
    $q_parcial = "SELECT COALESCE(SUM(r.monto_total - r.monto_recaudacion), 0) 
                  FROM reservas r 
                  JOIN canchas c ON r.id_cancha = c.id_cancha 
                  WHERE c.id_recinto = :id 
                  AND r.fecha >= :fecha 
                  AND r.estado_pago = 'parcial' 
                  AND r.estado != 'cancelada'";
    $s_parcial = $pdo->prepare($q_parcial);
    $s_parcial->execute([':id' => $id_recinto, ':fecha' => $primer_dia_mes]);
    $monto_pendiente = $s_parcial->fetchColumn() ?: 0;

    // === EN RESERVA: Monto total de reservas FUTURAS no pagadas (pendiente + parcial) ===
    $q_reserva = "SELECT COALESCE(SUM(r.monto_total), 0) 
                  FROM reservas r 
                  JOIN canchas c ON r.id_cancha = c.id_cancha 
                  WHERE c.id_recinto = :id 
                  AND DATE(r.fecha) > :hoy 
                  AND r.estado_pago IN ('pendiente', 'parcial') 
                  AND r.estado != 'cancelada'";
    $s_reserva = $pdo->prepare($q_reserva);
    $s_reserva->execute([':id' => $id_recinto, ':hoy' => $hoy]);
    $monto_en_reserva = $s_reserva->fetchColumn() ?: 0;

    // Cantidad de reservas en reserva (para subtexto)
    $q_cant_reserva = "SELECT COUNT(*) 
                       FROM reservas r 
                       JOIN canchas c ON r.id_cancha = c.id_cancha 
                       WHERE c.id_recinto = :id 
                       AND DATE(r.fecha) > :hoy 
                       AND r.estado_pago IN ('pendiente', 'parcial') 
                       AND r.estado != 'cancelada'";
    $s_cant_reserva = $pdo->prepare($q_cant_reserva);
    $s_cant_reserva->execute([':id' => $id_recinto, ':hoy' => $hoy]);
    $cant_en_reserva = $s_cant_reserva->fetchColumn() ?: 0;

    // === DEUDA VENCIDA: reservas PASADAS no pagadas ===
    $q_deuda = "SELECT COALESCE(SUM(r.monto_total), 0) 
                FROM reservas r 
                JOIN canchas c ON r.id_cancha = c.id_cancha 
                WHERE c.id_recinto = :id 
                AND DATE(r.fecha) < :hoy 
                AND r.estado_pago IN ('pendiente', 'parcial') 
                AND r.estado != 'cancelada'";
    $s_deuda = $pdo->prepare($q_deuda);
    $s_deuda->execute([':id' => $id_recinto, ':hoy' => $hoy]);
    $monto_deuda = $s_deuda->fetchColumn() ?: 0;

} catch (PDOException $e) {
    // Logging seguro sin exponer detalles en producción
    error_log("❌ [KPI ERROR] " . $e->getMessage() . " | recinto: $id_recinto");
    // Las variables ya están inicializadas en 0, así que no rompe la UI
}

// === DEBUG KPIs (descomentar solo para pruebas) ===
error_log("🔍 [KPI DEBUG] recinto: $id_recinto | hoy: $hoy");
error_log("🔍 [KPI DEBUG] monto_en_reserva: $monto_en_reserva | cant: $cant_en_reserva");
error_log("🔍 [KPI DEBUG] monto_pendiente: $monto_pendiente | deuda: $monto_deuda");

// Verificar si hay reservas futuras no pagadas
$debug_q = "SELECT r.id_reserva, r.fecha, r.monto_total, r.estado_pago, r.monto_recaudacion
            FROM reservas r 
            JOIN canchas c ON r.id_cancha = c.id_cancha 
            WHERE c.id_recinto = ? 
            AND DATE(r.fecha) > ? 
            AND r.estado_pago IN ('pendiente', 'parcial') 
            AND r.estado != 'cancelada'
            ORDER BY r.fecha ASC LIMIT 5";
$debug_s = $pdo->prepare($debug_q);
$debug_s->execute([$id_recinto, $hoy]);
$debug_rows = $debug_s->fetchAll();
if ($debug_rows) {
    foreach ($debug_rows as $row) {
        error_log("   ✅ Reserva ID:{$row['id_reserva']} Fecha:{$row['fecha']} Monto:{$row['monto_total']} Estado:{$row['estado_pago']}");
    }
} else {
    error_log("   ⚠️ No se encontraron reservas futuras no pagadas para este recinto");
}
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

    /* === 3. GRID PRINCIPAL (Ajustar gaps para mejor distribución) === */
    .main-layout {
        display: grid; 
        grid-template-columns: 320px 1fr 220px !important; /* ✅ Columna acciones más ancha */
        gap: 1rem !important; /* ✅ Reducir gap para aprovechar espacio */
        width: 98%; 
        margin: 0 auto; 
        padding: 0.5rem; 
        height: calc(100vh - 60px); 
        align-items: start;
    }
    
    .planilla-column { background: transparent; display: flex; flex-direction: column; height: 100%; position: relative; justify-content: flex-start; align-items: center; }    
    .planilla-table-container { flex: 1; overflow: auto; padding: 4px; width: max-content !important; min-width: 940px; background: transparent; }

    /* === 3. TÍTULOS KPIs (Grafito) === */
    .kpi-card-mini div:first-child {
        color: #4A4A4A !important; /* ✅ Grafito oscuro */
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        opacity: 1 !important; /* Quitar transparencia antigua */
        letter-spacing: 0.5px;
    }
    .kpi-column { margin-top: 50px; padding: 0 1rem; }
    .kpi-card-mini { background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border-left: 4px solid #ccc; padding: 0.8rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin-bottom: 0.8rem; }
    .kpi-card-mini:hover { transform: translateX(-3px); }
    .kpi-card-mini div:nth-child(2) { font-size: 1.4rem; font-weight: 900; line-height: 1.2; margin: 0.3rem 0; }
    .kpi-card-mini div:last-child { font-size: 0.7rem; opacity: 0.7; }
    .kpi-ingresos { border-left-color: #4CAF50; background: #E8F5E9; } .kpi-ingresos div:nth-child(2) { color: #1B5E20 !important; font-weight:bold; }
    .kpi-parcial { border-left-color: #FBC02D; background: #FFFDE7; cursor: pointer; } .kpi-parcial div:nth-child(2) { color: #EF6C00 !important; font-weight:bold; }
    .kpi-reserva { border-left-color: #2196F3; background: #E3F2FD; } .kpi-reserva div:nth-child(2) { color: #0D47A1 !important; font-weight:bold; }
    .kpi-deuda { border-left-color: #EF5350; background: #FFEBEE; cursor: pointer; } .kpi-deuda div:nth-child(2) { color: #B71C1C !important; font-weight:bold; }
    
    /* === 2. PANEL TORNEOS (Ancho completo + Posición corregida) === */
    .torneos-panel-container {
        /* Posicionamiento */
        grid-column: 2 / -1 !important; /* ✅ Ocupa desde columna central hasta el final */
        margin-top: -1.5rem !important; /* ✅ Sube el panel para eliminar espacio vacío */
        margin-left: 0 !important;
        margin-right: 0 !important;
        
        /* Dimensiones */
        width: calc(100% - 2rem) !important; /* Ancho completo con márgenes laterales */
        max-width: 1380px !important;
        
        /* Visibilidad y estilo */
        background: rgba(255,255,255,0.98) !important;
        border-radius: 12px !important;
        padding: 1.5rem !important;
        color: #333 !important;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.2) !important;
        z-index: 50 !important;
        position: relative !important;
        
        /* Animación */
        display: none; /* Oculto por defecto, JS lo muestra */
        animation: slideUpPanel 1s ease-out !important;
        transform-origin: top center;
    }

    /* Animación de entrada */
    @keyframes slideUpPanel {
        from { 
            opacity: 0; 
            transform: translateY(20px) scaleY(0.95);
            max-height: 0;
            padding: 0 1.5rem;
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scaleY(1);
            max-height: 80vh;
            padding: 1.5rem;
        }
    }
    /* === 4. CONTENIDO DEL PANEL (Grid de tarjetas responsive) === */
    #listaTorneos {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important; /* ✅ Tarjetas más anchas */
        gap: 1rem !important;
        width: 100% !important;
        margin-top: 0.5rem;
    }

    /* === TARJETAS CON ANIMACIÓN DE ENTRADA === */
    @keyframes fadeInCard {
        to { opacity: 1; transform: translateY(0); }
    }

   /* Staggered animation para efecto cascada */
    #listaTorneos > div:nth-child(1) { animation-delay: 0.1s; }
    #listaTorneos > div:nth-child(2) { animation-delay: 0.15s; }
    #listaTorneos > div:nth-child(3) { animation-delay: 0.2s; }
    #listaTorneos > div:nth-child(n+4) { animation-delay: 0.25s; }

    /* Asegurar que el grid funcione */
    #listaTorneos {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
        gap: 1rem !important;
        width: 100% !important;
    }
    /* === 5. RESPONSIVE (Ajustes para pantallas pequeñas) === */
    @media (max-width: 1200px) {
        .main-layout {
            grid-template-columns: 280px 1fr 200px !important; /* Reducir en tablets */
        }
        .actions-column {
            width: 280px !important;
            min-width: 280px !important;
            max-width: 280px !important;
        }
        .torneos-panel-container {
            grid-column: 1 / -1 !important; /* Ocupar todo el ancho en móvil */
            margin-top: 1rem !important;
        }
    }

    @media (max-width: 768px) {
        .main-layout {
            grid-template-columns: 1fr !important; /* Una sola columna en móvil */
            height: auto !important;
            display: block !important;
        }
        .actions-column {
            width: 100% !important;
            min-width: auto !important;
            max-width: none !important;
            flex-direction: row !important;
            overflow-x: auto !important;
            padding: 0.5rem 0 !important;
            margin-top: 1rem !important;
        }
        .action-btn-sidebar {
            min-width: 180px !important;
            width: auto !important;
        }
        .torneos-panel-container {
            width: 100% !important;
            margin: 1rem 0 !important;
        }
    }

    /* === CELDAS CON ROWSPAN === */
    td.cell-reserva[rowspan] {
        vertical-align: middle !important;
        text-align: center !important;
        /* NO display:flex aquí, rompe rowspan */
    }

    /* MODALES */
    #modalDetalleReserva, #modalPago, #modalListaKPI { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; justify-content:center; align-items:center; backdrop-filter: blur(4px); }
    #modalPago { z-index: 2500; } #modalListaKPI { z-index: 3000; }
    #modalPago label { color: #333 !important; font-weight: bold; }
    #modalPago small, #modalPago span, #modalPago div { color: #555 !important; } #modalPago h3 { color: #071289 !important; }
    #modalListaKPI { color: #4A4A4A !important; /* Grafito oscuro */ !important; } #modalListaKPI h3, #modalListaKPI th, #modalListaKPI td, #modalListaKPI span, #modalListaKPI div { color: #333 !important; }
    #modalListaKPI span[onclick="cerrarModalListaKPI()"] { color: #999 !important; }
    #modalListaKPI td:last-child:nth-last-child(2) { color: #c62828 !important; }

    /* UTILIDADES & ANIMACIONES */
    .section-divider { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; color: white; font-weight: bold; font-size: 0.9rem; margin-bottom: 0.5rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5); opacity: 0.9; padding: 0.5rem; border-radius: 6px; transition: background 0.2s; cursor: pointer; }
    .section-divider:hover { background: rgba(255,255,255,0.1); }
    .rotated { transform: rotate(-180deg); }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes pulse { 0% { transform:scale(1); } 50% { transform:scale(1.03); } 100% { transform:scale(1); } }


    /* 🎯 DRAG & DROP - VERSIÓN ESTABLE PARA TABLAS */
    .cell-reserva { 
        cursor: grab !important; 
        transition: opacity 0.2s, transform 0.2s; 
    }
    .cell-reserva:active { cursor: grabbing; }
    .cell-reserva.dragging {
        opacity: 0.4 !important;
        border: 3px dashed #333 !important;
        transform: scale(0.96) !important;
        background: rgba(255,255,255,0.3) !important;
        z-index: 9999 !important;
        /* ❌ ELIMINADO: pointer-events: none; (esto cancela el drag) */
    }
    td.drop-target {
        background: #FFCDD2 !important;
        box-shadow: inset 0 0 0 3px #EF5350 !important;
        transform: scale(1.04) !important;
        z-index: 10 !important;
    }
    td.coord-highlight, th.coord-highlight {
        background: #FFF8E1 !important;
        font-weight: 900 !important;
        transform: scale(1.12) !important;
        border: 2px solid #FFA000 !important;
        z-index: 20 !important;
    }
    .drop-anim { animation: dropSuccess 0.3s ease-out; }
    @keyframes dropSuccess { 0% { transform: scale(1.03); } 50% { transform: scale(0.98); } 100% { transform: scale(1); } }
 
    .drop-zone { transition: all 0.2s ease; }
    @media (max-width: 1024px) {
        .main-layout { grid-template-columns: 1fr !important; height: auto; display: block; }
        .planilla-header-controls { min-width: 100%; width: 100%; overflow-x: auto; }
        .kpi-column { margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; padding-right: 0; }
        .actions-column { margin-top: 1rem; flex-direction: row; overflow-x: auto; padding-left: 0; }
        .torneos-panel-container { grid-column: auto; }
    }

    /* === 1. COLUMNA DE ACCIONES (Más ancha para mejor UX) === */
    .actions-column { 
        display: flex; 
        flex-direction: column; 
        gap: 1rem; 
        padding-left: 1rem;
        margin-top: 60px;
        width: 200px !important;
        min-width: 200px !important;
        max-width: 200px !important;
        flex-shrink: 0; /* Evita que se encoja en pantallas pequeñas */
    }
    /* === BOTONES OPERACIONES ESTILO PÍLDORA === */
.action-btn-sidebar {
    width: 100% !important;
    justify-content: center !important; /* Centrar icono y texto */
    text-align: center !important;
    padding: 0.8rem 1rem !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    
    /* Estilo Píldora */
    border-radius: 50px !important; /* Bordes totalmente redondeados */
    background: rgba(255, 255, 255, 0.9) !important; /* Fondo blanco suave */
    color: #071289 !important; /* Texto azul oscuro */
    border: 1px solid rgba(255,255,255,0.5) !important;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease !important;
    font-weight: 600 !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.action-btn-sidebar:hover {
    transform: translateY(-2px) !important;
    background: white !important;
    box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
    color: #BA68C8 !important; /* Color morado al hover */
}

.action-btn-sidebar span {
    font-size: 1.2rem;
}

/* === DEBUG VISUAL: Outline para ver rowspan === */
/* (Comentar en producción) */
td[rowspan="3"] {
    outline-color: rgba(255, 0, 0, 0.7) !important;
}

/* Centrar contenido dentro de celda con rowspan */
td.cell-reserva[rowspan] > div:first-child {
    margin-top: -5px; /* Ajuste fino para centrar */
}
td.cell-reserva[rowspan] > div:last-child {
    margin-bottom: -5px;
}

.date-nav-btn {
        background: white; border: 1px solid #ddd; border-radius: 50%; width: 32px; height: 32px;
        color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
.date-nav-btn:hover { background: #f0f0f0; }
/* 2. Inputs y Selectores (Bordes Ovalados) */
.control-select,
.control-input {
    background: white; 
    padding: 0.4rem 0.8rem; 
    border-radius: 20px; /* ✅ Bordes redondeados */
    color: #4A4A4A !important; /* Grafito oscuro */
    border: 1px solid #ddd; 
    font-weight: bold;
    min-width: 120px; 
    font-size: 0.85rem;
    height: 32px; /* Altura uniforme con botones */
    cursor: pointer;
    outline: none;
}
/* Ajuste específico para el calendario */
.control-input {
    min-width: 135px;
}
/* 3. Botones Circulares (< >) */
.date-nav-btn {
    background: white; 
    border: 1px solid #ddd; 
    border-radius: 50%; /* ✅ Círculo perfecto */
    width: 32px; 
    height: 32px;
    color: #555; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-weight: bold;
    transition: background 0.2s;
}
.date-nav-btn:hover { background: #f0f0f0; }
/* 4. Botón Hoy (Ovalado) */
.planilla-header-controls button[onclick="irAHoy()"] {
    background: white;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 0 12px;
    height: 32px;
    font-size: 0.8rem;
    font-weight: bold;
    color: #555;
    cursor: pointer;
    transition: background 0.2s;
}
.planilla-header-controls button[onclick="irAHoy()"]:hover {
    background: #f0f0f0;
}
/* === 1. HEADER FILTROS (Sticky + Largo completo + Sin amontonamiento) === */
.planilla-header-controls {
    position: sticky !important;
    top: 50px !important; /* Justo debajo de la top-bar */
    z-index: 900 !important;
    background: rgba(21, 101, 192, 0.95) !important;
    backdrop-filter: blur(12px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.8rem 1.5rem;
    border-radius: 50px;
    margin: 0 auto 1rem auto; /* Centrado */
    width: 96% !important; /* Largo completo controlado */
    max-width: 1380px !important;
    min-width: 340px !important;
    display: flex;
    flex-wrap: wrap; /* Ajuste seguro sin superponer */
    justify-content: center;
    align-items: center;
    gap: 0.6rem;
    color: white;
    transform: translateZ(0); /* Fix para sticky en Safari/Chrome */
    transition: box-shadow 0.3s ease;
}

/* Sombra sutil cuando el usuario hace scroll */
.planilla-column.scrolled .planilla-header-controls {
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.control-group {
        background: white; padding: 0.4rem 0.8rem; border-radius: 20px; color: #071289; border: none; font-weight: bold;
        min-width: 120px; font-size: 0.85rem;
}
/* Opcional: Ajustar placeholders para que no choquen */
::placeholder { color: #888 !important; }

/* === 🎯 TABLA DASHBOARD (UNIFICADA Y STICKY CORREGIDA) === */

/* 1. Contenedor de Scroll (Contexto OBLIGATORIO para sticky) */
.planilla-table-container {
    flex: 1;
    overflow: auto !important; /* Sin esto, sticky no funciona */
    background: transparent;
    padding: 0 !important; /* Padding rompe sticky en Chrome/Safari */
    min-height: 0; /* Fix para hijos flex */
}

/* 2. Estructura de la Tabla */
 .planilla-table {
    width: auto; border-collapse: separate; border-spacing: 6px; background: transparent;
    table-layout: fixed; min-width: 600px; margin: 0 auto;
}

/* 3. Fila de Nombres de Cancha (Sticky Superior) */
.planilla-table thead th {
    background: rgba(255,255,255,0.9) !important; color: #4A4A4A; position: sticky; top: 0; z-index: 5;
    border: none; border-radius: 12px; padding: 10px; font-size: 0.85rem; font-weight: bold;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* 4. Columna de Horas (Sticky Izquierda) */
.planilla-table td:first-child {
    position: sticky; left: 0; z-index: 10;
    background: rgba(255,255,255,0.95) !important; color: #555; font-weight: bold;
    border: none; border-radius: 10px; width: 70px !important; text-align: center;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

/* 5. Esquina Superior Izquierda (Hora + Header Cancha) */
.planilla-table thead th {
        background: rgba(255,255,255,0.9) !important; color: #4A4A4A; position: sticky; top: 0; z-index: 5;
        border: none; border-radius: 12px; padding: 10px; font-size: 0.85rem; font-weight: bold;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* 6. Celdas Generales y Estados */
.planilla-table td {
        padding: 8px 4px; vertical-align: middle; text-align: center;
        border: none; border-radius: 10px; transition: all 0.2s ease; height: 40px;
}

td.estado-disponible { background: rgba(255,255,255,0.1) !important; border: 1px dashed rgba(255,255,255,0.3) !important; cursor: pointer; }
td.estado-pendiente { background: #FF5252 !important; color: white !important; border: none !important; }
td.estado-pagado { background: #4CAF50 !important; color: white !important; border: none !important; }
td.estado-parcial { background: #FFEB3B !important; color: #333 !important; border: none !important; }
td.cell-reserva { cursor: grab !important; vertical-align: middle !important; text-align: center !important; }

/* Hover */
.planilla-table td:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    z-index: 5;
    position: relative;
}
/* === MODAL TORNEOS (Overlay + Animación desde abajo) === */
.torneos-modal-overlay {
    position: fixed !important;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.65) !important; /* Fondo oscuro semitransparente */
    backdrop-filter: blur(6px) !important;
    z-index: 3000 !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    padding: 1.5rem;
    box-sizing: border-box;
    opacity: 0;
    pointer-events: none;
    transform: translateY(100%); /* Empieza desde el fondo */
    transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
}

.torneos-modal-overlay.active {
    opacity: 1 !important;
    transform: translateY(0) !important; /* Sube al centro */
    pointer-events: auto !important;
}
/* Tarjeta interna blanca */
.torneos-card {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 960px; /* Ancho óptimo para ver 2-3 tarjetas */
    max-height: 85vh;
    overflow-y: auto;
    padding: 1.5rem;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    transform: translateY(30px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) 0.1s;
}

.torneos-modal-overlay.active .torneos-card {
    transform: translateY(0);
    opacity: 1;
}
/* === ESTILOS DE BOTONES EN FICHAS === */
.btn-torneo {
    padding: 0.55rem 0.8rem !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    font-size: 0.82rem !important;
    text-align: center !important;
    transition: all 0.2s ease !important;
    flex: 1 !important;
    min-width: 110px !important;
    display: inline-block !important;
    border: none !important;
    cursor: pointer !important;
}
.btn-torneo:hover { transform: translateY(-2px) !important; box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important; }

.btn-invitar    { background: #E0F7FA !important; color: #006064 !important; }
.btn-fixture    { background: #071289 !important; color: white !important; }
.btn-ver-fixture{ background: #f0f0f0 !important; color: #333 !important; }
.btn-resultados { background: #071289 !important; color: white !important; }

/* Grid responsive para tarjetas */
#listaTorneos > div {
    background: #fafafa;
    border-radius: 12px;
    padding: 1.2rem;
    border-left: 4px solid #ccc;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}
#listaTorneos > div:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
/* === SUBMODAL MODERNO (Animación + Blur) === */
.submodal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    z-index: 4000;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.submodal-overlay.active {
    opacity: 1;
    pointer-events: auto;
}
.submodal-card {
    background: white;
    border-radius: 16px;
    width: 95%;
    max-width: 800px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    transform: scale(0.95) translateY(20px);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.submodal-overlay.active .submodal-card {
    transform: scale(1) translateY(0);
}
.submodal-close {
    position: absolute;
    top: 12px; right: 16px;
    background: #f0f0f0;
    border: none;
    width: 32px; height: 32px;
    border-radius: 50%;
    font-size: 1.4rem;
    cursor: pointer;
    color: #666;
    display: flex; align-items: center; justify-content: center;
    transition: 0.2s;
    z-index: 10;
}
.submodal-close:hover { background: #e0e0e0; color: #c62828; }

/* === BOTONES DE ACCIÓN (Reutilizables) === */
.action-btn {
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

/* === TABLA DE FIXTURE === */
.fixture-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    background: white;
}
.fixture-table th {
    background: #071289;
    color: white;
    padding: 0.8rem;
    text-align: left;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 5;
}
.fixture-table td {
    padding: 0.8rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
.fixture-table tr:last-child td { border-bottom: none; }
.fixture-table tr:hover { background: #f9f9f9; }

/* === BOTÓN RESULTADO === */
.btn-resultado {
    background: #071289;
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: 0.2s;
}
.btn-resultado:hover { background: #050d66; transform: translateY(-2px); }
/* === MODAL INSCRITOS Y FIXTURE === */
.torneo-submodal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.75); backdrop-filter: blur(8px);
    z-index: 4500; display: none; justify-content: center; align-items: center;
    padding: 1rem; opacity: 0; transition: opacity 0.3s ease;
}
.torneo-submodal-overlay.active { opacity: 1; pointer-events: auto; }

.torneo-submodal-card {
    background: white; border-radius: 16px; width: 95%; max-width: 900px;
    max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex; flex-direction: column;
}
.torneo-submodal-overlay.active .torneo-submodal-card { transform: scale(1); }

.torneo-header {
    padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;
    background: #f8f9fa; border-radius: 16px 16px 0 0;
}
.torneo-body { padding: 1.5rem; flex: 1; }

/* Tabla de Inscritos */
.tabla-inscritos th { background: #071289; color: white; padding: 0.8rem; text-align: left; font-size: 0.9rem; }
.tabla-inscritos td { padding: 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
.btn-bajar-pareja {
    background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; padding: 0.4rem 0.8rem;
    border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: bold; transition: 0.2s;
}
.btn-bajar-pareja:hover { background: #ef9a9a; color: white; }

/* Fixture por Sets */
.set-container { margin-bottom: 2rem; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; }
.set-header { background: #f5f5f5; padding: 0.8rem 1.2rem; font-weight: bold; color: #071289; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; }
.partido-row { display: grid; grid-template-columns: 2fr 1fr 2fr 1fr 1fr; gap: 1rem; padding: 1rem 1.2rem; align-items: center; border-bottom: 1px solid #f0f0f0; }
.partido-row:last-child { border-bottom: none; }
.pareja-nombre { font-weight: 600; color: #333; font-size: 0.95rem; }
.vs-badge { text-align: center; font-weight: bold; color: #999; font-size: 0.8rem; }
.resultado-input { width: 60px; padding: 0.4rem; text-align: center; border: 1px solid #ddd; border-radius: 6px; font-weight: bold; }
.btn-guardar-set { background: #4CAF50; color: white; border: none; padding: 0.4rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
.btn-guardar-set:disabled { background: #ccc; cursor: not-allowed; }
.marcador-final { font-weight: 800; color: #071289; font-size: 1.1rem; text-align: center; }
.ganador-highlight { color: #2E7D32; font-weight: 800; }

/* === TARJETAS DE PARTIDO EN FIXTURE === */
.partido-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0.8rem 0;
    background: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid transparent;
}
.partido-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
}
.partido-card.jugado {
    border-left-color: #4CAF50; /* Borde verde si ya se jugó */
}
.partido-card.pendiente {
    border-left-color: #FFC107; /* Borde amarillo si falta jugar */
}

.pareja-nombre {
    flex: 1;
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}
.pareja-nombre.ganador {
    color: #2E7D32; /* Verde oscuro para ganador */
    font-weight: 800;
}

.marcador-box {
    min-width: 80px;
    text-align: center;
    font-weight: bold;
    font-size: 1.1rem;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    background: #f5f5f5;
    color: #666;
}
.marcador-box.finalizado {
    background: #E8F5E9;
    color: #2E7D32;
}

.btn-accion-result {
    background: #071289;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background 0.2s;
    margin-left: 1rem;
}
.btn-accion-result:hover {
    background: #050d6b;
}

/* === MODAL DE EDICIÓN DE RESULTADO (UX Mejorada) === */
.resultado-editor {
    text-align: center;
    max-width: 400px;
    margin: 0 auto;
    padding: 1rem;
}
.resultado-inputs {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    margin: 2rem 0;
}
.input-juegos {
    width: 80px;
    padding: 0.8rem;
    text-align: center;
    font-size: 1.5rem;
    font-weight: bold;
    border: 2px solid #ddd;
    border-radius: 12px;
    color: #071289;
    transition: border-color 0.3s;
}
.input-juegos:focus {
    border-color: #071289;
    outline: none;
}
.vs-divider {
    font-size: 1.5rem;
    font-weight: bold;
    color: #ccc;
}
.preview-ganador {
    margin: 1.5rem 0;
    font-weight: bold;
    color: #4CAF50;
    font-size: 1.1rem;
    min-height: 1.5rem;
}
/* === MODAL COMPACTO MÓVIL === */
.submodal-card.compact-modal {
    max-width: 320px !important; /* Ancho tipo móvil */
    padding: 1rem !important;
}
.compact-inputs {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin: 1.5rem 0;
}
.compact-input {
    width: 60px;
    height: 60px;
    font-size: 1.8rem;
    text-align: center;
    border: 2px solid #071289;
    border-radius: 12px;
    font-weight: bold;
    color: #071289;
}
.compact-btn-save {
    background: #4CAF50;
    color: white;
    width: 100%;
    padding: 0.8rem;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1rem;
    margin-top: 1rem;
}
.fixture-container {
    max-height: 75vh; /* Ocupa el 75% de la pantalla */
    overflow-y: auto; /* Scroll interno suave si es necesario */
    padding-right: 5px;
}
.set-header { font-size: 0.85rem; padding: 0.5rem; }
.partido-card { padding: 0.6rem; margin: 0.4rem 0; }
.pareja-nombre { font-size: 0.85rem; }
/* === MODAL FIXTURE ANCHO CONTROLADO === */
.submodal-card.fixture-width {
    max-width: 600px !important; /* Equivalente a ~50-60 caracteres */
    width: 95% !important;
}
/* === Estilos para sección recurrente === */
#modalReservaAdmin {
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#recurrentFields {
    border: 1px solid #E2E8F0;
    transition: all 0.2s;
}

#recurrentFields:hover {
    border-color: #AB47BC;
    box-shadow: 0 2px 8px rgba(171,71,188,0.1);
}

#previewDates {
    font-weight: 500;
    transition: color 0.2s;
}
/* === MODAL OVERLAY - CENTRADO PERFECTO === */
.modal-overlay {
    position: fixed;           /* Fijo respecto a la ventana */
    inset: 0;                  /* Top:0, Right:0, Bottom:0, Left:0 */
    background: rgba(0, 0, 0, 0.55);  /* Fondo semitransparente */
    backdrop-filter: blur(4px);       /* Efecto blur opcional */
    display: flex;                   /* Flexbox para centrar */
    justify-content: center;         /* Centrado horizontal */
    align-items: center;             /* Centrado vertical */
    z-index: 2000;                   /* Por encima de todo */
    padding: 1rem;                   /* Espacio en móviles */
    opacity: 0;                      /* Para animación de entrada */
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s ease;
}

/* Cuando el modal está activo */
.modal-overlay.active,
.modal-overlay[style*="display: flex"] {
    opacity: 1;
    visibility: visible;
}

/* === CONTENIDO DEL MODAL === */
.modal-content {
    background: white;
    border-radius: 20px;
    padding: 1.75rem;
    max-width: 520px;
    width: 100%;
    max-height: 90vh;        /* Para que quepa en pantallas pequeñas */
    overflow-y: auto;        /* Scroll interno si es necesario */
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);  /* Sombra profunda */
    position: relative;
    animation: modalSlideUp 0.3s ease-out;
    border: 1px solid rgba(255, 255, 255, 0.9);
}

/* Animación de entrada suave */
@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive móvil */
@media (max-width: 480px) {
    .modal-content {
        padding: 1.5rem;
        margin: 0.5rem;
        border-radius: 16px;
    }
}
/* === TEXTOS DENTRO DEL MODAL - CONTRASTE GARANTIZADO === */
.modal-content label,
.modal-content span,
.modal-content strong,
.modal-content div[id*="Display"] {
    color: #2D3748 !important;  /* Gris oscuro casi negro */
}

/* Excepción: títulos principales pueden mantener color púrpura */
.modal-content h3,
.modal-content [id="modalCanchaDisplay"] {
    color: #6A1B9A !important;
}

/* Asegurar que los inputs tengan texto oscuro */
.modal-content input,
.modal-content select {
    color: #2D3748 !important;
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
                    <a href="gestion_asistentes.php">👥 Gestionar Asistentes</a>
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
                <span>🏆</span> Ver Torneos Pádel
            </button>

            <button class="action-btn-sidebar" onclick="window.location.href='crear_torneo.php'">
                <span>➕</span> Crear Torneo
            </button>
        </div>
    </div>

    <!-- COLUMNA 2: PLANILLA (Centro) -->
    <div class="planilla-column">
        <div class="planilla-header-controls">
            <button class="date-nav-btn" onclick="cambiarDiaPlanilla(-1)">&lt;</button>
            <input type="date" id="fechaPlanillaInput" class="control-select" style="width: 135px;">
            <button class="date-nav-btn" onclick="cambiarDiaPlanilla(1)">&gt;</button>
            <button class="date-nav-btn" onclick="irAHoyPlanilla()" style="width:auto; padding:0 12px; border-radius:20px; font-size:0.8rem; height:32px;">Hoy</button>
            
            <select class="control-select" id="filtroDeporte" onchange="cargarPlanillaReservas()">
                <option value="todos">Todos</option>
                <option value="padel">Pádel</option>
                <option value="futbol">Fútbol</option>
                <option value="tenis">Tenis</option>
            </select>

            <select class="control-select" id="filtroEstado" onchange="cargarPlanillaReservas()">
                <option value="">Estado...</option>
                <option value="disponible">Disponible</option>
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
                <option value="parcial">Pago Parcial</option>
            </select>
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
        <!-- KPI: Ingreso Mes -->
        <div class="kpi-card-mini kpi-ingreso">
            <div>Ingreso Mes</div>
            <div style="color: #0e3b02; font-weight: bold;">$<?= number_format($ingresos_act, 0, ',', '.') ?></div>
            <div style="color: <?= $var_ing >= 0 ? '#2E7D32' : '#C62828' ?>; font-size: 0.8rem;">
                <?= $var_ing >= 0 ? '▲' : '▼' ?> <?= abs($var_ing) ?>%
            </div>
        </div>
        <?php endif; ?>

        <!-- KPI: Saldo Pendiente (pagos parciales) -->
        <div class="kpi-card-mini kpi-parcial" onclick="abrirListaKPI('parcial')">
            <div>Saldo Pendiente</div>
            <div style="color: #e2b619; font-weight: bold;">$<?= number_format($monto_pendiente, 0, ',', '.') ?></div>
            <div style="color: #4A4A4A; font-size: 0.8rem;">Por cobrar (parciales)</div>
        </div>

        <?php if ($rol_actual === 'admin'): ?>
        <!-- KPI: En Reserva (futuras no pagadas) -->
        <div class="kpi-card-mini kpi-reserva">
            <div>En Reserva</div>
            <div>$<?= number_format($monto_en_reserva, 0, ',', '.') ?></div>
            <div style="color: #4A4A4A; font-size: 0.8rem;">
                <?= $cant_en_reserva ?> próximas
            </div>
        </div>
        <?php endif; ?>

        <!-- KPI: Deuda Vencida -->
        <div class="kpi-card-mini kpi-deuda" onclick="abrirListaKPI('deuda')">
            <div>Deuda Vencida</div>
            <div>$<?= number_format($monto_deuda, 0, ',', '.') ?></div>
            <div style="color: #C62828; font-size: 0.8rem;">Por regularizar</div>
        </div>
    </div>

   <!-- PANEL TORNEOS (Ahora es Modal Overlay) -->
    <div id="panelTorneos" class="torneos-modal-overlay">
        <div class="torneos-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; padding-bottom:0.8rem; border-bottom:1px solid #eee;">
                <h3 style="margin:0; color:#071289; font-size:1.4rem;">🏆 Torneos Activos</h3>
                <button onclick="cerrarModalTorneos()" style="background:#f0f0f0; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer; color:#666; font-size:1.2rem; display:flex; align-items:center; justify-content:center; transition:0.2s;">&times;</button>
            </div>
            <div id="listaTorneos" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:1rem;">
                <!-- Contenido inyectado por JS -->
            </div>
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

// === CARGA DE PLANILLA (con validación de respuesta) ===
async function cargarPlanillaReservas() {
    const deporte = document.getElementById('filtroDeporte')?.value || "todos";
    console.log(`📡 Cargando... Fecha: ${fechaPlanillaActual}, Deporte: ${deporte}`);
    
    try {
        const url = `../api/canchaboard.php?action=get_planilla_reservas&fecha=${fechaPlanillaActual}&deporte=${encodeURIComponent(deporte)}`;
        const response = await fetch(url, { credentials: 'include' });
        
        // Verificar estado HTTP primero
        if (response.status === 401) {
            showToast("Sesión expirada. Redirigiendo...", "warning");
            setTimeout(() => window.location.href = 'login_recintos.php', 2000);
            return;
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // === VALIDAR QUE LA RESPUESTA ES JSON ANTES DE PARSEAR ===
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // Si no es JSON, leer como texto para debug
            const text = await response.text();
            console.error('❌ Respuesta no es JSON:', text.substring(0, 200));
            throw new Error('El servidor devolvió HTML en lugar de JSON. Revisa logs.');
        }
        
        const data = await response.json();
        
        if (data.error) throw new Error(data.error);
        
        renderizarPlanilla(data, estadoSeleccionadoPlanilla);
        
    } catch (error) {
        console.error('❌ Error en cargarPlanillaReservas:', error);
        showToast(`Error: ${error.message}`, 'error');
        document.getElementById('tablaPlanilla').innerHTML = 
            `<tr><td colspan="100%" style="padding:2rem; color:#c62828; text-align:center;">
                ⚠️ ${error.message}<br>
                <small style="color:#666;">Revisa consola (F12) para más detalles</small>
            </td></tr>`;
    }
}

function renderizarPlanilla(data, filtroEstado) {
    const table = document.getElementById('tablaPlanilla');
    if (!table) return;
    if (!data.canchas || !data.canchas.length) {
        table.innerHTML = '<tr><td style="padding:2rem; text-align:center;">No hay canchas operativas.</td></tr>';
        return;
    }

    console.log(`📊 [DEBUG] Renderizando: ${data.canchas.length} canchas, ${Object.keys(data.reservas || {}).length} reservas`);

    // === HEADER ===
    let html = `<thead><tr>`;
    html += `<th style="background:#AB47BC; left:0; z-index:20; width:60px; min-width:60px; max-width:60px;">Hora</th>`;
    
    window.currentCanchasData = data.canchas;
    
    data.canchas.forEach((c, index) => {
        const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
        html += `<th style="background:#AB47BC; width:110px; font-size:0.75rem;" 
                data-cancha-id="${c.id_cancha}">
                    <div style="white-space:normal; line-height:1.1;">${c.nombre_cancha}</div>
                </th>`;
    });
    html += `</tr></thead><tbody>`;

    // === CUERPO: Slots de 30 minutos ===
    const ahora = new Date();
    let skipCells = {};
    let celdasPintadas = 0;

    data.slots.forEach(slot => {
        if (slot.is_label_row) {
            html += `<tr>`;
            html += `<td style="background:rgba(255,255,255,0.9); font-weight:bold; position:sticky; left:0; z-index:1; width:60px; font-size:0.75rem; text-align:center; border-right:1px solid #eee;">${slot.label}</td>`;

            data.canchas.forEach((cancha, idxCancha) => {
                // Saltar si está cubierta por rowspan anterior
                if (skipCells[idxCancha] && skipCells[idxCancha] > 0) {
                    skipCells[idxCancha]--;
                    return;
                }

                // 🔑 CLAVE: Normalizar slot.label para match exacto con BD
                const slotLabelNormalized = slot.label.substring(0, 5); // "21:00:00" → "21:00"
                const key = `${cancha.id_cancha}_${slotLabelNormalized}`;
                const res = data.reservas[key];

                if (res) {
                    // === CELDA RESERVADA ===
                    let bgClass = 'estado-pendiente';
                    if (res.estado_pago === 'pagado') bgClass = 'estado-pagado';
                    else if (res.estado_pago === 'parcial') bgClass = 'estado-parcial';

                    // Calcular duración y rowspan
                    const hIni = parseInt(res.hora_inicio.substring(0,2)) * 60 + parseInt(res.hora_inicio.substring(3,5));
                    const hFin = parseInt(res.hora_fin.substring(0,2)) * 60 + parseInt(res.hora_fin.substring(3,5));
                    const duracionMin = hFin - hIni;
                    const rowspan = Math.max(1, Math.ceil(duracionMin / 30));

                    console.log(`✅ MATCH: Key="${key}" | ${res.hora_inicio}-${res.hora_fin} | Rowspan: ${rowspan}`);

                    if (rowspan > 1) skipCells[idxCancha] = rowspan - 1;

                    const nombre = (res.nombre_cliente || res.nombre_socio || 'Reserva').substring(0, 15);
                    
                    // ✅ IMPORTANTE: rowspan="${rowspan}" como atributo HTML
                  html += `<td class="${bgClass} cell-reserva" 
                                rowspan="${rowspan}" 
                                draggable="true" 
                                ondragstart="dragStart(event, ${parseInt(res.id_reserva)})" 
                                ondragend="dragEnd(event)"
                                style="height:${rowspan * 40}px; vertical-align:middle; cursor:grab;" 
                                onclick="abrirDetalleDesdePlanilla(${parseInt(res.id_reserva)})">
                                <div style="font-size:0.7rem; font-weight:bold; line-height:1.2;">${nombre}</div>
                                <div style="font-size:0.6rem; opacity:0.9;">${res.hora_inicio.substring(0,5)}-${res.hora_fin.substring(0,5)}</div>
                            </td>`;
                    
                    celdasPintadas++;
                    
                } else {
                    // === CELDA DISPONIBLE ===
                    const slotFecha = new Date(`${fechaPlanillaActual}T${slot.label}:00`);
                    const esPasado = slotFecha <= ahora;
                    
                    if (esPasado) {
                        html += `<td class="estado-disponible" 
                                    data-cancha-id="${cancha.id_cancha}"
                                    style="opacity:0.3; cursor:not-allowed;"></td>`;
                    } else {
                        html += `<td class="estado-disponible drop-zone" 
                                    data-cancha-id="${cancha.id_cancha}"
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
    
    // Debug opcional: verificar rowspan aplicado
    setTimeout(() => {
        const celdas = document.querySelectorAll('td.cell-reserva[rowspan]');
        console.log(`🔍 [DEBUG] Celdas con rowspan: ${celdas.length}`);
        celdas.forEach((td, i) => {
            console.log(`   [${i}] rowspan="${td.getAttribute('rowspan')}" | offsetHeight=${td.offsetHeight}px`);
        });
    }, 100);
    
    console.log(`📈 [DEBUG] Resumen: ${celdasPintadas} reservas pintadas`);
}

// === 🎯 DRAG & DROP - VERSIÓN ROBUSTA ===
let draggedReservaId = null;
let draggedElement = null;

function dragStart(e, id) {
    draggedReservaId = id;
    draggedElement = e.target;
    
    // Configurar dataTransfer ANTES de cualquier otra cosa
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', id.toString());
    
    // Aplicar clase visual
    e.target.classList.add('dragging');
    
    // Forzar reflow para aplicar estilos inmediatamente
    void e.target.offsetWidth;
    
    console.log(`🎯 Drag START: ID=${id}`);
}

function dragEnd(e) {
    console.log(`🎯 Drag END`);
    if (draggedElement) draggedElement.classList.remove('dragging');
    limpiarHighlights();
    draggedReservaId = null;
    draggedElement = null;
}

// Listener GLOBAL para dragover (CRÍTICO para permitir drop)
document.addEventListener('dragover', (e) => {
    e.preventDefault(); // Permite el drop
    e.dataTransfer.dropEffect = 'move';
    
    const td = e.target.closest('td.estado-disponible');
    if (td) {
        limpiarHighlights();
        td.classList.add('drop-target');
        highlightCoordinates(td);
    }
}, { passive: false });

document.addEventListener('dragenter', (e) => {
    e.preventDefault();
}, { passive: false });

function highlightCoordinates(td) {
    const row = td.closest('tr');
    if (!row) return;
    const colIndex = Array.from(row.children).indexOf(td);
    
    // Resaltar Hora
    const timeCell = row.querySelector('td:first-child');
    if (timeCell) timeCell.classList.add('coord-highlight');
    
    // Resaltar Cancha (header)
    const headerRow = document.querySelector('#tablaPlanilla thead tr');
    if (headerRow && headerRow.children[colIndex]) {
        headerRow.children[colIndex].classList.add('coord-highlight');
    }
}

function limpiarHighlights() {
    document.querySelectorAll('.drop-target, .coord-highlight').forEach(el => {
        el.classList.remove('drop-target', 'coord-highlight');
    });
}

function dragOver(e) {
    e.preventDefault(); // CRÍTICO: permite el drop
    const td = e.target.closest('td.estado-disponible');
    
    if (td && draggedReservaId) {
        console.log(`🎯 Drag OVER: celda disponible`, td);
        limpiarHighlights();
        td.classList.add('drop-target');
        highlightCoordinates(td);
    }
}

async function dropReserva(e, canchaId, hora) {
    e.preventDefault();
    console.log(`🎯 Drop: canchaId=${canchaId}, hora=${hora}, draggedId=${draggedReservaId}`);
    e.stopPropagation(); // 🔑 EVITA QUE EL EVENTO BUBBLEE A OTROS LISTENERS
     // Validación adicional por seguridad
    if (!draggedReservaId || !canchaId || !hora) {
        console.warn('⚠️ Datos incompletos para mover reserva');
        return;
    }

    
    const targetCell = e.target.closest('td');
    if (targetCell) {
        targetCell.classList.add('drop-anim');
        setTimeout(() => targetCell.classList.remove('drop-anim'), 300);
    }
    
    limpiarHighlights();
    if (!draggedReservaId) {
        console.warn('⚠️ No hay reserva arrastrada');
        return;
    }

    if (confirm(`📅 ¿Mover reserva ID ${draggedReservaId} a las ${hora} en Cancha ${canchaId}?`)) {
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
            showToast(data.success ? '✅ Reserva movida' : '❌ ' + data.message, 
                     data.success ? 'success' : 'error');
            if (data.success) cargarPlanillaReservas();
        } catch (err) {
            console.error('❌ Error en drop:', err);
            showToast('❌ Error de conexión', 'error');
        }
    }
    draggedReservaId = null;
}

document.addEventListener('dragend', (e) => {
    e.target.classList.remove('dragging');
    limpiarHighlights();
    draggedReservaId = null;
});


// === DETALLE DE RESERVA (CORREGIDO) ===
async function abrirDetalleDesdePlanilla(idReserva) {
     console.log("🔍 abrirDetalleDesdePlanilla llamado con ID:", idReserva, typeof idReserva);
    
    if (!idReserva || idReserva === 'undefined' || idReserva === 'null') {
        showToast("❌ ID de reserva inválido", "error");
        console.error("❌ ID inválido recibido:", idReserva);
        return;
    }
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
            else if (detalle.estado_pago === 'parcial') estadoColor = '#f4e346';

            // Construcción del HTML principal
            let html = `
                <!-- === MENÚ DE 3 PUNTOS (SOLO ADMIN) === -->
                <?php if (($_SESSION['recinto_rol'] ?? '') === 'admin'): ?>
                <div style="position:absolute; top:8px; right:8px; z-index:5;">
                    <div style="position:relative; display:inline-block;">
                        <button onclick="toggleLogMenu(event, <?= $reserva['id_reserva'] ?>)" 
                                style="background:rgba(255,255,255,0.9); border:none; border-radius:6px; 
                                    width:28px; height:28px; cursor:pointer; font-size:1.1rem; 
                                    display:grid; place-items:center; color:#666; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                            ⋮
                        </button>
                        <div id="logMenu_<?= $reserva['id_reserva'] ?>" 
                            style="display:none; position:absolute; top:100%; right:0; 
                                    background:white; border-radius:10px; min-width:180px; 
                                    box-shadow:0 8px 25px rgba(0,0,0,0.15); z-index:10; overflow:hidden; border:1px solid #eee;">
                            <div onclick="abrirLogReserva(<?= $reserva['id_reserva'] ?>)" 
                                style="padding:10px 14px; cursor:pointer; font-size:0.9rem; color:#333; 
                                        display:flex; align-items:center; gap:8px; border-bottom:1px solid #f5f5f5;">
                                📋 Ver bitácora
                            </div>
                            <!-- Aquí puedes agregar más acciones admin en el futuro -->
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
                        <!-- === CREADO POR (visible para todos) === -->
                        <div style="font-size:0.75rem; color:#888; margin-top:4px; display:flex; align-items:center; gap:4px;">
                            <span>👤</span>
                            <span id="creado_por_<?= $reserva['id_reserva'] ?>">
                                <?= htmlspecialchars($reserva['usuario_creacion'] ?? 'Sistema') ?>
                            </span>
                        </div>
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

// === TOAST NOTIFICATIONS ===
function showToast(message, type = 'success') {
    // Remover toast anterior si existe
    const existing = document.getElementById('toastNotification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'toastNotification';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
        background: ${type === 'error' ? '#C62828' : '#2E7D32'}; color: white;
        padding: 0.85rem 1.5rem; border-radius: 14px; font-size: 0.9rem; font-weight: 500;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2); z-index: 3000; max-width: 90%; text-align: center;
        opacity: 0; transition: all 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    // Animar entrada
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
    });
    
    // Auto-ocultar
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Menú Admin
function toggleMenu(e) { e.stopPropagation(); document.getElementById('adminMenu').style.display = 'block'; }
function closeMenu() { document.getElementById('adminMenu').style.display = 'none'; }
document.addEventListener('click', () => { if(document.getElementById('adminMenu').style.display === 'block') closeMenu(); });

// === 🏆 CARGAR TORNEOS + LÓGICA DE BOTONES CORREGIDA ===
async function cargarTorneos() {
    const container = document.getElementById('listaTorneos');
    if (!container) return;
    
    container.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem; color:#666;">🔄 Cargando torneos...</div>`;
    
    try {
        const res = await fetch(`../api/get_torneos_recinto.php`, { credentials: 'include' });
        if (!res.ok) throw new Error(`Error ${res.status}`);
        
        const torneos = await res.json();
        
        if (!Array.isArray(torneos) || torneos.length === 0) {
            container.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#888;">
                <div style="font-size:3rem; margin-bottom:0.5rem;">📋</div>
                <p>No hay torneos activos</p></div>`;
            return;
        }
        
        let html = '';
        torneos.forEach(t => {
            // 1. Definir variables básicas
            const estado = (t.estado || '').toLowerCase();
            const fechaInicio = t.fecha_inicio ? new Date(t.fecha_inicio).toLocaleDateString('es-CL', {day:'2-digit', month:'short'}) : '-';
            const inscritosCount = parseInt(t.parejas_inscritas) || 0;
            const maxParejas = parseInt(t.num_parejas_max) || 0;
            const progreso = maxParejas > 0 ? Math.min(100, (inscritosCount / maxParejas) * 100) : 0;
            const icono = {padel:'🎾',tenis:'🎾',futbol:'⚽',futsal:'⚽'}[t.deporte?.toLowerCase()] || '🏆';
            
            // 2. DEFINIR estadoLabel y Color (Aquí estaba el error)
            const estadoMap = {
                'abierto': 'ABIERTO',
                'cerrado': 'CERRADO',
                'en_progreso': 'EN CURSO',
                'finalizado': 'FINALIZADO'
            };
            const estadoLabel = estadoMap[estado] || estado.toUpperCase();
            
            const estadoColor = estado === 'abierto' ? '#4CAF50' : 
                               (estado === 'en_progreso' ? '#2196F3' : '#FF9800');

            // 3. Lógica de Botones (Según tu requerimiento)
            let botonesHtml = '';
            
            if (estado === 'abierto') {
                // ABIERTO: Invitar, Crear Fixture
                botonesHtml = `
                    <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                        <a href="torneo_invite.php?id=${t.id_torneo}" class="btn-torneo btn-invitar" style="text-align:center; text-decoration:none; padding:0.6rem; border-radius:6px; background:#E0F7FA; color:#006064; font-weight:600; font-size:0.9rem;">📩 Invitar Parejas</a>
                        <button class="btn-torneo" style="padding:0.6rem; border-radius:6px; background:#071289; color:white; border:none; font-weight:600; cursor:pointer;" onclick="cerrarTorneo(${t.id_torneo})">Cerrar Inscrip..</button>
                        <button class="btn-torneo" style="padding:0.6rem; border-radius:6px; background:#071289; color:white; border:none; font-weight:600; cursor:pointer;" onclick="generarFixture(${t.id_torneo})">⚙️ Crear Fixture</button>
                    </div>
                `;
            } else {
                // EN CURSO / CERRADO: Ver Fixture, Resultados, Finalizar
                botonesHtml = `
                    <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn-torneo" style="flex:1; padding:0.6rem; border-radius:6px; background:#f0f0f0; color:#333; border:none; font-weight:600; cursor:pointer;" onclick="verFixture(${t.id_torneo})">🎾 Ver Fixture</button>
                            <button class="btn-torneo" style="flex:1; padding:0.6rem; border-radius:6px; background:#071289; color:white; border:none; font-weight:600; cursor:pointer;" onclick="verResultadosTV(${t.id_torneo})">📺 TV Mode</button>
                        </div>
                        ${estado !== 'finalizado' ? `
                        <button class="btn-torneo" style="padding:0.6rem; border-radius:6px; background: #f64242; color:white; border:none; font-weight:600; cursor:pointer; margin-top:0.5rem;" onclick="finalizarTorneoYCalcularRanking(${t.id_torneo})">
                            ✅ Finalizar + Upgrade Ranking
                        </button>` : ''}
                    </div>
                `;
            }
            
            // 4. Construir HTML de la Tarjeta
            html += `
            <div style="background: white; border-radius: 12px; padding: 1.2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); position: relative; display: flex; flex-direction: column; gap: 0.8rem; border-left: 5px solid ${estadoColor}; animation: fadeIn 0.3s ease-out forwards;">
                
                <!-- Header: Estado y Menú 3 Puntos -->
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="background:${estadoColor}; color:white; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:bold;">
                        ${estadoLabel}
                    </span>
                    
                    <!-- Menú de 3 Puntos -->
                    <div style="position:relative;">
                        <button onclick="toggleMenuTorneo(${t.id_torneo}, event)" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#666; padding:0;">⋮</button>
                        <div id="menu-torneo-${t.id_torneo}" style="display:none; position:absolute; right:0; top:100%; background:white; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:10; min-width:120px; border:1px solid #eee;">
                            <div onclick="editarTorneo(${t.id_torneo})" style="padding:0.6rem; cursor:pointer; border-bottom:1px solid #eee; font-size:0.9rem; color:#333;">✏️ Editar</div>
                            <div onclick="eliminarTorneo(${t.id_torneo})" style="padding:0.6rem; cursor:pointer; color:#c62828; font-size:0.9rem;">🗑️ Eliminar</div>
                        </div>
                    </div>
                </div>

                <!-- Título y Fecha -->
                <div>
                    <h4 style="margin:0; color:#071289; font-size:1.1rem; font-weight:700;">${icono} ${t.nombre || 'Sin nombre'}</h4>
                    <p style="margin:0.2rem 0 0 0; font-size:0.85rem; color:#666;">📅 Inicio: ${fechaInicio}</p>
                </div>

                <!-- Inscritos y Ojo -->
                <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.9rem; color:#555;">
                    <span>👥 ${inscritosCount}/${maxParejas || '∞'} parejas</span>
                    <button onclick="abrirModalInscritos(${t.id_torneo})" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:#071289;" title="Ver inscritos">👁️</button>
                </div>

                <!-- Barra de Progreso -->
                ${maxParejas > 0 ? `<div style="background:#e0e0e0; border-radius:10px; height:6px; overflow:hidden;"><div style="background:${estadoColor}; height:100%; width:${progreso}%; border-radius:10px;"></div></div>` : ''}

                <!-- Botones de Acción -->
                <div style="margin-top:auto; padding-top:0.8rem; border-top:1px solid #eee;">
                    ${botonesHtml}
                </div>
            </div>`;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error(error);
        container.innerHTML = `<div style="grid-column:1/-1; text-align:center; color:#c62828; padding:2rem;">⚠️ Error: ${error.message}<br><button onclick="cargarTorneos()" style="margin-top:0.5rem; padding:0.4rem 1rem; background:#071289; color:white; border:none; border-radius:6px; cursor:pointer;">Reintentar</button></div>`;
    }
}

// === FUNCIONES AUXILIARES PARA EL MENÚ ===
function toggleMenuTorneo(id, event) {
    event.stopPropagation();
    document.querySelectorAll('[id^="menu-torneo-"]').forEach(m => m.style.display = 'none');
    const menu = document.getElementById(`menu-torneo-${id}`);
    if (menu) menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('click', () => {
    document.querySelectorAll('[id^="menu-torneo-"]').forEach(m => m.style.display = 'none');
});

function editarTorneo(id) {
    window.location.href = `crear_torneo.php?editar=${id}`;
}

function eliminarTorneo(id) {
    if(confirm('¿Estás seguro de eliminar este torneo? Esta acción no se puede deshacer.')) {
        fetch('../api/eliminar_torneo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id_torneo: id})
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    }
}

// === TOGGLE MODAL CON ANIMACIÓN ===
document.getElementById('btnTorneosActivos')?.addEventListener('click', () => {
    const panel = document.getElementById('panelTorneos');
    if (!panel) return;
    
    // Abrir
    panel.style.display = 'flex';
    // Forzar reflow para activar transición CSS
    void panel.offsetWidth;
    panel.classList.add('active');
    
    // Cargar datos solo la primera vez
    if (!panel.dataset.loaded) {
        cargarTorneos();
        panel.dataset.loaded = 'true';
    }
});

function cerrarModalTorneos() {
    const panel = document.getElementById('panelTorneos');
    if (panel) {
        panel.classList.remove('active');
        setTimeout(() => { panel.style.display = 'none'; }, 400); // Esperar animación
    }
}

// Cerrar al hacer click fuera de la tarjeta
document.getElementById('panelTorneos')?.addEventListener('click', (e) => {
    if (e.target.id === 'panelTorneos') cerrarModalTorneos();
});

// === ACTUALIZAR EL SUBMIT DEL FORMULARIO ===
document.getElementById('formReservaManual')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const isRecurrent = document.getElementById('isRecurrent')?.checked;
    
    if (isRecurrent) {
        // === FLUJO RECURRENTE ===
        await handleRecurrentReservation();
    } else {
        // === FLUJO NORMAL (existente) ===
        await handleSingleReservation();
    }
});

async function handleSingleReservation() {
    // Tu lógica existente para reserva única...
    // (mantener el código actual)
}

async function handleRecurrentReservation() {
    const btn = document.querySelector('#formReservaManual button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '🔄 Generando reservas...';
    
    try {
        const payload = {
            action: 'create_recurrent',
            id_cancha: document.getElementById('modalCanchaId')?.value,
            hora_inicio: document.getElementById('modalHoraInicio')?.value,
            hora_fin: document.getElementById('modalHoraFin')?.value,
            id_socio: document.getElementById('modalSocioId')?.value,
            repeat_day: parseInt(document.getElementById('repeatDay')?.value),
            start_date: document.getElementById('startDate')?.value,
            end_date: document.getElementById('endDate')?.value,
            monto_total: document.getElementById('modalMonto')?.value,
            jugadores_esperados: document.getElementById('modalJugadores')?.value
        };
        
        const response = await fetch('../api/reserva_recurrente.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`✅ ${result.created} reservas creadas` + 
                     (result.skipped > 0 ? ` | ⚠️ ${result.skipped} saltadas por conflicto` : ''));
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(`❌ ${result.message}`, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('❌ Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// === ABRIR MODAL CON DATOS ===
function abrirReservaAdmin(canchaId, fecha, hora) {
    // Verificar elementos críticos
    const elCancha = document.getElementById('admin_cancha_id');
    const elFecha = document.getElementById('admin_fecha');
    const elHora = document.getElementById('admin_hora_inicio');
    const elModal = document.getElementById('modalReservaAdmin');
    
    if (!elCancha || !elFecha || !elHora || !elModal) {
        console.error('❌ Faltan campos del modal');
        showToast('⚠️ Error al abrir formulario', 'error');
        return;
    }

    // Buscar cancha con comparación flexible (string/number)
    let cancha = null;
    if (typeof canchasData !== 'undefined' && Array.isArray(canchasData)) {
        cancha = canchasData.find(c => 
            String(c.id_cancha) === String(canchaId) || 
            c.id_cancha == canchaId  // Doble-check con == para tipos mixtos
        );
    }

    // Si se encontró, poblar datos visuales
    if (cancha) {
        const nombreCancha = cancha.nombre_cancha?.trim() || cancha.nro_cancha || `Cancha ${cancha.id_cancha}`;
        document.getElementById('modalCanchaDisplay').textContent = `🏟️ ${nombreCancha}`;
        
        // Guardar monto base y actualizar display
        const montoBase = parseFloat(cancha.valor_arriendo) || 0;
        document.getElementById('admin_monto_base').value = montoBase;
        
        // Calcular monto inicial según duración seleccionada
        const duracion = document.querySelector('input[name="duracion"]:checked')?.value || '60';
        actualizarMontoDisplay(montoBase, parseInt(duracion));
    } else {
        // Fallback si no se encuentra la cancha (pero no bloquear)
        console.warn('⚠️ Cancha no encontrada en canchasData:', canchaId);
        document.getElementById('modalCanchaDisplay').textContent = `🏟️ Cancha #${canchaId}`;
        document.getElementById('admin_monto_base').value = 0;
        document.getElementById('modalMontoDisplay').textContent = '$0';
    }
    
    // Asignar valores base
    elCancha.value = canchaId;
    elFecha.value = fecha;
    elHora.value = hora;
    
    // Calcular hora fin inicial (60 min)
    actualizarHoraFin(hora, 60);
    
    // Actualizar vista previa visual
    const fechaParts = fecha.split('-');
    document.getElementById('modalFechaDisplay').textContent = `${fechaParts[2]}/${fechaParts[1]}`;
    document.getElementById('modalHoraDisplay').textContent = `${hora} - ${document.getElementById('admin_hora_fin').value}`;
    
    // Resetear buscador y sección recurrente
    document.getElementById('searchAdmin').value = '';
    document.getElementById('searchResultsAdmin').style.display = 'none';
    document.getElementById('admin_socio_id').value = '';
    
    document.getElementById('isRecurrent').checked = false;
    toggleRecurrentFields(false);
    
    // Resetear duración a 60 min
    document.querySelector('input[name="duracion"][value="60"]').checked = true;
    
    // Mostrar modal con animación
    elModal.style.display = 'flex';
    setTimeout(() => document.getElementById('searchAdmin')?.focus(), 100);
    
    // Prevenir scroll del body
    document.body.style.overflow = 'hidden';
}

// === ACTUALIZAR HORA FIN SEGÚN DURACIÓN ===
function actualizarHoraFin(horaInicio, duracionMin) {
    const [h, m] = horaInicio.split(':').map(Number);
    const fin = new Date();
    fin.setHours(h, m + duracionMin, 0, 0);
    const horaFin = `${String(fin.getHours()).padStart(2,'0')}:${String(fin.getMinutes()).padStart(2,'0')}`;
    
    document.getElementById('admin_hora_fin').value = horaFin;
    
    // Actualizar display visual
    const fecha = document.getElementById('admin_fecha').value;
    const fechaParts = fecha.split('-');
    document.getElementById('modalFechaDisplay').textContent = `${fechaParts[2]}/${fechaParts[1]}`;
    document.getElementById('modalHoraDisplay').textContent = `${horaInicio} - ${horaFin}`;
    
    // Recalcular monto si hay valor base
    const montoBase = parseFloat(document.getElementById('admin_monto_base').value) || 0;
    actualizarMontoDisplay(montoBase, duracionMin);
}

// === ACTUALIZAR MONTO SEGÚN DURACIÓN (función global) ===
function actualizarMontoDisplay(montoBase, duracionMin) {
    // Factor de precio: 90 min = 1.5x el valor de 60 min (ajusta según tu regla de negocio)
    const factor = duracionMin === 90 ? 1.5 : 1;
    const total = Math.round(montoBase * factor);
    
    const elMonto = document.getElementById('modalMontoDisplay');
    if (elMonto) {
        elMonto.textContent = `$${total.toLocaleString('es-CL')}`;
        // Efecto visual de actualización
        elMonto.style.transition = 'transform 0.2s';
        elMonto.style.transform = 'scale(1.05)';
        setTimeout(() => elMonto.style.transform = 'scale(1)', 200);
    }
}

// === CAMBIO DE DURACIÓN (60/90 min) ===
function actualizarDuracionReserva(duracion) {
    const horaInicio = document.getElementById('admin_hora_inicio').value;
    actualizarHoraFin(horaInicio, parseInt(duracion));
    document.getElementById('admin_duracion').value = duracion;
}

// === TOGGLE SECCIÓN RECURRENTE (FIX: usar onchange en HTML + función global) ===
function toggleRecurrentFields(mostrar) {
    const fields = document.getElementById('recurrentFields');
    if (fields) {
        fields.style.display = mostrar ? 'block' : 'none';
        if (mostrar) updatePreviewDates();
    }
}

// === BUSCADOR INTELIGENTE (TU CÓDIGO INTEGRADO) ===
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
        const text = await res.text();
        
        let data;
        try { data = JSON.parse(text); } catch (e) {
            console.error('❌ API search_socios devolvió JSON inválido:', text.substring(0, 150));
            container.innerHTML = '<div style="padding:8px; color:#d32f2f; font-size:0.85rem;">Error en búsqueda.</div>';
            container.style.display = 'block';
            return;
        }

        container.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
            container.innerHTML = '<div style="padding:10px; color:#666; font-size:0.85rem;">Sin coincidencias.</div>';
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

// === VISTA PREVIA DE FECHAS RECURRENTES ===
function updatePreviewDates() {
    const day = parseInt(document.getElementById('repeatDay')?.value);
    const start = document.getElementById('startDate')?.value;
    const end = document.getElementById('endDate')?.value;
    const preview = document.getElementById('previewDates');
    
    if (!day || !start || !end || isNaN(day)) {
        if (preview) preview.textContent = 'Selecciona fechas para ver las fechas generadas';
        return;
    }
    
    const dates = generateRecurringDates(start, end, day);
    const dayNames = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    
    if (!preview) return;
    
    if (dates.length === 0) {
        preview.textContent = '⚠️ No hay fechas válidas en este rango';
        preview.style.color = '#C62828';
    } else {
        preview.textContent = `📅 ${dates.length} fechas: ` + dates.slice(0, 3).map(d => {
            const dateObj = new Date(d + 'T00:00:00');
            return `${dayNames[dateObj.getDay()]} ${d.split('-')[2]}/${d.split('-')[1]}`;
        }).join(', ') + (dates.length > 3 ? '...' : '');
        preview.style.color = '#2E7D32';
    }
}

function generateRecurringDates(startDate, endDate, dayOfWeek) {
    const dates = [];
    let current = new Date(startDate + 'T00:00:00');
    const end = new Date(endDate + 'T00:00:00');
    
    while (current <= end) {
        if (current.getDay() === dayOfWeek) {
            dates.push(current.toISOString().split('T')[0]);
        }
        current.setDate(current.getDate() + 1);
    }
    return dates;
}

// === CERRAR MODAL ===
function cerrarModalReservaAdmin(e) {
    if (e.target.id === 'modalReservaAdmin' || e.target.closest('.modal-content button')) {
        const modal = document.getElementById('modalReservaAdmin');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 250);
        }
    }
    e.stopPropagation();
}

// === EVENTOS GLOBALES (DOMContentLoaded) ===
document.addEventListener('DOMContentLoaded', function() {
    // Prevenir cerrar modal al hacer click dentro del contenido
    const modalContent = document.querySelector('#modalReservaAdmin .modal-content');
    modalContent?.addEventListener('click', function(e) { e.stopPropagation(); });
    
    // Soporte tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('modalReservaAdmin');
            if (modal && modal.style.display !== 'none') {
                cerrarModalReservaAdmin({ target: modal });
            }
        }
    });
    
    // Actualizar preview de fechas recurrentes al cambiar inputs
    ['repeatDay', 'startDate', 'endDate'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', updatePreviewDates);
    });
    
    // Cerrar resultados de búsqueda al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#searchAdmin') && !e.target.closest('#searchResultsAdmin')) {
            const results = document.getElementById('searchResultsAdmin');
            if (results) results.style.display = 'none';
        }
    });
});

// === SUBMIT DEL FORMULARIO (con soporte recurrente) ===
// === SUBMIT DEL FORMULARIO (versión corregida - sin variables huérfanas) ===
async function guardarReservaAdmin(e) {
    e.preventDefault();
    
    // === 1. LEER VARIABLES DEL DOM (todas definidas localmente) ===
    const id_cancha = document.getElementById('admin_cancha_id')?.value;
    const fecha = document.getElementById('admin_fecha')?.value;
    const hora_inicio = document.getElementById('admin_hora_inicio')?.value;  // ← snake_case consistente
    const hora_fin = document.getElementById('admin_hora_fin')?.value;
    const id_socio = document.getElementById('admin_socio_id')?.value;
    const monto_total = parseFloat(document.getElementById('admin_monto_total')?.value) || 0;
    const duracion = parseInt(document.getElementById('admin_duracion_bloque')?.value) || 60;
    
    // Validación básica
    if (!id_cancha || !fecha || !hora_inicio) {
        showToast('⚠️ Faltan datos de la reserva', 'error');
        return;
    }
    
    const isRecurrent = document.getElementById('isRecurrent')?.checked;
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    
    btn.disabled = true;
    btn.textContent = isRecurrent ? '🔄 Generando...' : '💾 Guardando...';
    
    try {
        if (isRecurrent) {
            // === 2. FLUJO RECURRENTE ===
            const repeat_day = parseInt(document.getElementById('repeatDay')?.value);
            const start_date = document.getElementById('startDate')?.value;
            const end_date = document.getElementById('endDate')?.value;
            
            // Validación de fechas recurrentes
            if (!repeat_day || !start_date || !end_date) {
                showToast('⚠️ Completa día de repetición y fechas', 'error');
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }
            if (new Date(start_date) > new Date(end_date)) {
                showToast('⚠️ Fecha inicio debe ser anterior a fecha fin', 'error');
                btn.disabled = false;
                btn.textContent = originalText;
                return;
            }
            
            // Payload para API
            const payload = {
                action: 'create_recurrent',
                id_cancha: id_cancha,
                hora_inicio: hora_inicio,  // ← Variable definida arriba
                hora_fin: hora_fin,
                id_socio: id_socio,
                repeat_day: repeat_day,
                start_date: start_date,
                end_date: end_date,
                monto_total: monto_total,
                duracion_bloque: duracion
            };
            
            const response = await fetch('../api/reserva_recurrente.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            
            if (result.success) {
                showToast(`✅ ${result.created} reservas creadas` + 
                         (result.skipped > 0 ? ` | ⚠️ ${result.skipped} saltadas` : ''));
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(`❌ ${result.message}`, 'error');
                btn.disabled = false;
                btn.textContent = originalText;
            }
            
        } else {
            // === 3. FLUJO RESERVA ÚNICA ===
            const formData = new FormData();
            formData.append('action', 'crear_manual');
            formData.append('id_cancha', id_cancha);
            formData.append('fecha', fecha);
            formData.append('hora_inicio', hora_inicio);  // ← Variable definida arriba
            formData.append('hora_fin', hora_fin);
            formData.append('id_socio', id_socio);
            formData.append('monto_total', monto_total);
            formData.append('duracion_bloque', duracion);
            
            const response = await fetch('../api/gestion_reservas.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            console.log('🔍 [DEBUG] Variables leídas del DOM:');
            console.log('  id_cancha:', id_cancha);
            console.log('  fecha:', fecha);
            console.log('  hora_inicio:', hora_inicio);  // ← Debería mostrar "19:00" o similar
            console.log('  monto_total:', monto_total);
            
            if (result.success) {
                // ✅ Registrar log de creación (bitácora)
                const idReservaCreada = result.id_reserva;
                if (idReservaCreada) {
                    registrarLogReserva(
                        idReservaCreada,
                        'creada',
                        `Reserva manual creada para ${hora_inicio} - ${hora_fin}`,
                        { cancha_id: id_cancha, socio_id: id_socio },
                        { nuevo: monto_total }
                    );
                }
                
                showToast('✅ Reserva creada correctamente');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(`❌ ${result.message}`, 'error');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
    } catch (error) {
        console.error('❌ Error en guardarReservaAdmin:', error);
        showToast('❌ Error de conexión: ' + error.message, 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
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
// Detectar scroll para sombra del header
document.querySelector('.planilla-table-container')?.addEventListener('scroll', function(e) {
    const header = document.querySelector('.planilla-header-controls');
    if (e.target.scrollTop > 10) header.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
    else header.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
});

// === VOLVER A TORNEOS ACTIVOS ===
function volverATorneosActivos() {
    console.log('🔙 Volviendo a Torneos Activos...');
    cerrarSubmodal();
    
    // Asegurar que el panel principal de torneos esté visible
    const panel = document.getElementById('panelTorneos');
    if (panel) {
        panel.style.display = 'flex';
        void panel.offsetWidth;
        panel.classList.add('active');
    }
}

// === EVENTO CLICK EN OVERLAY (Cerrar al hacer click fuera) ===
document.addEventListener('click', function(e) {
    const submodal = document.getElementById('submodalGenerico');
    if (submodal && 
        submodal.style.display !== 'none' && 
        e.target === submodal) {
        console.log('🖱️ Click en overlay, cerrando...');
        cerrarSubmodal();
    }
});

// === CERRAR CON TECLA ESCAPE ===
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const submodal = document.getElementById('submodalGenerico');
        if (submodal && submodal.style.display !== 'none') {
            console.log('⌨️ Tecla Escape, cerrando...');
            cerrarSubmodal();
        }
    }
});

// === BOTÓN X DENTRO DEL SUBMODAL ===
// Asegurar que funcione incluso si se re-renderiza el contenido
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('submodal-close') || e.target.closest('.submodal-close')) {
        console.log('❌ Click en botón X, cerrando...');
        cerrarSubmodal();
    }
});
// === 👁️ VER INSCRITOS Y BAJAR PAREJA ===
async function abrirModalInscritos(idTorneo) {
    const overlay = document.getElementById('submodalGenerico');
    const contenido = document.getElementById('submodalContenido');
    
    if(!overlay || !contenido) return;
    
    overlay.style.display = 'flex';
    void overlay.offsetWidth;
    overlay.classList.add('active');
    
    contenido.innerHTML = '<p style="text-align:center; padding:2rem;">🔄 Cargando inscritos...</p>';
    
    try {
        const res = await fetch(`../api/get_inscritos_torneos.php?id_torneo=${idTorneo}`);
        if (!res.ok) throw new Error(`Error ${res.status}`);
        
        const data = await res.json();
        
        // Validación estricta
        if (!Array.isArray(data)) {
            console.error('La API no devolvió un array:', data);
            throw new Error(data.error || 'Datos inválidos');
        }

        if(data.length === 0) {
            contenido.innerHTML = '<p style="text-align:center; padding:2rem; color:#888;">No hay parejas inscritas aún.</p>';
            return;
        }
        
        let html = `<h3 style="color:#071289; margin-bottom:1rem;">👥 Parejas Inscritas</h3>
                    <table class="tabla-inscritos" style="width:100%; border-collapse:collapse;">
                    <thead><tr style="background:#071289; color:white;"><th>Pareja</th><th>Jugador 1</th><th>Jugador 2</th><th>Contacto</th></tr></thead>
                    <tbody>`;
            
        data.forEach(p => {
            html += `<tr style="border-bottom:1px solid #eee;">
                <td style="padding:0.8rem; font-weight:600;">${p.nombre_pareja || '-'}</td>
                <td style="padding:0.8rem;">${p.jugador1}</td>
                <td style="padding:0.8rem;">${p.jugador2}</td>
                <td style="padding:0.8rem; font-size:0.85rem; color:#666;">${p.contacto}</td>
            </tr>`;
        });
        
        html += `</tbody></table>`;
        html += `<div style="margin-top:1rem; text-align:right;"><button class="action-btn" style="background:#6c757d;" onclick="cerrarSubmodal()">Cerrar</button></div>`;
        contenido.innerHTML = html;
        
    } catch(e) {
        console.error(e);
        contenido.innerHTML = `<div style="text-align:center; color:red; padding:2rem;">⚠️ Error: ${e.message}<br><button class="action-btn" onclick="abrirModalInscritos(${idTorneo})">Reintentar</button></div>`;
    }
}

async function bajarPareja(idPareja, nombre, idTorneo) {
    if(!confirm(`¿Estás seguro de BAJAR a la pareja "${nombre}"?\n\nSe les enviará un correo notificando su exclusión.`)) return;
    
    try {
        const formData = new FormData();
        formData.append('id_pareja', idPareja);
        
        const res = await fetch('../api/eliminar_pareja_torneo.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) {
            alert('✅ Pareja bajada y notificada.');
            abrirModalInscritos(idTorneo); // Recargar lista
            cargarTorneos(); // Actualizar contadores en tarjetas
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch(e) {
        alert('❌ Error de conexión');
    }
}

function cerrarSubmodalInscritos() {
    const overlay = document.getElementById('submodalInscritosOverlay');
    if(overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }
}

// === VARIABLES GLOBALES (Necesarias para navegación interna) ===
let contenidoFixtureAnterior = '';
window.torneoActualId = null;

// === ✏️ ABRIR RESULTADO (UX Mejorada) ===
function abrirResultado(idPartido, pareja1, pareja2) {
    // Definir explícitamente las variables aquí
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay.querySelector('.submodal-card');
    const contenido = document.getElementById('submodalContenido'); // Aquí estaba el fallo
    
    if (!contenido) return;

    // Activar modo compacto móvil
    if(card) card.classList.add('compact-modal');
    
    contenidoFixtureAnterior = contenido.innerHTML;

    fetch(`../api/get_resultado_partido.php?id_partido=${idPartido}`)
        .then(r => r.json())
        .then(resultado => {
            const j1 = resultado.juegos_pareja_1 || 0;
            const j2 = resultado.juegos_pareja_2 || 0;

            const html = `
                <div style="text-align:center;">
                    <h4 style="color:#071289; margin-bottom:1rem;">${pareja1} vs ${pareja2}</h4>
                    
                    <div style="display:flex; justify-content:center; gap:1rem; margin:1.5rem 0;">
                        <div>
                            <input type="number" id="juegos1" class="compact-input" value="${j1}" min="0" max="7">
                            <div style="font-size:0.7rem; color:#666; margin-top:0.3rem;">${pareja1.split(' & ')[0]}</div>
                        </div>
                        <div style="font-size:1.5rem; align-self:center; color:#ccc;">-</div>
                        <div>
                            <input type="number" id="juegos2" class="compact-input" value="${j2}" min="0" max="7">
                            <div style="font-size:0.7rem; color:#666; margin-top:0.3rem;">${pareja2.split(' & ')[0]}</div>
                        </div>
                    </div>
                    
                    <div id="ganadora" style="font-weight:bold; color:#4CAF50; margin-bottom:1rem;"></div>
                    
                    <button class="compact-btn-save" onclick="guardarResultado(${idPartido}, '${pareja1.replace(/'/g, "\\'")}', '${pareja2.replace(/'/g, "\\'")}')">💾 Guardar</button>
                    <button style="background:none; border:none; color:#666; margin-top:0.5rem; cursor:pointer;" onclick="volverAFixture()">Cancelar</button>
                </div>
            `;
            contenido.innerHTML = html;

            document.getElementById('juegos1').addEventListener('input', actualizarGanadora);
            document.getElementById('juegos2').addEventListener('input', actualizarGanadora);
            actualizarGanadora();
        })
        .catch(err => {
            console.error(err);
            alert('❌ Error al cargar datos');
            volverAFixture();
        });
}

function actualizarGanadora() {
    const j1 = parseInt(document.getElementById('juegos1').value) || 0;
    const j2 = parseInt(document.getElementById('juegos2').value) || 0;
    const div = document.getElementById('ganadora');
    
    // Obtener nombres de los labels debajo de los inputs
    const labels = document.querySelectorAll('.resultado-inputs div div');
    const n1 = labels[0]?.textContent || 'Pareja 1';
    const n2 = labels[1]?.textContent || 'Pareja 2';

    if (j1 > j2) div.textContent = `🏆 Ganador: ${n1}`;
    else if (j2 > j1) div.textContent = `🏆 Ganador: ${n2}`;
    else div.textContent = 'Empate';
}

function guardarResultado(idPartido, pareja1, pareja2) {
    const j1 = document.getElementById('juegos1').value;
    const j2 = document.getElementById('juegos2').value;

    if(j1 === '' || j2 === '') { alert('Completa ambos marcadores'); return; }

    fetch('../api/guardar_resultado_torneo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ id_partido: idPartido, juegos1: j1, juegos2: j2 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // En lugar de volver al HTML viejo, recargamos el fixture fresco
            verFixture(window.torneoActualId); 
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Error de conexión');
    });
}

// Esta función ahora solo sirve para cancelar la edición
function volverAFixture() {
    verFixture(window.torneoActualId);
}

function cerrarSubmodal() {
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay.querySelector('.submodal-card');
    if(card) card.classList.remove('fixture-width', 'compact-modal'); // Limpiar clases
    if(overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }
    contenidoFixtureAnterior = '';
}

// === 📊 VER RESULTADOS GENERALES ===
function verResultados(idTorneo) {
    const contenido = document.getElementById('submodalContenido');
    contenido.innerHTML = '<p style="text-align:center;">Cargando resultados...</p>';
    
    fetch(`../api/get_resultados_torneo.php?id_torneo=${idTorneo}`)
        .then(r => r.json())
        .then(data => {
            // VALIDACIÓN DE SEGURIDAD
            if (!Array.isArray(data)) {
                console.error('❌ La API no devolvió un array:', data);
                contenido.innerHTML = `<div style="text-align:center; color:red; padding:2rem;">
                    <p>Error: ${data.error || 'Datos inválidos verResultados'}</p>
                    <button class="action-btn" onclick="cerrarSubmodal()">Cerrar</button>
                </div>`;
                return;
            }

            if (data.length === 0) {
                contenido.innerHTML = `<div style="text-align:center; padding:3rem;"><p>No hay fixture generado.</p><button class="action-btn" onclick="cerrarSubmodal()">Cerrar</button></div>`;
                return;
            }
            if (!data || data.length === 0) {
                contenido.innerHTML = '<p style="text-align:center;">No hay resultados registrados.</p><button class="action-btn" onclick="volverAFixture()">Volver</button>';
                return;
            }

            const rondas = {};
            data.forEach(partido => {
                const key = new Date(partido.fecha_hora_programada).toISOString().split('T')[0] + '_' + 
                            new Date(partido.fecha_hora_programada).getHours();
                if (!rondas[key]) rondas[key] = [];
                rondas[key].push(partido);
            });

            let html = `<h3 style="color:#071289;">📊 Resultados Generales</h3>`;
            html += `<table style="width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem;">`;
            html += `<thead><tr style="background:#071289; color:white;"><th>Ronda</th><th>Pareja 1</th><th>Res</th><th>Pareja 2</th></tr></thead><tbody>`;

            let numRonda = 1;
            Object.values(rondas).forEach(partidos => {
                partidos.forEach(p => {
                    const ganador = (parseInt(p.juegos1) > parseInt(p.juegos2)) ? p.pareja1 : p.pareja2;
                    html += `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:0.5rem;">Set ${numRonda}</td>
                            <td style="padding:0.5rem; font-weight:600;">${p.pareja1} (${p.juegos1})</td>
                            <td style="padding:0.5rem; text-align:center; color:#999;">vs</td>
                            <td style="padding:0.5rem; font-weight:600;">${p.pareja2} (${p.juegos2})</td>
                        </tr>
                    `;
                });
                numRonda++;
            });

            html += `</tbody></table>`;
            html += `<button class="action-btn" style="margin-top:1rem;" onclick="volverAFixture()">Volver al Fixture</button>`;
            contenido.innerHTML = html;
        });
}

// === 🏆 VER POSICIONES ===
function verPosicionesTorneo(idTorneo) {
    const contenido = document.getElementById('submodalContenido');
    contenido.innerHTML = '<p style="text-align:center;">Cargando posiciones...</p>';

    fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`)
        .then(r => r.json())
        .then(data => {
            if (!data.posiciones || data.posiciones.length === 0) {
                contenido.innerHTML = '<p style="text-align:center;">No hay datos de posiciones.</p><button class="action-btn" onclick="volverAFixture()">Volver</button>';
                return;
            }

            let html = `<h3 style="color:#071289;">🏆 Tabla de Posiciones – ${data.torneo_nombre}</h3>`;
            html += `<table style="width:100%; border-collapse:collapse; margin-top:1rem;">`;
            html += `<thead><tr style="background:#071289; color:white;"><th>#</th><th>Pareja</th><th>Sets Ganados</th></tr></thead><tbody>`;

            data.posiciones.forEach((p, index) => {
                const medal = index === 0 ? '🥇' : (index === 1 ? '🥈' : (index === 2 ? '🥉' : ''));
                html += `
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:0.8rem; text-align:center;">${medal} ${index + 1}</td>
                        <td style="padding:0.8rem; font-weight:600;">${p.nombre_pareja}</td>
                        <td style="padding:0.8rem; text-align:center; font-weight:bold; color:#071289;">${p.sets_ganados}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            html += `<button class="action-btn" style="margin-top:1rem;" onclick="volverAFixture()">Volver al Fixture</button>`;
            contenido.innerHTML = html;
        })
        .catch(err => {
            console.error('Error al cargar posiciones:', err);
            alert('❌ Error al cargar el cuadro de resultados');
            volverAFixture();
        });
}

// === 🎾 VER FIXTURE (Versión Estable Restaurada) ===
function verFixture(idTorneo) {
    window.torneoActualId = idTorneo;
    
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay.querySelector('.submodal-card');
    const contenido = document.getElementById('submodalContenido');
    
    if (!overlay || !card || !contenido) return;

    // Resetear estilos TV si quedaron activos
    card.style.maxWidth = '600px'; 
    card.style.height = 'auto';
    card.style.display = 'block';
    card.style.background = 'white';
    card.style.color = '#333';
    contenido.style.display = 'block';
    contenido.style.width = 'auto';
    contenido.style.height = 'auto';

    overlay.style.display = 'flex';
    void overlay.offsetWidth;
    overlay.classList.add('active');
    
    contenido.innerHTML = '<p style="text-align:center; padding:2rem; color:#666;">🔄 Cargando fixture...</p>';

    fetch(`../api/get_torneo_nombre.php?id_torneo=${idTorneo}`)
        .then(r => r.json())
        .then(torneoData => {
            const nombreTorneo = torneoData.nombre || 'Torneo';
            
            return fetch(`../api/get_fixture.php?id_torneo=${idTorneo}`)
                .then(r => {
                    if (!r.ok) throw new Error(`Error ${r.status}`);
                    return r.json();
                })
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        contenido.innerHTML = `
                            <div style="text-align:center; padding:3rem;">
                                <div style="font-size:3rem; margin-bottom:1rem;">📋</div>
                                <p>No hay fixture generado aún.</p>
                                <button class="action-btn" style="margin-top:1rem;" onclick="cerrarSubmodal()">Cerrar</button>
                            </div>`;
                        return;
                    }

                    let html = `<h3 style="color:#071289; margin-bottom:1rem; text-align:center; font-size:1.2rem;">🎾 Fixture - ${nombreTorneo}</h3>`;
                    
                    const rondas = {};
                    data.forEach(partido => {
                        const key = partido.fecha_hora_programada;
                        if (!rondas[key]) rondas[key] = [];
                        rondas[key].push(partido);
                    });

                    let rondaNum = 1;
                    Object.entries(rondas).forEach(([fecha, partidos]) => {
                        const fechaObj = new Date(fecha);
                        const fechaStr = fechaObj.toLocaleDateString('es-CL', {day:'2-digit', month:'short'});
                        const horaStr = fechaObj.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
                        
                        html += `<div style="margin-bottom:1.5rem; background:#f8f9fa; border-radius:8px; overflow:hidden;">
                                    <div style="background:#e9ecef; padding:0.6rem 1rem; font-weight:bold; color:#071289; font-size:0.9rem; display:flex; justify-content:space-between;">
                                        <span>SET ${rondaNum}</span>
                                        <span style="color:#666; font-weight:normal;">${fechaStr} - ${horaStr}</span>
                                    </div>
                                    <div style="padding:0.5rem;">`;
                        
                        partidos.forEach(p => {
                            const tieneResultado = p.juegos_pareja_1 !== null && p.juegos_pareja_2 !== null;
                            const marcador = tieneResultado ? `${p.juegos_pareja_1} - ${p.juegos_pareja_2}` : 'VS';
                            const claseEstado = tieneResultado ? 'jugado' : 'pendiente';
                            const estiloMarcador = tieneResultado ? 'color:#2E7D32; font-weight:800;' : 'color:#999;';
                            
                            const g1 = (tieneResultado && p.juegos_pareja_1 > p.juegos_pareja_2) ? 'ganador' : '';
                            const g2 = (tieneResultado && p.juegos_pareja_2 > p.juegos_pareja_1) ? 'ganador' : '';

                            const safeP1 = (p.pareja1 || '').replace(/'/g, "\\'");
                            const safeP2 = (p.pareja2 || '').replace(/'/g, "\\'");

                            html += `
                                <div class="partido-card ${claseEstado}" style="display:flex; justify-content:space-between; align-items:center; margin:0.5rem 0; background:white; padding:0.8rem; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border-left: 3px solid ${tieneResultado ? '#4CAF50' : '#FFC107'};">
                                    <div class="pareja-nombre ${g1}" style="flex:1; text-align:right; padding-right:0.5rem; font-size:0.9rem;">${p.pareja1}</div>
                                    
                                    <div style="min-width:60px; text-align:center; ${estiloMarcador}; font-size:1rem;">${marcador}</div>
                                    
                                    <div class="pareja-nombre ${g2}" style="flex:1; text-align:left; padding-left:0.5rem; font-size:0.9rem;">${p.pareja2}</div>
                                    
                                    <button style="margin-left:0.5rem; background:#071289; color:white; border:none; padding:0.4rem 0.6rem; border-radius:4px; cursor:pointer; font-size:0.75rem; min-width:70px;" 
                                            onclick="abrirResultado(${p.id_partido}, '${safeP1}', '${safeP2}')">
                                        ${tieneResultado ? '✏️ Editar' : '📝 Resultado'}
                                    </button>
                                </div>
                            `;
                        });
                        html += `</div></div>`;
                        rondaNum++;
                    });

                    contenido.innerHTML = html;
                });
        })
        .catch(err => {
            console.error('❌ Error cargando fixture:', err);
            contenido.innerHTML = `
                <div style="text-align:center; color:#c62828; padding:2rem;">
                    ⚠️ Error: ${err.message}<br>
                    <button class="action-btn" style="margin-top:0.5rem;" onclick="verFixture(${idTorneo})">Reintentar</button>
                </div>`;
        });
}

// === ✏️ ABRIR MODAL PARA EDITAR/INGRESAR RESULTADO ===
function abrirModalEditarResultado(idPartido, pareja1, pareja2, val1, val2) {
    const submodal = document.getElementById('submodalGenerico');
    const contenido = document.getElementById('submodalContenido');
    
    if (!submodal || !contenido) {
        console.error('Submodal elements not found');
        return;
    }
    
    submodal.style.display = 'flex';
    void submodal.offsetWidth;
    submodal.classList.add('active');
    
    // Decodificar entidades HTML si vienen así
    const cleanP1 = pareja1.replace(/&quot;/g, '"').replace(/\\'/g, "'");
    const cleanP2 = pareja2.replace(/&quot;/g, '"').replace(/\\'/g, "'");
    
    contenido.innerHTML = `
        <div style="text-align:center; max-width:400px; margin:0 auto;">
            <h3 style="color:#071289; margin-bottom:1rem;">📊 Registrar Resultado</h3>
            <p style="margin-bottom:1.5rem; font-weight:600; font-size:0.9rem;">${cleanP1} <span style="color:#999;">vs</span> ${cleanP2}</p>
            
            <div style="display:flex; justify-content:center; align-items:center; gap:1rem; margin-bottom:1.5rem;">
                <div style="text-align:center;">
                    <label style="display:block; font-size:0.75rem; color:#666; margin-bottom:0.3rem;">Sets P1</label>
                    <input type="number" id="edit_j1" value="${val1}" min="0" max="7" 
                           style="width:70px; padding:0.5rem; text-align:center; font-size:1.2rem; font-weight:bold; border:2px solid #ddd; border-radius:8px;">
                </div>
                <div style="font-size:1.5rem; font-weight:bold; color:#ccc;">-</div>
                <div style="text-align:center;">
                    <label style="display:block; font-size:0.75rem; color:#666; margin-bottom:0.3rem;">Sets P2</label>
                    <input type="number" id="edit_j2" value="${val2}" min="0" max="7" 
                           style="width:70px; padding:0.5rem; text-align:center; font-size:1.2rem; font-weight:bold; border:2px solid #ddd; border-radius:8px;">
                </div>
            </div>
            
            <div id="preview_ganador" style="margin-bottom:1rem; font-weight:bold; color:#071289; height:24px;"></div>
            
            <div style="display:flex; gap:0.5rem; justify-content:center;">
                <button class="action-btn" style="background:#4CAF50;" onclick="guardarResultadoEditado(${idPartido})">💾 Guardar</button>
                <button class="action-btn" style="background:#6c757d;" onclick="cerrarSubmodal()">Cancelar</button>
            </div>
        </div>
    `;
    
    const input1 = document.getElementById('edit_j1');
    const input2 = document.getElementById('edit_j2');
    const preview = document.getElementById('preview_ganador');
    
    const updatePreview = () => {
        const v1 = parseInt(input1.value) || 0;
        const v2 = parseInt(input2.value) || 0;
        if (v1 > v2) preview.textContent = `Ganador: ${cleanP1}`;
        else if (v2 > v1) preview.textContent = `Ganador: ${cleanP2}`;
        else preview.textContent = 'Empate';
    };
    
    input1.addEventListener('input', updatePreview);
    input2.addEventListener('input', updatePreview);
    updatePreview();
}

// === 💾 GUARDAR RESULTADO EDITADO ===
async function guardarResultadoEditado(idPartido) {
    const j1 = document.getElementById('edit_j1').value;
    const j2 = document.getElementById('edit_j2').value;
    
    if (j1 === '' || j2 === '') {
        alert('Ingresa ambos marcadores');
        return;
    }
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    
    try {
        const formData = new FormData();
        formData.append('id_partido', idPartido);
        formData.append('juegos1', j1);
        formData.append('juegos2', j2);
        
        const res = await fetch('../api/guardar_resultado_torneo.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
            cerrarSubmodal();
            if (window.torneoActualId) {
                verFixturePorSets(window.torneoActualId);
            }
        } else {
            alert('❌ Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (e) {
        console.error(e);
        alert('❌ Error de conexión');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function guardarResultadoSet(idPartido) {
    const j1 = document.getElementById(`j1_${idPartido}`).value;
    const j2 = document.getElementById(`j2_${idPartido}`).value;
    
    if(j1 === '' || j2 === '') {
        alert('Ingresa ambos marcadores');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '...';
    
    try {
        const formData = new FormData();
        formData.append('id_partido', idPartido);
        formData.append('juegos1', j1);
        formData.append('juegos2', j2);
        
        const res = await fetch('../api/guardar_resultado_torneo.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) {
            // Recargar solo este set o todo el fixture para reflejar cambios
            // Por simplicidad, recargamos el modal completo
            const idTorneo = window.torneoActualId; 
            if(idTorneo) verFixture(idTorneo);
        } else {
            alert('❌ Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = '💾 Guardar';
        }
    } catch(e) {
        alert('❌ Error de conexión');
        btn.disabled = false;
        btn.textContent = '💾 Guardar';
    }
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

function cerrarSubmodalFixture() {
    const overlay = document.getElementById('submodalFixtureOverlay');
    if(overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }
}

// Cerrar menús al hacer click fuera
document.addEventListener('click', () => {
    document.querySelectorAll('[id^="menu-torneo-"]').forEach(m => m.style.display = 'none');
});

/// === 📺 VER RESULTADOS TV MODE (Split-Screen 80/20 - Todos los Sets) ===
function verResultadosTV(idTorneo) {
    console.log('📺 Iniciando TV Mode Split-Screen para torneo:', idTorneo);
    
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay.querySelector('.submodal-card');
    const contenido = document.getElementById('submodalContenido');
    
    if (!overlay || !card || !contenido) return;

    // 1. Estilos TV Full Screen
    overlay.style.zIndex = 5000;
    card.style.maxWidth = '98%';
    card.style.height = '95vh';
    card.style.padding = '0';
    card.style.display = 'flex'; // Flex horizontal
    card.style.flexDirection = 'row';
    card.style.background = '#1a1a1a'; // Fondo oscuro
    card.style.color = 'white';
    card.style.overflow = 'hidden'; // Sin scroll general
    
    contenido.style.display = 'flex';
    contenido.style.width = '100%';
    contenido.style.height = '100%';
    contenido.innerHTML = '<p style="text-align:center; color:white; font-size:1.5rem; margin:auto;">🔄 Cargando marcador en vivo...</p>';

    // 2. Fetch Paralelo
    Promise.all([
        fetch(`../api/get_resultados_torneo.php?id_torneo=${idTorneo}`).then(r => r.json()),
        fetch(`../api/get_posiciones_torneo.php?id_torneo=${idTorneo}`).then(r => r.json())
    ])
    .then(([dataResultados, dataPosiciones]) => {
        
        // --- COLUMNA IZQUIERDA: FIXTURE (80%) ---
        let htmlFixture = `<div style="width: 80%; height: 100%; overflow-y: auto; padding: 2rem; border-right: 1px solid #333; scrollbar-width: thin;">`;
        htmlFixture += `<h2 style="text-align:center; color:#FFD700; margin-bottom: 2rem; text-transform:uppercase; font-size: 2.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">🏆 Marcador en Vivo</h2>`;
        
        // Agrupar por sets
        const rondas = {};
        if (Array.isArray(dataResultados)) {
            dataResultados.forEach(p => {
                const fechaRaw = p.fecha_hora_programada || p.fecha || new Date().toISOString();
                const key = fechaRaw.substring(0, 16); 
                if(!rondas[key]) rondas[key] = [];
                rondas[key].push(p);
            });
        }

        let setNum = 1;
        Object.values(rondas).forEach(partidos => {
            htmlFixture += `<div style="margin-bottom: 3rem;">
                                <h3 style="color:#4ECDC4; margin-bottom: 1.5rem; font-size: 1.8rem; border-bottom: 2px solid #4ECDC4; padding-bottom: 0.5rem; display:inline-block;">SET ${setNum}</h3>`;
            
            partidos.forEach(p => {
                const j1 = parseInt(p.juegos1) || 0;
                const j2 = parseInt(p.juegos2) || 0;
                
                const styleG1 = (j1 > j2) ? 'color:#4CAF50; font-weight:900; text-shadow: 0 0 10px rgba(76, 175, 80, 0.5);' : 'color:rgba(255,255,255,0.7);';
                const styleG2 = (j2 > j1) ? 'color:#4CAF50; font-weight:900; text-shadow: 0 0 10px rgba(76, 175, 80, 0.5);' : 'color:rgba(255,255,255,0.7);';
                
                htmlFixture += `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin:1rem 0; font-size: 1.6rem; background:rgba(255,255,255,0.05); padding:1.2rem; border-radius:12px; border: 1px solid rgba(255,255,255,0.1);">
                        <span style="flex:1; text-align:right; padding-right:1.5rem; ${styleG1}">${p.pareja1 || 'TBD'}</span>
                        <span style="padding:0 2rem; font-weight:bold; font-size: 2.2rem; color:white; background:rgba(0,0,0,0.4); border-radius:12px; min-width:140px; text-align:center; letter-spacing: 2px;">${j1} - ${j2}</span>
                        <span style="flex:1; text-align:left; padding-left:1.5rem; ${styleG2}">${p.pareja2 || 'TBD'}</span>
                    </div>
                `;
            });
            htmlFixture += `</div>`;
            setNum++;
        });
        htmlFixture += `</div>`;

        // --- COLUMNA DERECHA: POSICIONES (20%) ---
        let htmlPosiciones = `<div style="width: 20%; height: 100%; overflow-y: auto; padding: 1.5rem; background: rgba(0,0,0,0.2);">`;
        htmlPosiciones += `<h3 style="text-align:center; color:#FFD700; margin-bottom: 1.5rem; font-size: 1.4rem; text-transform:uppercase;">Posiciones</h3>`;
        
        if (dataPosiciones && dataPosiciones.posiciones && dataPosiciones.posiciones.length > 0) {
            // Letra aumentada 50% (de 0.9rem a ~1.35rem)
            htmlPosiciones += `<table style="width:100%; border-collapse:collapse; font-size: 1.35rem;">`; 
            
            dataPosiciones.posiciones.forEach((p, index) => {
                const medal = index === 0 ? '🥇' : (index === 1 ? '🥈' : (index === 2 ? '🥉' : `${index + 1}.`));
                const bgRow = index < 3 ? 'background:rgba(255,215,0,0.1);' : '';
                
                htmlPosiciones += `
                    <tr style="border-bottom:1px solid #444; ${bgRow}">
                        <td style="padding:0.8rem 0.2rem; font-weight:bold;">${medal}</td>
                        <td style="padding:0.8rem 0.2rem; font-weight:500; line-height:1.1; word-wrap: break-word;">${p.nombre_pareja}</td>
                        <td style="padding:0.8rem 0.2rem; text-align:right; font-weight:bold; color:#4ECDC4;">${p.sets_ganados}</td>
                    </tr>
                `;
            });
            htmlPosiciones += `</table>`;
        } else {
            htmlPosiciones += `<p style="text-align:center; color:#666; font-size:1rem;">Sin datos de posiciones aún.</p>`;
        }
        
        htmlPosiciones += `</div>`;

        // Unir columnas
        contenido.innerHTML = htmlFixture + htmlPosiciones;
    })
    .catch(err => {
        console.error('❌ Error TV Mode:', err);
        contenido.innerHTML = `<div style="text-align:center; color:#ff5252; padding:2rem;"><h3>Error al cargar resultados</h3><p>${err.message}</p></div>`;
    });
}

function cerrarSubmodalTV() {
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay.querySelector('.submodal-card');
    const contenido = document.getElementById('submodalContenido');
    
    // Restaurar estilos originales
    if(overlay) overlay.style.zIndex = 4000;
    if(card) {
        card.style.maxWidth = ''; 
        card.style.height = '';
        card.style.padding = '';
        card.style.display = '';
        card.style.flexDirection = '';
        card.style.background = '';
        card.style.color = '';
        card.style.overflow = '';
    }
    if(contenido) {
        contenido.style.display = '';
        contenido.style.width = '';
        contenido.style.height = '';
    }
    
    cerrarSubmodal();
}

function cerrarSubmodalTV() {
    const overlay = document.getElementById('submodalGenerico');
    const card = overlay ? overlay.querySelector('.submodal-card') : null;
    const contenido = document.getElementById('submodalContenido');
    
    // Restaurar estilos
    if(overlay) overlay.style.zIndex = 4000;
    if(card) {
        card.style.maxWidth = ''; 
        card.style.height = '';
        card.style.padding = '';
        card.style.display = '';
        card.style.flexDirection = '';
        card.style.background = '';
        card.style.color = '';
        card.style.overflow = '';
    }
    if(contenido) {
        contenido.style.display = '';
        contenido.style.width = '';
        contenido.style.height = '';
    }
    
    cerrarSubmodal();
}
// === ✅ FINALIZAR TORNEO Y CALCULAR RANKING ===
function finalizarTorneoYCalcularRanking(idTorneo) {
    if (!confirm('⚠️ ¿Estás seguro de FINALIZAR este torneo?\n\nEsto calculará el ranking oficial y cerrará las inscripciones. Esta acción no se puede deshacer.')) return;

    console.log('🏁 Finalizando torneo:', idTorneo);

    // 1. Validar que todos los partidos tengan resultado (opcional, pero recomendado)
    fetch(`../api/validar_torneo_finalizado.php?id_torneo=${idTorneo}`)
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('❌ No se puede finalizar: ' + (data.message || 'Faltan resultados por ingresar'));
            return;
        }

        // 2. Calcular Ranking
        return fetch('../api/calcular_ranking_torneo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_torneo: idTorneo })
        });
    })
    .then(r => {
        if(!r) return; // Si falló el paso anterior
        return r.json();
    })
    .then(res => {
        if (res && res.success) {
            alert('✅ Torneo finalizado y Ranking actualizado correctamente');
            location.reload(); // Recargar para ver cambios
        } else if (res) {
            alert('❌ Error al calcular ranking: ' + res.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('❌ Error de conexión al finalizar el torneo');
    });
}
// === MENÚ DE 3 PUNTOS: TOGGLE ===
function toggleLogMenu(event, idReserva) {
    event.stopPropagation();
    // Cerrar otros menús abiertos
    document.querySelectorAll('[id^="logMenu_"]').forEach(menu => {
        if (menu.id !== `logMenu_${idReserva}`) menu.style.display = 'none';
    });
    // Toggle del menú actual
    const menu = document.getElementById(`logMenu_${idReserva}`);
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

// Cerrar menús al hacer click fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('[onclick*="toggleLogMenu"]')) {
        document.querySelectorAll('[id^="logMenu_"]').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

// === ABRIR MODAL DE BITÁCORA ===
async function abrirLogReserva(idReserva) {
    const modal = document.getElementById('modalLogReserva');
    const tbody = document.getElementById('logReservaBody');
    const titleId = document.getElementById('logReservaId');
    
    if (!modal || !tbody) return;
    
    // Mostrar modal y cargar datos
    titleId.textContent = idReserva;
    tbody.innerHTML = '<tr><td colspan="4" style="padding:20px; text-align:center; color:#888;">🔄 Cargando...</td></tr>';
    modal.style.display = 'flex';
    
    try {
        const res = await fetch(`../api/get_log_reserva.php?id_reserva=${idReserva}`);
        const data = await res.json();
        
        if (data.success && Array.isArray(data.logs) && data.logs.length > 0) {
            tbody.innerHTML = data.logs.map(log => `
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:10px; color:#4A5568; font-weight:500;">${log.fecha}</td>
                    <td style="padding:10px; color:#2D3748;">${log.usuario}</td>
                    <td style="padding:10px;">
                        <span style="padding:4px 8px; border-radius:6px; font-size:0.8rem; font-weight:500; background:${getAccionColor(log.accion)}; color:white;">
                            ${formatAccion(log.accion)}
                        </span>
                    </td>
                    <td style="padding:10px; color:#4A5568; font-size:0.9rem;">
                        ${log.descripcion || '-'}
                        ${log.monto_anterior || log.monto_nuevo ? 
                            `<br><small style="color:#666;">$${log.monto_anterior || '?'} → $${log.monto_nuevo || '?'}</small>` : ''}
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="padding:20px; text-align:center; color:#888;">Sin registros de auditoría</td></tr>';
        }
    } catch (err) {
        console.error('Error cargando log:', err);
        tbody.innerHTML = '<tr><td colspan="4" style="padding:20px; text-align:center; color:#C62828;">Error al cargar historial</td></tr>';
    }
}

// Helpers para formato visual
function formatAccion(accion) {
    const map = {
        'creada': '✅ Creada',
        'movida': '🔄 Movida',
        'anulada': '❌ Anulada',
        'cobro_parcial': '💰 Pago parcial',
        'cobro_total': '✅ Pagada',
        'editada': '✏️ Editada',
        'reembolso': '↩️ Reembolso'
    };
    return map[accion] || accion;
}

function getAccionColor(accion) {
    const colors = {
        'creada': '#4CAF50',
        'movida': '#2196F3',
        'anulada': '#F44336',
        'cobro_parcial': '#FF9800',
        'cobro_total': '#4CAF50',
        'editada': '#9C27B0',
        'reembolso': '#607D8B'
    };
    return colors[accion] || '#9E9E9E';
}

// === CERRAR MODAL ===
function cerrarModalLog(e) {
    if (e.target.id === 'modalLogReserva' || e.target.closest('.modal-content button')) {
        document.getElementById('modalLogReserva').style.display = 'none';
    }
}

// === REGISTRAR LOG DESDE JS (para acciones futuras) ===
async function registrarLogReserva(idReserva, accion, descripcion, metadata = null, montos = {}) {
    try {
        await fetch('../api/registrar_log_reserva.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_reserva: idReserva,
                accion: accion,
                descripcion: descripcion,
                metadata: metadata,
                monto_anterior: montos.anterior || null,
                monto_nuevo: montos.nuevo || null
            })
        });
    } catch (err) {
        console.error('Error registrando log:', err);
        // No bloquear la acción principal si falla el log
    }
}
</script>
    <!-- === MODAL RESERVA MANUAL ADMIN (VERSIÓN COMPLETA) === -->
    <div id="modalReservaAdmin" class="modal-overlay" style="display:none;" onclick="cerrarModalReservaAdmin(event)">
    <div class="modal-content">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
        <h3 style="margin:0; color:#AB47BC; font-size:1.2rem;">🎾 Nueva Reserva</h3>
        <button onclick="cerrarModalReservaAdmin(event)" 
                style="background:none; border:none; font-size:1.5rem; color:#999; cursor:pointer; width:32px; height:32px; border-radius:50%; display:grid; place-items:center;">
            &times;
        </button>
        </div>

        <!-- Formulario -->
        <form id="formReservaManual" onsubmit="guardarReservaAdmin(event)">
        
        <!-- Campos ocultos (CRÍTICOS) -->
        <input type="hidden" id="admin_cancha_id" name="id_cancha">
        <input type="hidden" id="admin_fecha" name="fecha">
        <input type="hidden" id="admin_hora_inicio" name="hora_inicio">
        <input type="hidden" id="admin_hora_fin" name="hora_fin">
        <input type="hidden" id="admin_socio_id" name="id_socio">
        <input type="hidden" id="admin_monto_total" name="monto_total">
        <input type="hidden" id="admin_duracion_bloque" name="duracion_bloque" value="60">

        <!-- ✅ Resumen visual con fecha, hora y cancha -->
        <div style="background:linear-gradient(135deg, #F3E5F5, #E1BEE7); padding:1rem; border-radius:12px; margin-bottom:1.25rem; text-align:center; border:1px solid #CE93D8;">
            <div style="font-weight:700; color:#4A148C; font-size:1.05rem; margin-bottom:0.3rem;" id="modalCanchaDisplay">🏟️ Cargando...</div>
            <div style="display:flex; justify-content:center; gap:1rem; font-size:0.95rem;">
                <span style="color:#4A148C;">📅 <strong id="modalFechaDisplay" style="color:#6A1B9A;">--/--</strong></span>
                <span style="color:#4A148C;">⏰ <strong id="modalHoraDisplay" style="color:#6A1B9A;">--:--</strong></span>
            </div>
        </div>

        <!-- ✅ Selector de duración (60/90 min) -->
        <div style="margin-bottom:1rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem; color:#333;">⏱️ Duración de reserva</label>
            <div style="display:flex; gap:0.5rem;">
            <label style="flex:1; padding:0.6rem; border:2px solid #E2E8F0; border-radius:10px; text-align:center; cursor:pointer; background:#F7FAFC; transition:all 0.2s;">
                <input type="radio" name="duracion" value="60" checked onchange="actualizarDuracionReserva(this.value)" style="margin-right:0.4rem;"> 60 min
            </label>
            <label style="flex:1; padding:0.6rem; border:2px solid #E2E8F0; border-radius:10px; text-align:center; cursor:pointer; background:#F7FAFC; transition:all 0.2s;">
                <input type="radio" name="duracion" value="90" onchange="actualizarDuracionReserva(this.value)" style="margin-right:0.4rem;"> 90 min
            </label>
            </div>
        </div>

        <!-- ✅ Buscador Inteligente de Socio (con tu código integrado) -->
        <div style="position:relative; margin-bottom:1rem;">
            <label style="display:block; font-weight:600; margin-bottom:0.5rem; color:#333;">👤 Socio *</label>
            <input type="text" id="searchAdmin" placeholder="Buscar socio (nombre, email, celular)..." 
                oninput="debounceBuscar(this.value)" 
                style="width:100%; padding:0.75rem; border:2px solid #E2E8F0; border-radius:10px; font-size:1rem;">
            <div id="searchResultsAdmin" style="position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #eee; border-radius:8px; max-height:180px; overflow-y:auto; z-index:10; display:none; box-shadow:0 5px 15px rgba(0,0,0,0.1);"></div>
            <input type="hidden" id="admin_socio_id">
            <input type="hidden" id="admin_nombre">
            <input type="hidden" id="admin_email">
            <input type="hidden" id="admin_celular">
        </div>

        <!-- Resumen de monto -->
        <div style="background:#E8F5E9; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; border-left:4px solid #4CAF50;">
            <span style="font-weight:600; color:#2E7D32;">💰 Total a pagar:</span>
            <span style="font-size:1.2rem; font-weight:700; color:#2E7D32;" id="modalMontoDisplay">$0</span>
        </div>

        <!-- ✅ Sección Reserva Recurrente (con evento fijo) -->
        <div style="margin:1.25rem 0; padding-top:1rem; border-top:1px solid #eee;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
            <input type="checkbox" id="isRecurrent" style="width:18px; height:18px;" onchange="toggleRecurrentFields(this.checked)">
            <label for="isRecurrent" style="font-weight:600; color:#333; cursor:pointer;">🔄 Crear reserva recurrente</label>
            </div>
            
            <div id="recurrentFields" style="display:none; background:#F7FAFC; padding:1rem; border-radius:10px; border:1px solid #E2E8F0;">
            <div class="form-group">
                <label style="font-size:0.9rem; font-weight:600; color:#333;">Repetir cada:</label>
                <select id="repeatDay" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;">
                <option value="1">Lunes</option>
                <option value="2">Martes</option>
                <option value="3">Miércoles</option>
                <option value="4">Jueves</option>
                <option value="5">Viernes</option>
                <option value="6">Sábado</option>
                <option value="0">Domingo</option>
                </select>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.75rem;">
                <div class="form-group">
                <label style="font-size:0.9rem; font-weight:600; color:#333;">Fecha inicio *</label>
                <input type="date" id="startDate" name="start_date" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;">
                </div>
                <div class="form-group">
                <label style="font-size:0.9rem; font-weight:600; color:#333;">Fecha fin *</label>
                <input type="date" id="endDate" name="end_date" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;">
                </div>
            </div>
            
            <div style="margin-top:0.75rem; font-size:0.85rem; color:#666;">
                <span id="previewDates">Selecciona fechas para ver las fechas generadas</span>
            </div>
            </div>
        </div>

        <!-- Botón submit -->
        <button type="submit" style="width:100%; padding:0.9rem; background:linear-gradient(135deg,#CE93D8,#AB47BC); color:white; border:none; border-radius:14px; font-weight:600; font-size:1rem; cursor:pointer; margin-top:0.5rem; transition:transform 0.2s;">
            💾 Crear Reserva
        </button>
        </form>
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

    <!-- === SISTEMA DE TOAST NOTIFICATIONS === -->
    <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

    <!-- === SUBMODAL GENÉRICO (Fixture, Resultados, Ranking) === -->
    <div id="submodalGenerico" class="submodal-overlay" style="display:none;">
        <div class="submodal-card">
            <button class="submodal-close" onclick="cerrarSubmodal()">&times;</button>
            <div id="submodalContenido" style="max-height:70vh; overflow-y:auto; padding:0.5rem;">
                <!-- Contenido inyectado por JS -->
            </div>
        </div>
    </div>
    <!-- MODAL INSCRITOS -->
    <div id="submodalInscritosOverlay" class="torneo-submodal-overlay">
        <div class="torneo-submodal-card">
            <div class="torneo-header">
                <h3 style="margin:0; color:#071289;">👥 Parejas Inscritas</h3>
                <button onclick="cerrarSubmodalInscritos()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="submodalInscritosBody" class="torneo-body"></div>
        </div>
    </div>

    <!-- MODAL FIXTURE POR SETS -->
    <div id="submodalFixtureOverlay" class="torneo-submodal-overlay">
        <div class="torneo-submodal-card">
            <div class="torneo-header">
                <h3 style="margin:0; color:#071289;">🎾 Fixture por Sets</h3>
                <button onclick="cerrarSubmodalFixture()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="submodalFixtureBody" class="torneo-body"></div>
        </div>
    </div>

    <!-- === MODAL BITÁCORA DE RESERVA === -->
    <div id="modalLogReserva" class="modal-overlay" style="display:none;" onclick="cerrarModalLog(event)">
    <div class="modal-content" style="max-width:580px; padding:1.5rem; border-radius:16px;">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="margin:0; color:#AB47BC; font-size:1.1rem;">📋 Bitácora de Reserva #<span id="logReservaId"></span></h3>
        <button onclick="cerrarModalLog(event)" 
                style="background:none; border:none; font-size:1.3rem; color:#999; cursor:pointer; width:30px; height:30px; border-radius:50%; display:grid; place-items:center;">
            &times;
        </button>
        </div>
        
        <!-- Tabla de logs -->
        <div style="max-height:400px; overflow-y:auto; border:1px solid #E2E8F0; border-radius:10px;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead style="position:sticky; top:0; background:#F7FAFC; z-index:1;">
            <tr>
                <th style="padding:10px; text-align:left; font-weight:600; color:#4A5568; border-bottom:2px solid #E2E8F0;">Fecha</th>
                <th style="padding:10px; text-align:left; font-weight:600; color:#4A5568; border-bottom:2px solid #E2E8F0;">Usuario</th>
                <th style="padding:10px; text-align:left; font-weight:600; color:#4A5568; border-bottom:2px solid #E2E8F0;">Acción</th>
                <th style="padding:10px; text-align:left; font-weight:600; color:#4A5568; border-bottom:2px solid #E2E8F0;">Detalle</th>
            </tr>
            </thead>
            <tbody id="logReservaBody">
            <tr><td colspan="4" style="padding:20px; text-align:center; color:#888;">Cargando historial...</td></tr>
            </tbody>
        </table>
        </div>
        
        <!-- Footer -->
        <div style="margin-top:1rem; text-align:right; font-size:0.8rem; color:#888;">
        Ordenado por fecha (más reciente primero)
        </div>
    </div>
    </div>
</body>
</html>