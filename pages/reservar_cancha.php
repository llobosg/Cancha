<?php
// pages/reservar_cancha.php
require_once __DIR__ . '/../includes/config.php';

// Manejo rápido de guardado de favoritos vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_favorites') {
    header('Content-Type: application/json');
    if (isset($_SESSION['id_socio'])) {
        $stmt = $pdo->prepare("UPDATE socios SET club_favorito = ?, deporte_favorito = ? WHERE id_socio = ?");
        $stmt->execute([$_POST['club_favorito'], $_POST['deporte_favorito'], $_SESSION['id_socio']]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    }
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('CANCHASPORT_SESSION');
}

if (!isset($_SESSION['id_socio'])) { header('Location: ../index.php'); exit; }

$id_socio = (int)$_SESSION['id_socio'];
$stmt = $pdo->prepare("SELECT id_socio, nombre, alias, email, celular, club_favorito, deporte_favorito FROM socios WHERE id_socio = ?");
$stmt->execute([$id_socio]);
$usuario_data = $stmt->fetch();

if (!$usuario_data) { header('Location: ../index.php'); exit; }

// === 1. OBTENER CLUBES DONDE EL SOCIO ES RESPONSABLE (PARA ESTE ARCHIVO) ===
$clubes_responsable = [];
try {
    $stmt_r = $pdo->prepare("
        SELECT c.id_club, c.nombre as club_nombre 
        FROM socio_club sc 
        JOIN clubs c ON sc.id_club = c.id_club 
        WHERE sc.id_socio = ? AND sc.es_responsable = 1
    ");
    $stmt_r->execute([$id_socio]);
    $clubes_responsable = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para confirmar que llega dato a este archivo
    error_log("[RESERVAR_CANCHA] Socio $id_socio tiene " . count($clubes_responsable) . " clubes responsables.");
} catch (Exception $e) {
    error_log("[RESERVAR_CANCHA] Error cargando clubes: " . $e->getMessage());
}

// Obtener Recintos y Deportes
$stmt_recintos = $pdo->prepare("SELECT id_recinto, nombre FROM recintos_deportivos WHERE email_verified = 1 ORDER BY nombre");
$stmt_recintos->execute(); $recintos = $stmt_recintos->fetchAll();

$deportes = [
    'futbol' => 'Fútbol', 'futbolito' => 'Futbolito', 'futsal' => 'Futsal',
    'tenis' => 'Tenis', 'padel' => 'Pádel', 'voleyball' => 'Voleyball', 'otro' => 'Otros'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Reservar Cancha | CanchaSport</title>
<style>
    :root { --bg-primary: #071289; --accent: #4ECDC4; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: linear-gradient(rgba(0, 20, 10, 0.4), rgba(0, 30, 15, 0.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
        background-blend-mode: multiply; color: white; font-family: 'Segoe UI', sans-serif; min-height: 100vh; padding-bottom: 2rem;
    }
    
    .header {
        background: rgba(0, 51, 102, 0.9); backdrop-filter: blur(10px);
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.8rem 1.5rem; position: sticky; top: 0; z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .brand-logo { color: #FFD700; font-weight: 900; font-size: 1.3rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    
    .main-container { max-width: 98%; margin: 1.5rem auto; padding: 0 1rem; }
    
    .controls-section {
        display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.5rem;
        padding: 0.8rem; background: rgba(255,255,255,0.15); border-radius: 50px;
        backdrop-filter: blur(8px); align-items: center; justify-content: center;
    }
    .control-select {
        background: white; padding: 0.4rem 0.8rem; border-radius: 20px; color: #071289; border: none; font-weight: bold;
        min-width: 120px; font-size: 0.85rem; cursor: pointer;
    }
    .date-nav-btn {
        background: white; border: 1px solid #ddd; border-radius: 50%; width: 32px; height: 32px;
        color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;
    }
    .date-nav-btn:hover { background: #f0f0f0; }
    
    .planilla-wrapper {
        background: transparent; box-shadow: none; border-radius: 16px;
        display: flex; justify-content: center; padding: 20px 0;
    }
    .planilla-scroll { overflow-x: auto; padding: 10px 0; max-height: 75vh; }
    
    .planilla-table {
        width: auto; border-collapse: separate; border-spacing: 6px; background: transparent;
        table-layout: fixed; min-width: 600px; margin: 0 auto;
    }
    
    .planilla-table thead th {
        background: rgba(255,255,255,0.9) !important; color: #333; position: sticky; top: 0; z-index: 5;
        border: none; border-radius: 12px; padding: 10px; font-size: 0.85rem; font-weight: bold;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .planilla-table td:first-child {
        position: sticky; left: 0; z-index: 10;
        background: rgba(255,255,255,0.95) !important; color: #555; font-weight: bold;
        border: none; border-radius: 10px; width: 70px !important; text-align: center;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }

    .planilla-table td {
        padding: 8px 4px; vertical-align: middle; text-align: center;
        border: none; border-radius: 10px; transition: all 0.2s ease; height: 40px;
    }
    
    td.estado-disponible { background: rgba(255,255,255,0.8) !important; cursor: pointer; }
    td.estado-disponible:hover { background: #e8f5e9 !important; transform: scale(1.05); box-shadow: 0 4px 8px rgba(0,0,0,0.15); z-index: 2; position: relative; }
    td.estado-ocupado { background: #FF5252 !important; color: white !important; font-size: 0.75rem; line-height: 1.2; }
    
    /* Modal Styles */
    .modal-reserva-inteligente { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
    .modal-reserva-inteligente-content {
        background-color: white; padding: 2rem; border-radius: 16px; width: 90%; max-width: 450px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); color: #333;
    }
    .btn-primary {
        background: #071289; color: white; border: none; padding: 0.8rem; border-radius: 8px;
        font-weight: bold; cursor: pointer; width: 100%; margin-top: 1rem; transition: background 0.2s;
    }
    .btn-primary:hover { background: #050d6b; }
    .btn-secondary {
        background: #eee; color: #333; border: none; padding: 0.8rem; border-radius: 8px;
        font-weight: bold; cursor: pointer; width: 100%; margin-top: 0.5rem; transition: background 0.2s;
    }
    .btn-secondary:hover { background: #ddd; }
    
    /* Duration Radios */
    .duration-options {
        display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;
    }
    .duration-label {
        flex: 1; min-width: 60px; padding: 0.5rem; border: 2px solid #E2E8F0; border-radius: 10px;
        text-align: center; cursor: pointer; background: #F7FAFC; transition: all 0.2s; font-size: 0.9rem;
        display: flex; align-items: center; justify-content: center; gap: 0.3rem;
    }
    .duration-label:hover { border-color: #071289; background: #E3F2FD; }
    .duration-label input { margin-right: 0.2rem; }
    
    .toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; z-index: 3000; transform: translateX(120%); transition: transform 0.3s; }
    .toast.show { transform: translateX(0); }
    .toast.success { background: #4CAF50; }
    .toast.error { background: #C62828; }
    
    /* Estilos Ficha Recurrencia */
    .recap-card {
        background: #f8f9fa;
        border-left: 4px solid #071289;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }
    .recap-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    .recap-total {
        border-top: 1px solid #ddd;
        padding-top: 0.5rem;
        margin-top: 0.5rem;
        font-weight: bold;
        font-size: 1.1rem;
        color: #071289;
    }

    /* === ESTILOS PARA HORARIOS Y FECHAS VENCIDAS === */
    td.slot-pasado { 
        background: rgba(200, 200, 200, 0.4) !important; 
        cursor: not-allowed !important; 
        position: relative;
        opacity: 0.6;
    }

    td.slot-pasado::after {
        content: '🕒';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 1rem;
        opacity: 0.8;
    }
</style>
</head>
<body>
<div class="header">
    <div class="brand-logo">
        🏟️ Reservar Cancha
        <!-- Balón FIFA 2026 -->
        <div class="balon-container">
            <img src="../assets/img/balonfifa2026.png" alt="FIFA 2026" class="balon-animado-img">
        </div>
    </div>
    <a href="dashboard_socio.php" style="color: white; text-decoration: none;">⬅ Dashboard</a>
</div>

<div class="main-container">
    <div class="controls-section">
        <button class="date-nav-btn" onclick="cambiarDia(-1)">&lt;</button>
        <input type="date" id="filtroFecha" class="control-select" style="width: 135px;">
        <button class="date-nav-btn" onclick="irAHoy()" style="width:auto; padding:0 12px; border-radius:20px; font-size:0.8rem; height:32px;">Hoy</button>
        <button class="date-nav-btn" onclick="cambiarDia(1)">&gt;</button>
        
        <select class="control-select" id="filtroDeporte">
            <option value="">Deporte...</option>
            <?php foreach ($deportes as $key => $value): ?>
                <option value="<?= $key ?>"><?= $value ?></option>
            <?php endforeach; ?>
        </select>
        
        <select class="control-select" id="filtroRecinto">
            <option value="">Recinto...</option>
            <?php foreach ($recintos as $recinto): ?>
                <option value="<?= $recinto['id_recinto'] ?>"><?= htmlspecialchars($recinto['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <button onclick="aplicarFiltros(true)" style="background:#4ECDC4; border:none; padding:0.5rem 1.2rem; border-radius:20px; cursor:pointer; font-weight:bold; color:#071289; font-size:0.9rem;">🔍 Buscar</button>
    </div>

    <div class="planilla-wrapper">
        <div class="planilla-scroll">
            <table class="planilla-table" id="tablaReservas">
                <thead id="tablaHeader"><tr><th>Hora</th></tr></thead>
                <tbody id="tablaBody"><tr><td colspan="100%" style="padding:2rem; text-align:center; color: #aaa;">Cargando disponibilidad...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Reserva Inteligente -->
<div id="modalReservaInteligente" class="modal-reserva-inteligente">
    <!-- === SELECTOR DE CONTEXTO === -->
    <?php if (!empty($clubes_responsable)): ?>
        <div style="margin-bottom:1rem; padding:1rem; background:#F3E5F5; border-radius:10px; border:1px solid #CE93D8;">
            <label style="font-weight:600; color:#4A148C; display:block; margin-bottom:0.5rem;">🏢 ¿A nombre de quién?</label>
            
            <div style="display:flex; gap:1rem;">
                <label><input type="radio" name="tipo_reserva" value="individual" checked onchange="toggleClubContext(false)"> 👤 Personal</label>
                <label><input type="radio" name="tipo_reserva" value="club" onchange="toggleClubContext(true)"> 🏟️ Club</label>
            </div>

            <select id="id_club_reserva" name="id_club_reserva" style="width:100%; margin-top:0.5rem; display:none; padding:0.5rem;">
                <option value="">Selecciona club...</option>
                <?php foreach ($clubes_responsable as $cr): ?>
                    <option value="<?= $cr['id_club'] ?>"><?= htmlspecialchars($cr['club_nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <script>
        function toggleClubContext(isClub) {
            document.getElementById('id_club_reserva').style.display = isClub ? 'block' : 'none';
        }
        </script>
    <?php else: ?>
        <small style="color:#888;">ℹ️ Reservas disponibles solo a nivel personal.</small>
    <?php endif; ?>

    <div class="modal-reserva-inteligente-content">
        <h3 style="margin-top:0; color:#071289; border-bottom: 1px solid #eee; padding-bottom: 10px;">Confirmar Reserva</h3>
        
        <div style="margin: 1rem 0; font-size: 0.95rem; line-height: 1.5;">
            <p id="modalInfo"></p>
        </div>

        <!-- Selector de Duración (Se llena dinámicamente según deporte) -->
        <div id="opcionesDuracion" class="form-group" style="background:#f8f9fa; padding:15px; border-radius:12px; margin-bottom: 15px;">
            <label style="font-weight:bold; color:#333; display:block; margin-bottom:8px;">⏱️ Duración:</label>
            <div class="duration-options" id="durationContainer">
                <!-- Se inyecta via JS -->
            </div>
        </div>

        <!-- Sección Reserva Recurrente -->
        <div style="margin:1rem 0; padding-top:1rem; border-top:1px solid #eee;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                <input type="checkbox" id="isRecurrentSocio" style="width:18px; height:18px;" onchange="toggleRecurrentFieldsSocio(this.checked)">
                <label for="isRecurrentSocio" style="font-weight:600; color:#333; cursor:pointer;">🔄 Crear reserva recurrente</label>
            </div>
            
            <div id="recurrentFieldsSocio" style="display:none; background:#F7FAFC; padding:1rem; border-radius:10px; border:1px solid #E2E8F0;">
                <div class="form-group">
                    <label style="font-size:0.9rem; font-weight:600; color:#333;">Repetir cada:</label>
                    <select id="repeatDaySocio" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;" onchange="calcularRecurrencia()">
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
                        <input type="date" id="startDateSocio" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;" onchange="calcularRecurrencia()">
                    </div>
                    <div class="form-group">
                        <label style="font-size:0.9rem; font-weight:600; color:#333;">Fecha fin *</label>
                        <input type="date" id="endDateSocio" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; margin-top:0.3rem;" onchange="calcularRecurrencia()">
                    </div>
                </div>

                <!-- Ficha de Resumen de Recurrencia -->
                <div id="recapRecurrencia" class="recap-card" style="display:none;">
                    <div class="recap-row">
                        <span>Reservas generadas:</span>
                        <strong id="countReservas">0</strong>
                    </div>
                    <div class="recap-row">
                        <span>Valor unitario:</span>
                        <span id="valUnitario">$0</span>
                    </div>
                    <div class="recap-total">
                        <span>Total Estimado:</span>
                        <span id="totalEstimado">$0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen Precio Final (Para reserva simple o total recurrente) -->
        <div style="display:flex; justify-content:space-between; align-items:center; background:#E8F5E9; padding:10px 15px; border-radius:8px; border-left: 4px solid #4CAF50; margin-bottom: 15px;">
            <div>
                <span style="font-weight:600; color:#2E7D32; display:block; font-size:0.9rem;" id="labelPrecioFinal">Total a Pagar:</span>
            </div>
            <div id="precioDisplay" style="font-size:1.5rem; font-weight:bold; color:#2E7D32;">$0</div>
        </div>

        <button onclick="confirmarReservaInteligente()" class="btn-primary">✅ Confirmar Reserva</button>
        <button onclick="cerrarModalReserva()" class="btn-secondary">Cancelar</button>
    </div>
</div>

<!-- Sistema de Toast Notifications -->
<div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

<script>
    let reservaActual = null;
    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    const iconosDeporte = { 'padel':'🎾', 'tenis':'🎾', 'futbol':'⚽', 'default':'🏟️' };
    
    const userData = <?= json_encode([
        'club_fav' => $usuario_data['club_favorito'],
        'deporte_fav' => $usuario_data['deporte_favorito']
    ]) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('filtroFecha').value = fechaPlanillaActual;
        if (userData.club_fav) document.getElementById('filtroRecinto').value = userData.club_fav;
        if (userData.deporte_fav) document.getElementById('filtroDeporte').value = userData.deporte_fav;
        aplicarFiltros();
        const hoy = new Date();
        const yyyy = hoy.getFullYear();
        const mm = String(hoy.getMonth() + 1).padStart(2, '0');
        const dd = String(hoy.getDate()).padStart(2, '0');
        document.getElementById('filtroFecha').min = `${yyyy}-${mm}-${dd}`;
    });

    async function aplicarFiltros(esBusquedaManual = false) {
        const deporte = document.getElementById('filtroDeporte').value;
        const recinto = document.getElementById('filtroRecinto').value;
        fechaPlanillaActual = document.getElementById('filtroFecha').value || new Date().toISOString().split('T')[0];

        document.getElementById('tablaBody').innerHTML = '<tr><td colspan="100%" style="padding:2rem; text-align:center;">Cargando...</td></tr>';
        document.getElementById('tablaHeader').innerHTML = '<th>Hora</th>';

        try {
            const formData = new FormData();
            formData.append('deporte', deporte);
            formData.append('recinto', recinto);
            formData.append('fecha', fechaPlanillaActual);
            formData.append('id_socio', <?= $id_socio ?>);

            const res = await fetch('../api/reservas_club.php?action=get_disponibilidad', { method: 'POST', body: formData, credentials: 'include' });
            
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await res.text();
                console.error('❌ API devolvió HTML:', text.substring(0, 200));
                throw new Error('Respuesta inválida del servidor');
            }
            
            const data = await res.json();
            if(data.error) throw new Error(data.error);
            
            renderizarPlanillaSocio(data);

            if (esBusquedaManual && (!userData.club_fav || !userData.deporte_fav) && (deporte && recinto)) {
                setTimeout(() => {
                    if(confirm(`¿Guardar ${document.getElementById('filtroRecinto').options[document.getElementById('filtroRecinto').selectedIndex].text} y ${document.getElementById('filtroDeporte').options[document.getElementById('filtroDeporte').selectedIndex].text} como favoritos?`)) {
                        guardarFavoritos(recinto, deporte);
                    }
                }, 500);
            }
        } catch (error) {
            console.error('❌ Error cargando disponibilidad:', error);
            document.getElementById('tablaBody').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red;">Error: ${error.message}<br><button onclick="aplicarFiltros()" style="margin-top:10px;padding:5px 10px;background:#4CAF50;color:white;border:none;border-radius:4px;cursor:pointer;">Reintentar</button></td></tr>`;
        }
    }

    function renderizarPlanillaSocio(data) {
        const thead = document.getElementById('tablaHeader');
        const tbody = document.getElementById('tablaBody');
        
        if (!data || !data.canchas || data.canchas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="100%" style="padding:2rem; text-align:center;">No hay canchas disponibles.</td></tr>';
            thead.innerHTML = ''; 
            return;
        }

        // === HEADER ===
        let htmlHead = '<th>Hora</th>';
        data.canchas.forEach(c => {
            htmlHead += `<th>${iconosDeporte[c.id_deporte] || iconosDeporte['default']}<br><span style="font-weight:normal; font-size:0.7rem;">${c.nombre_cancha}</span></th>`;
        });
        thead.innerHTML = htmlHead;

        // === VALIDACIÓN ROBUSTA DE FECHA PASADA ===
        const ahora = new Date();
        const fechaSeleccionada = document.getElementById('filtroFecha').value;
        
        // Construir fecha local en formato YYYY-MM-DD para evitar desfases UTC
        const yyyy = ahora.getFullYear();
        const mm = String(ahora.getMonth() + 1).padStart(2, '0');
        const dd = String(ahora.getDate()).padStart(2, '0');
        const hoyStr = `${yyyy}-${mm}-${dd}`;
        
        const esHoy = (fechaSeleccionada === hoyStr);
        const esPasado = (fechaSeleccionada < hoyStr);
        const horaActualMinutos = (ahora.getHours() * 60) + ahora.getMinutes();

        // 🔒 BLOQUEO TOTAL SI LA FECHA ES ANTERIOR A HOY
        if (esPasado) {
            tbody.innerHTML = `<tr><td colspan="100%" style="padding:3rem; text-align:center; color:#888; background:rgba(255,255,255,0.9); border-radius:12px;">
                <div style="font-size:3rem; margin-bottom:1rem;">🕒</div>
                <h3 style="color:#333;">No se pueden realizar reservas en fechas pasadas</h3>
                <p>Por favor selecciona una fecha futura para ver la disponibilidad.</p>
            </td></tr>`;
            return;
        }

        // === CUERPO DE LA PLANILLA ===
        let htmlBody = '';
        let horaActual = 7 * 60; 
        const finDia = 23 * 60; 
        let skipCells = {};

        while (horaActual < finDia) {
            const h = Math.floor(horaActual / 60);
            const m = horaActual % 60;
            const timeLabel = `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
            const esMedia = (m === 30);
            
            htmlBody += `<tr><td style="${esMedia ? 'opacity:0.5; font-size:0.7rem;' : ''}">${esMedia ? '' : timeLabel}</td>`;
            
            data.canchas.forEach((c, idx) => {
                // Manejo de rowspan para reservas existentes
                if (skipCells[idx] && skipCells[idx] > 0) { 
                    skipCells[idx]--; 
                    return; 
                }
                
                const res = data.reservas.find(r => r.id_cancha == c.id_cancha && r.hora_inicio.substring(0,5) === timeLabel);
                
                if (res) {
                    // CELDA OCUPADA
                    const duracion = ((parseInt(res.hora_fin.substring(0,2))*60 + parseInt(res.hora_fin.substring(3,5))) - horaActual) / 30;
                    const rowspan = Math.max(1, Math.round(duracion));
                    if (rowspan > 1) skipCells[idx] = rowspan - 1;
                    
                    htmlBody += `<td class="estado-ocupado" rowspan="${rowspan}" style="height:${rowspan*40}px;">
                        <div style="font-weight:bold;">${res.hora_inicio.substring(0,5)} - ${res.hora_fin.substring(0,5)}</div>
                        <div style="font-size:0.65rem; opacity:0.9;">Ocupado</div>
                    </td>`;
                } else {
                    // CELDA DISPONIBLE O PASADA (DENTRO DEL DÍA ACTUAL)
                    let claseExtra = 'estado-disponible';
                    let onclickAction = `seleccionarSlot("${c.id_cancha}", "${timeLabel}", "${c.nro_cancha}", "${c.recinto_nombre}", "${c.id_deporte}", "${c.valor_arriendo}")`;
                    
                    // ⏱️ BLOQUEO DE HORAS VENCIDAS EN EL DÍA ACTUAL
                    if (esHoy && horaActual <= horaActualMinutos) {
                        claseExtra = 'slot-pasado';
                        onclickAction = ''; // Bloquear click
                    }

                    htmlBody += `<td class="${claseExtra}" onclick='${onclickAction}'></td>`;
                }
            });
            
            htmlBody += `</tr>`; 
            horaActual += 30;
        }
        
        tbody.innerHTML = htmlBody;
    }

    function guardarFavoritos(club, deporte) {
        const f = new FormData(); f.append('action', 'save_favorites'); f.append('club_favorito', club); f.append('deporte_favorito', deporte);
        fetch('reservar_cancha.php', { method: 'POST', body: f }).then(() => showToast('✅ Favoritos guardados', 'success'));
    }

    function cambiarDia(dias) { const f = new Date(fechaPlanillaActual); f.setDate(f.getDate()+dias); fechaPlanillaActual = f.toISOString().split('T')[0]; document.getElementById('filtroFecha').value = fechaPlanillaActual; aplicarFiltros(); }
    function irAHoy() { fechaPlanillaActual = new Date().toISOString().split('T')[0]; document.getElementById('filtroFecha').value = fechaPlanillaActual; aplicarFiltros(); }

    // === LÓGICA DE SELECCIÓN Y DURACIÓN ===

    function seleccionarSlot(id, hora, nro, recinto, deporte, valor) {
        // Guardamos el deporte seleccionado para determinar opciones de duración
        reservaActual = { 
            id_cancha: id, 
            nro_cancha: nro, 
            recinto_nombre: recinto, 
            id_deporte: deporte, 
            valor_arriendo: valor, 
            fecha: fechaPlanillaActual, 
            hora_inicio: hora 
        };

        document.getElementById('modalInfo').innerHTML = `
            <strong>📍 Cancha:</strong> ${nro} (${recinto})<br>
            <strong>📅 Fecha:</strong> ${fechaPlanillaActual}<br>
            <strong>⏰ Hora Inicio:</strong> ${hora}
        `;

        // 1. Configurar Opciones de Duración según Deporte
        configurarOpcionesDuracion(deporte, valor);

        // 2. Resetear campos recurrentes
        document.getElementById('isRecurrentSocio').checked = false;
        document.getElementById('recurrentFieldsSocio').style.display = 'none';
        document.getElementById('recapRecurrencia').style.display = 'none';
        
        // 3. Calcular precio inicial (simple)
        actualizarPrecioSimple();
        
        document.getElementById('modalReservaInteligente').style.display = 'flex';
    }

    function configurarOpcionesDuracion(deporte, valorBase) {
        const container = document.getElementById('durationContainer');
        container.innerHTML = ''; // Limpiar

        // Definir opciones según deporte
        let opciones = [];
        if (deporte === 'padel') {
            // Pádel: 60, 90, 120 min
            opciones = [
                { val: 60, label: '60m', factor: 1 },
                { val: 90, label: '90m', factor: 1.5 },
                { val: 120, label: '120m', factor: 2 }
            ];
        } else {
            // Otros deportes: Solo 60 min (bloqueo visual o lógico)
            opciones = [
                { val: 60, label: '60m', factor: 1 }
            ];
        }

        // Generar HTML
        opciones.forEach((op, index) => {
            const isChecked = index === 0 ? 'checked' : '';
            const html = `
                <label class="duration-label">
                    <input type="radio" name="duracion" value="${op.val}" ${isChecked} onchange="actualizarPrecioModal(${op.val})"> ${op.label}
                </label>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });
    }

    function actualizarPrecioModal(minutos) {
        if (!reservaActual) return;
        
        // Si está activo el modo recurrente, recalculamos todo el bloque recurrente
        if (document.getElementById('isRecurrentSocio').checked) {
            calcularRecurrencia();
        } else {
            actualizarPrecioSimple();
        }
    }

    function actualizarPrecioSimple() {
        if (!reservaActual) return;
        const duracion = parseInt(document.querySelector('input[name="duracion"]:checked')?.value || 60);
        
        let factor = 1;
        if (duracion == 90) factor = 1.5;
        else if (duracion == 120) factor = 2;
        
        const total = Math.round(parseFloat(reservaActual.valor_arriendo) * factor);
        
        document.getElementById('precioDisplay').textContent = '$' + total.toLocaleString('es-CL');
        document.getElementById('labelPrecioFinal').textContent = 'Total a Pagar:';
    }

    // === LÓGICA RECURRENTE ===

    function toggleRecurrentFieldsSocio(mostrar) {
        const fields = document.getElementById('recurrentFieldsSocio');
        fields.style.display = mostrar ? 'block' : 'none';
        
        if (mostrar) {
            // Pre-seleccionar fechas lógicas (ej: desde hoy hasta 1 mes)
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('startDateSocio').value = hoy;
            
            const proximoMes = new Date();
            proximoMes.setMonth(proximoMes.getMonth() + 1);
            document.getElementById('endDateSocio').value = proximoMes.toISOString().split('T')[0];
            
            calcularRecurrencia();
        } else {
            document.getElementById('recapRecurrencia').style.display = 'none';
            actualizarPrecioSimple(); // Volver a precio simple
        }
    }

    function calcularRecurrencia() {
        if (!reservaActual) return;

        const startStr = document.getElementById('startDateSocio').value;
        const endStr = document.getElementById('endDateSocio').value;
        const dayOfWeek = parseInt(document.getElementById('repeatDaySocio').value);
        const duracion = parseInt(document.querySelector('input[name="duracion"]:checked')?.value || 60);

        if (!startStr || !endStr) return;

        // 1. Calcular cantidad de fechas
        const fechas = generarFechasRecurrencia(startStr, endStr, dayOfWeek);
        const cantidad = fechas.length;

        // 2. Calcular valores
        let factor = 1;
        if (duracion == 90) factor = 1.5;
        else if (duracion == 120) factor = 2;
        
        const valorUnitario = Math.round(parseFloat(reservaActual.valor_arriendo) * factor);
        const totalEstimado = valorUnitario * cantidad;

        // 3. Actualizar UI Ficha
        document.getElementById('countReservas').textContent = cantidad;
        document.getElementById('valUnitario').textContent = `$${valorUnitario.toLocaleString('es-CL')} (${duracion} min)`;
        document.getElementById('totalEstimado').textContent = `$${totalEstimado.toLocaleString('es-CL')}`;
        
        document.getElementById('recapRecurrencia').style.display = 'block';
        
        // Actualizar el precio final grande
        document.getElementById('precioDisplay').textContent = `$${totalEstimado.toLocaleString('es-CL')}`;
        document.getElementById('labelPrecioFinal').textContent = `Total Estimado (${cantidad} reservas):`;
    }

    function generarFechasRecurrencia(start, end, dayOfWeek) {
        const dates = [];
        let current = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');

        while (current <= endDate) {
            // getDay(): 0=Domingo, 1=Lunes...
            if (current.getDay() === dayOfWeek) {
                dates.push(current.toISOString().split('T')[0]);
            }
            current.setDate(current.getDate() + 1);
        }
        return dates;
    }

    // === CONFIRMACIÓN MEJORADA ===
    async function confirmarReservaInteligente() {
        if (!reservaActual) {
            showToast('❌ No hay reserva seleccionada', 'error');
            return;
        }

        const isRecurrent = document.getElementById('isRecurrentSocio').checked;
        const duracion = parseInt(document.querySelector('input[name="duracion"]:checked')?.value || 60);
        
        // Calcular hora fin
        const [h, m] = reservaActual.hora_inicio.split(':').map(Number);
        const finDate = new Date();
        finDate.setHours(h, m + duracion, 0, 0);
        const horaFinStr = `${String(finDate.getHours()).padStart(2,'0')}:${String(finDate.getMinutes()).padStart(2,'0')}`;

        // Calcular monto unitario
        let factor = 1;
        if (duracion == 90) factor = 1.5;
        else if (duracion == 120) factor = 2;
        const montoUnitario = Math.round(parseFloat(reservaActual.valor_arriendo) * factor);

        // ✅ DEFINIR VARIABLES DE CONTEXTO (ESTO FALTABA)
        const tipoReserva = document.querySelector('input[name="tipo_reserva"]:checked')?.value || 'individual';
        const idClubReserva = document.getElementById('id_club_reserva')?.value || null;

        try {
            if (isRecurrent) {
                // --- FLUJO RECURRENTE ---
                const day = parseInt(document.getElementById('repeatDaySocio').value);
                const sDate = document.getElementById('startDateSocio').value;
                const eDate = document.getElementById('endDateSocio').value;

                if (!day || !sDate || !eDate) {
                    showToast('❌ Complete día de repetición y fechas', 'error');
                    return;
                }

                const payload = {
                    action: 'create_recurrent',
                    id_cancha: reservaActual.id_cancha,
                    hora_inicio: reservaActual.hora_inicio,
                    id_socio: <?= $id_socio ?>,
                    repeat_day: day,
                    start_date: sDate,
                    end_date: eDate,
                    monto_total: montoUnitario,
                    duracion_bloque: duracion,
                    tipo_reserva: tipoReserva,
                    id_club_reserva: idClubReserva
                };

                const res = await fetch('../api/reserva_recurrente.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    showToast(`✅ ${data.created} reservas creadas exitosamente`, 'success');
                    cerrarModalReserva();
                    location.reload();
                } else {
                    showToast('❌ ' + (data.message || 'Error al crear reservas'), 'error');
                }

            } else {
                // --- FLUJO SIMPLE ---
                const payload = {
                    id_cancha: reservaActual.id_cancha,
                    fecha: reservaActual.fecha, 
                    hora_inicio: reservaActual.hora_inicio,
                    hora_fin: horaFinStr,
                    id_socio: <?= $id_socio ?>,
                    monto_total: montoUnitario,
                    duracion_bloque: duracion,
                    // ✅ ENVÍO DE CONTEXTO
                    tipo_reserva: tipoReserva,
                    id_club_reserva: idClubReserva
                };

                console.log("🚀 Enviando a reserva_unica.php:", payload);

                const res = await fetch('../api/reserva_unica.php', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success) {
                    // ✅ EN LUGAR DE RELOAD, REDIRIGIMOS CON ESTADO
                    window.location.href = window.location.pathname + '?reserva_ok=1';
                } else {
                    showToast('❌ ' + (data.message || 'Error al crear reserva'), 'error');
                }
            }
        } catch (error) {
            console.error(error);
            showToast('❌ Error de conexión: ' + error.message, 'error');
        }
    }

    function cerrarModalReserva() { 
        document.getElementById('modalReservaInteligente').style.display = 'none'; 
    }

    function showToast(msg, type) {
        const container = document.getElementById('toast-container');
        if (!container) return alert(msg);
        const t = document.createElement('div'); 
        t.className = `toast ${type}`; 
        t.textContent = msg; 
        container.appendChild(t);
        setTimeout(() => t.classList.add('show'), 100); 
        setTimeout(() => { t.classList.remove('show'); setTimeout(()=>t.remove(), 300); }, 3000);
    }
    function cambiarDia(dias) { 
        const f = new Date(fechaPlanillaActual); 
        f.setDate(f.getDate() + dias); 
        
        // Validar que no sea anterior a hoy
        const hoy = new Date();
        hoy.setHours(0,0,0,0);
        if (f < hoy) {
            showToast('⚠️ No puedes seleccionar fechas anteriores a hoy', 'error');
            return; 
        }
        
        fechaPlanillaActual = f.toISOString().split('T')[0]; 
        document.getElementById('filtroFecha').value = fechaPlanillaActual; 
        aplicarFiltros(); 
    }
</script>
<?php if (isset($_GET['reserva_ok'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                showToast('✅ ¡Reserva creada correctamente!', 'success');
                
                // Limpiar la URL para que no se muestre de nuevo al recargar
                const url = new URL(window.location);
                url.searchParams.delete('reserva_ok');
                window.history.replaceState({}, document.title, url);
            }, 800); // Pequeña pausa para que cargue el UI
        });
    </script>
<?php endif; ?>
</body>
</html>