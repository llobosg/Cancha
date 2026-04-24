<?php
// pages/reservar_cancha.php
require_once __DIR__ . '/../includes/config.php';

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_name('CANCHASPORT_SESSION');
    session_start();
}

if (!isset($_SESSION['id_socio'])) {
    header('Location: ../index.php');
    exit;
}

$id_socio = (int)$_SESSION['id_socio'];
$stmt = $pdo->prepare("SELECT id_socio, nombre, alias, email, celular FROM socios WHERE id_socio = ?");
$stmt->execute([$id_socio]);
$usuario_data = $stmt->fetch();

if (!$usuario_data) {
    header('Location: ../index.php');
    exit;
}

// Obtener Recintos
$stmt_recintos = $pdo->prepare("SELECT id_recinto, nombre FROM recintos_deportivos WHERE email_verified = 1 ORDER BY nombre");
$stmt_recintos->execute();
$recintos = $stmt_recintos->fetchAll();

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
    
    /* Header */
    .header {
        background: rgba(0, 51, 102, 0.95); backdrop-filter: blur(10px);
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.8rem 1.5rem; position: sticky; top: 0; z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .brand-logo { color: #FFD700; font-weight: 900; font-size: 1.3rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    
    /* Contenedor Principal */
    .main-container { max-width: 98%; margin: 1rem auto; padding: 0 1rem; }
    
    /* Filtros Externos (Deporte/Recinto) */
    .controls-section {
        display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;
        padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 12px;
        backdrop-filter: blur(5px); align-items: center;
    }
    .control-select {
        background: white; padding: 0.5rem; border-radius: 6px; color: #071289; border: none; font-weight: bold; min-width: 150px;
    }
    
    /* Planilla de Reservas (Tabla) */
    .planilla-wrapper {
        background: white; border-radius: 12px; overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2); color: #333;
    }
    .planilla-scroll { overflow-x: auto; max-height: 75vh; }
    
    /* === ESTILOS DE TABLA REFINADOS === */
    .planilla-table {
        width: 100%; border-collapse: separate; border-spacing: 0; table-layout: fixed;
        background: white;
    }
    
    /* Headers de Canchas */
    .planilla-table thead th {
        background: #f8f9fa !important; color: #333; position: sticky; top: 0; z-index: 5;
        border-bottom: 2px solid #AB47BC; border-right: 1px solid #eee;
        height: 60px; font-size: 0.8rem; font-weight: 600; vertical-align: middle;
        padding: 5px;
    }

    /* Celdas Generales (Líneas tenues) */
    .planilla-table td {
        padding: 0; vertical-align: middle; text-align: center;
        border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s ease; height: 40px; /* Altura base por slot de 30min */
    }

    /* Hora Sticky (Sin Bold, Líneas Suaves) */
    .planilla-table th:first-child,
    .planilla-table td:first-child {
        position: sticky; left: 0; z-index: 20;
        background: #fff !important; color: #555; font-weight: normal;
        border-right: 1px solid #eee; border-bottom: 1px solid #eee;
        width: 60px !important; min-width: 60px !important; max-width: 60px !important;
        padding: 4px !important; font-size: 0.8rem; text-align: center;
    }
    
    /* Estados Visuales */
    td.estado-disponible { 
        background-color: #ffffff !important; cursor: pointer;
    }
    td.estado-disponible:hover { background-color: #f9fbe7 !important; } 

    td.estado-ocupado { 
        background-color: #FF5252 !important; color: white !important;
        font-size: 0.75rem; line-height: 1.2;
    }

    /* Controles en Header de Tabla (Fecha) - CORREGIDO */
    .table-header-controls {
        display: flex; 
        align-items: center; 
        justify-content: flex-start; /* Alineación izquierda pero con padding */
        gap: 6px; 
        width: 100%;
        padding-left: 10px; /* MOVER 10 CARACTERES A LA DERECHA */
    }
    .date-nav-btn {
        background: white; 
        border: 1px solid #ddd; 
        border-radius: 4px;
        color: #555; 
        cursor: pointer; 
        padding: 4px 8px; 
        font-size: 0.9rem;
        line-height: 1;
        min-width: 30px;
    }
    .date-nav-btn:hover { background: #f0f0f0; border-color: #ccc; }
    
    .input-fecha-header {
        border: 1px solid #ddd; 
        border-radius: 4px; 
        padding: 4px;
        font-family: inherit; 
        font-size: 0.8rem; 
        color: #333; 
        width: 120px; /* Ancho fijo suficiente */
        text-align: center;
        background: white;
    }
    
    /* Modal */
    .modal-reserva-inteligente {
        display: none; position: fixed; z-index: 2000; left: 0; top: 0;
        width: 100%; height: 100%; background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px); justify-content: center; align-items: center;
    }
    .modal-reserva-inteligente-content {
        background-color: white; padding: 2rem; border-radius: 16px;
        width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    /* Botones y Form */
    .btn-primary { background: #071289; color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 1rem; }
    .btn-secondary { background: #ccc; color: #333; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 0.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #333; }
    
    /* Toast */
    .toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; z-index: 3000; transform: translateX(120%); transition: transform 0.3s; }
    .toast.show { transform: translateX(0); }
    .toast.success { background: #4CAF50; }
    .toast.error { background: #F44336; }
</style>
</head>
<body>

<div class="header">
    <div class="brand-logo">🎾⚽ Reservar Cancha</div>
    <a href="dashboard_socio.php" style="color: white; text-decoration: none;">← Volver</a>
</div>

<div class="main-container">
    <!-- Filtros Externos -->
    <div class="controls-section">
        <select class="control-select" id="filtroDeporte">
            <option value="">Todos los deportes</option>
            <?php foreach ($deportes as $key => $value): ?>
                <option value="<?= $key ?>"><?= $value ?></option>
            <?php endforeach; ?>
        </select>
        
        <select class="control-select" id="filtroRecinto">
            <option value="">Todos los recintos</option>
            <?php foreach ($recintos as $recinto): ?>
                <option value="<?= $recinto['id_recinto'] ?>"><?= htmlspecialchars($recinto['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <button onclick="aplicarFiltros()" style="background:#4ECDC4; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; font-weight:bold; color:#071289;">🔍 Actualizar</button>
    </div>

    <!-- Planilla -->
    <div class="planilla-wrapper">
        <div class="planilla-scroll">
            <table class="planilla-table" id="tablaReservas">
                <thead>
                    <tr id="tablaHeader">
                        <!-- Header se llena con JS incluyendo controles de fecha -->
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr><td colspan="100%" style="padding:2rem; text-align:center;">Cargando disponibilidad...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Reserva Inteligente -->
<div id="modalReservaInteligente" class="modal-reserva-inteligente">
    <div class="modal-reserva-inteligente-content">
        <h3 style="margin-top:0; color:#071289;">Confirmar Reserva</h3>
        <p id="modalInfo" style="margin-bottom:1rem; color:#555;"></p>
        
        <div id="opcionesDuracion" class="form-group" style="background:#f0f4f8; padding:10px; border-radius:6px;">
            <label>⏱️ Duración:</label>
            <div style="display:flex; gap:15px;">
                <label><input type="radio" name="duracion" value="60" onchange="actualizarPrecioModal(60)"> 60 min</label>
                <label><input type="radio" name="duracion" value="90" checked onchange="actualizarPrecioModal(90)"> 90 min</label>
            </div>
        </div>

        <div class="form-group">
            <label>Valor Estimado:</label>
            <div id="precioDisplay" style="font-size:1.5rem; font-weight:bold; color:#2E7D32;">$0</div>
        </div>

        <button onclick="confirmarReservaInteligente()" class="btn-primary">✅ Confirmar Reserva</button>
        <button onclick="cerrarModalReserva()" class="btn-secondary">Cancelar</button>
    </div>
</div>

<script>
    let reservaActual = null;
    let fechaPlanillaActual = new Date().toISOString().split('T')[0];
    let iconosDeporte = { 'padel':'🎾', 'tenis':'🎾', 'futbol':'⚽', 'default':'🏟️' };

    // Inicialización
    document.addEventListener('DOMContentLoaded', () => {
        aplicarFiltros();
    });

    async function aplicarFiltros() {
        const deporte = document.getElementById('filtroDeporte').value;
        const recinto = document.getElementById('filtroRecinto').value;
        
        console.log("Aplicando filtros:", { deporte, recinto, fecha: fechaPlanillaActual });

        document.getElementById('tablaBody').innerHTML = '<tr><td colspan="100%" style="padding:2rem; text-align:center;">Cargando...</td></tr>';
        document.getElementById('tablaHeader').innerHTML = '<th>Hora</th>'; // Reset temporal

        try {
            const formData = new FormData();
            formData.append('deporte', deporte);
            formData.append('recinto', recinto);
            formData.append('fecha', fechaPlanillaActual);
            formData.append('id_socio', <?= $id_socio ?>);

            const res = await fetch('../api/reservas_club.php?action=get_disponibilidad', {
                method: 'POST', body: formData, credentials: 'include'
            });
            
            if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
            
            const data = await res.json();
            console.log("Datos recibidos de API:", data); // DEBUG CRÍTICO
            
            if(data.error) throw new Error(data.error);
            
            renderizarPlanillaSocio(data);
        } catch (error) {
            console.error(error);
            document.getElementById('tablaBody').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red;">Error: ${error.message}</td></tr>`;
        }
    }

        // === RENDERIZADO CON FILAS EXPLÍCITAS DE 30 MINUTOS (CORREGIDO) ===
    function renderizarPlanillaSocio(data) {
        const thead = document.getElementById('tablaHeader');
        const tbody = document.getElementById('tablaBody');
        
        if (!data || !data.canchas || !Array.isArray(data.canchas)) {
            tbody.innerHTML = '<tr><td colspan="100%" style="padding:2rem; color:red;">Error al cargar datos.</td></tr>';
            thead.innerHTML = '';
            return;
        }

        const canchas = data.canchas;
        const reservas = Array.isArray(data.reservas) ? data.reservas : [];

        if (canchas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="100%" style="padding:2rem;">No hay canchas disponibles.</td></tr>';
            thead.innerHTML = '';
            return;
        }

        // 1. Header
        let htmlHead = `<th style="background:#f8f9fa; position:sticky; left:0; z-index:11; border-right:1px solid #eee; height:60px; vertical-align:middle;">
            <div class="table-header-controls">
                <button class="date-nav-btn" onclick="cambiarDia(-1)">&lt;</button>
                <input type="date" class="input-fecha-header" value="${fechaPlanillaActual}" onchange="actualizarFechaInput(this.value)">
                <button class="date-nav-btn" onclick="cambiarDia(1)">&gt;</button>
                <button class="date-nav-btn" style="font-size:0.7rem; padding:2px 4px;" onclick="irAHoy()">Hoy</button>
            </div>
        </th>`;
        
        canchas.forEach(c => {
            const icono = iconosDeporte[c.id_deporte] || iconosDeporte['default'];
            htmlHead += `<th>${icono}<br><span style="font-weight:normal; font-size:0.7rem;">${c.nro_cancha}</span></th>`;
        });
        thead.innerHTML = htmlHead;

        // 2. Cuerpo: UNA FILA EXPLÍCITA POR CADA 30 MINUTOS
        let htmlBody = '';
        let horaActualMinutos = 7 * 60; // 07:00
        const finDiaMinutos = 23 * 60;  // 23:00
        let skipCells = {}; 

        while (horaActualMinutos < finDiaMinutos) {
            const h = Math.floor(horaActualMinutos / 60);
            const m = horaActualMinutos % 60;
            const timeLabel = `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
            
            // Iniciar fila con atributo data para depuración
            htmlBody += `<tr data-slot="${timeLabel}">`;
            
            // Primera columna: HORA SIEMPRE VISIBLE
            // Negrita y azul oscuro para :00, gris suave para :30
            htmlBody += `<td style="background:#f8f9fa; font-weight:${m===0?'bold':'normal'}; color:#555; border-right:1px solid #eee; border-bottom:1px solid #eee; position:sticky; left:0; z-index:5; width:60px; text-align:center; font-size:0.85rem; padding:4px;">
                            ${timeLabel}
                         </td>`;

            canchas.forEach((cancha, indexCancha) => {
                // Verificar si esta celda está saltada por un rowspan anterior
                if (skipCells[indexCancha] && skipCells[indexCancha] > 0) {
                    skipCells[indexCancha]--;
                    return; // No renderizar celda, el rowspan de arriba la ocupa
                }

                // Buscar reserva que EMPIECE exactamente en este slot
                const reservaInicio = reservas.find(r => {
                    if (r.id_cancha != cancha.id_cancha) return false;
                    if (!r.hora_inicio) return false;
                    return String(r.hora_inicio).trim().substring(0,5) === timeLabel;
                });

                if (reservaInicio) {
                    // Calcular duración exacta en minutos
                    const hIni = parseInt(reservaInicio.hora_inicio.substring(0,2)) * 60 + parseInt(reservaInicio.hora_inicio.substring(3,5));
                    const hFin = parseInt(reservaInicio.hora_fin.substring(0,2)) * 60 + parseInt(reservaInicio.hora_fin.substring(3,5));
                    const duracionMinutos = hFin - hIni;
                    
                    // Rowspan: Duración / 30. Mínimo 1.
                    const rowspan = Math.max(1, Math.round(duracionMinutos / 30));
                    
                    if (rowspan > 1) skipCells[indexCancha] = rowspan - 1;

                    htmlBody += `<td class="estado-ocupado" rowspan="${rowspan}" style="height:${rowspan * 40}px; vertical-align:middle;">
                        <div style="font-weight:bold; padding:2px 0;">${reservaInicio.hora_inicio.substring(0,5)} - ${reservaInicio.hora_fin.substring(0,5)}</div>
                        <div style="font-size:0.65rem; opacity:0.9;">Ocupado</div>
                    </td>`;
                } else {
                    // Disponible: Pasar timeLabel EXACTO al modal
                    htmlBody += `<td class="estado-disponible" onclick='seleccionarSlot("${cancha.id_cancha}", "${timeLabel}", "${cancha.nro_cancha}", "${cancha.recinto_nombre}", "${cancha.id_deporte}", "${cancha.valor_arriendo}")'></td>`;
                }
            });
            
            htmlBody += `</tr>`;
            horaActualMinutos += 30; // Avanzar estrictamente 30 min
        }
        
        tbody.innerHTML = htmlBody;
    }
    
    // Funciones de Navegación de Fecha
    function cambiarDia(dias) {
        const fechaObj = new Date(fechaPlanillaActual);
        fechaObj.setDate(fechaObj.getDate() + dias);
        fechaPlanillaActual = fechaObj.toISOString().split('T')[0];
        aplicarFiltros();
    }
    
    function irAHoy() {
        fechaPlanillaActual = new Date().toISOString().split('T')[0];
        const inputs = document.querySelectorAll('.input-fecha-header');
        inputs.forEach(input => input.value = fechaPlanillaActual);
        aplicarFiltros();
    }
    
    function actualizarFechaInput(val) {
        fechaPlanillaActual = val;
        aplicarFiltros();
    }

    function seleccionarSlot(idCancha, hora, nroCancha, recinto, deporte, valor) {
        console.log(`🖱️ Click en celda: Cancha=${nroCancha}, Hora recibida=${hora}, Fecha=${fechaPlanillaActual}`);
        
        reservaActual = {
            id_cancha: idCancha,
            nro_cancha: nroCancha,
            recinto_nombre: recinto,
            id_deporte: deporte,
            valor_arriendo: valor,
            fecha: fechaPlanillaActual,
            hora_inicio: hora // Esta es la hora que se pasó desde el renderizado
        };

        const modal = document.getElementById('modalReservaInteligente');
        document.getElementById('modalInfo').innerHTML = `
            <strong>Cancha:</strong> ${nroCancha} (${recinto})<br>
            <strong>Fecha:</strong> ${fechaPlanillaActual}<br>
            <strong>Hora Inicio:</strong> ${hora}
        `;
        
        const esPadel = (deporte === 'padel');
        document.getElementById('opcionesDuracion').style.display = esPadel ? 'block' : 'none';
        
        if(esPadel) {
            document.querySelector('input[name="duracion"][value="90"]').checked = true;
            actualizarPrecioModal(90);
        } else {
            document.querySelector('input[name="duracion"][value="60"]').checked = true;
            actualizarPrecioModal(60);
        }
        modal.style.display = 'flex';
    }

    function actualizarPrecioModal(minutos) {
        if(!reservaActual) return;
        const precioBase = parseFloat(reservaActual.valor_arriendo);
        const factor = minutos / 60;
        const total = precioBase * factor;
        document.getElementById('precioDisplay').textContent = '$' + Math.round(total).toLocaleString('es-CL');
    }

    function cerrarModalReserva() {
        document.getElementById('modalReservaInteligente').style.display = 'none';
    }

    function confirmarReservaInteligente() {
        if(!reservaActual) return;
        const duracion = parseInt(document.querySelector('input[name="duracion"]:checked').value);
        
        const [h, m] = reservaActual.hora_inicio.split(':').map(Number);
        const fechaInicio = new Date(`${reservaActual.fecha}T${reservaActual.hora_inicio}:00`);
        const fechaFin = new Date(fechaInicio.getTime() + duracion * 60000);
        const horaFinStr = fechaFin.toTimeString().substring(0,8);

        const datos = {
            id_cancha: reservaActual.id_cancha,
            fecha_base: reservaActual.fecha,
            hora_inicio: reservaActual.hora_inicio,
            hora_fin: horaFinStr,
            duracion_minutos: duracion,
            tipo_patron: 'simple',
            club_id: '<?= $_SESSION['club_id'] ?? "" ?>'
        };

        fetch('../api/crear_reserva_recurrente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(datos)
        })
        .then(r => r.json())
        .then(data => {
            cerrarModalReserva();
            if(data.success) {
                showToast('✅ Reserva creada con éxito', 'success');
                aplicarFiltros();
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(err => {
            cerrarModalReserva();
            showToast('❌ Error de conexión', 'error');
        });
    }

    function showToast(msg, type) {
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.classList.add('show'), 100);
        setTimeout(() => { t.classList.remove('show'); setTimeout(()=>t.remove(), 300); }, 3000);
    }
</script>
</body>
</html>