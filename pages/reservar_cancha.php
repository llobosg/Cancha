<?php
// pages/reservar_cancha.php
require_once __DIR__ . '/../includes/config.php';

// Manejo rápido de guardado de favoritos vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_favorites') {
    header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
    session_start();
}

if (!isset($_SESSION['id_socio'])) { header('Location: ../index.php'); exit; }

$id_socio = (int)$_SESSION['id_socio'];
$stmt = $pdo->prepare("SELECT id_socio, nombre, alias, email, celular, club_favorito, deporte_favorito FROM socios WHERE id_socio = ?");
$stmt->execute([$id_socio]);
$usuario_data = $stmt->fetch();

if (!$usuario_data) { header('Location: ../index.php'); exit; }

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
        min-width: 120px; font-size: 0.85rem;
    }
    .date-nav-btn {
        background: white; border: 1px solid #ddd; border-radius: 50%; width: 32px; height: 32px;
        color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center;
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
    
    .modal-reserva-inteligente { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
    .modal-reserva-inteligente-content { background-color: white; padding: 2rem; border-radius: 16px; width: 90%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .btn-primary { background: #071289; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 1rem; }
    .btn-secondary { background: #eee; color: #333; border: none; padding: 0.8rem; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 0.5rem; }
    .toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; z-index: 3000; transform: translateX(120%); transition: transform 0.3s; }
    .toast.show { transform: translateX(0); }
    .toast.success { background: #4CAF50; }
</style>
</head>
<body>
<div class="header">
    <div class="brand-logo">🎾 Reservar Cancha</div>
    <a href="dashboard_socio.php" style="color: white; text-decoration: none;">← Dashboard</a>
</div>

<div class="main-container">
    <div class="controls-section">
        <button class="date-nav-btn" onclick="cambiarDia(-1)">&lt;</button>
        <input type="date" id="filtroFecha" class="control-select" style="width: 135px;">
        <button class="date-nav-btn" onclick="cambiarDia(1)">&gt;</button>
        <button class="date-nav-btn" onclick="irAHoy()" style="width:auto; padding:0 12px; border-radius:20px; font-size:0.8rem; height:32px;">Hoy</button>
        
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

<!-- Modal -->
<div id="modalReservaInteligente" class="modal-reserva-inteligente">
    <div class="modal-reserva-inteligente-content">
        <h3 style="margin-top:0; color:#071289;">Confirmar Reserva</h3>
        <p id="modalInfo" style="margin-bottom:1rem; color:#555;"></p>
        <div id="opcionesDuracion" class="form-group" style="background:#f8f9fa; padding:10px; border-radius:8px;">
            <label style="font-weight:bold; color:#333;">⏱️ Duración:</label>
            <div style="display:flex; gap:15px; margin-top:5px;">
                <label style="color:#333; cursor:pointer;"><input type="radio" name="duracion" value="60" onchange="actualizarPrecioModal(60)"> 60 min</label>
                <label style="color:#333; cursor:pointer;"><input type="radio" name="duracion" value="90" checked onchange="actualizarPrecioModal(90)"> 90 min</label>
            </div>
        </div>
        <div style="margin-top:10px;">
            <strong style="color:#333;">Valor Estimado:</strong>
            <div id="precioDisplay" style="font-size:1.4rem; font-weight:bold; color:#2E7D32;">$0</div>
        </div>
        <button onclick="confirmarReservaInteligente()" class="btn-primary">✅ Confirmar Reserva</button>
        <button onclick="cerrarModalReserva()" class="btn-secondary">Cancelar</button>
    </div>
</div>

<!-- === SISTEMA DE TOAST NOTIFICATIONS === -->
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
            
            // Verificar respuesta JSON válida
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
            tbody.innerHTML = '<tr><td colspan="100%" style="padding:2rem;">No hay canchas disponibles.</td></tr>';
            thead.innerHTML = ''; return;
        }

        let htmlHead = '<th>Hora</th>';
        data.canchas.forEach(c => {
            htmlHead += `<th>${iconosDeporte[c.id_deporte] || iconosDeporte['default']}<br><span style="font-weight:normal; font-size:0.7rem;">${c.nombre_cancha}</span></th>`;
        });
        thead.innerHTML = htmlHead;

        let htmlBody = '';
        let horaActual = 7 * 60, finDia = 23 * 60, skipCells = {};

        while (horaActual < finDia) {
            const h = Math.floor(horaActual / 60), m = horaActual % 60;
            const timeLabel = `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
            const esMedia = (m === 30);
            
            htmlBody += `<tr><td style="${esMedia ? 'opacity:0.5; font-size:0.7rem;' : ''}">${esMedia ? '' : timeLabel}</td>`;

            data.canchas.forEach((c, idx) => {
                if (skipCells[idx] && skipCells[idx] > 0) { skipCells[idx]--; return; }
                const res = data.reservas.find(r => r.id_cancha == c.id_cancha && r.hora_inicio.substring(0,5) === timeLabel);

                if (res) {
                    const duracion = ((parseInt(res.hora_fin.substring(0,2))*60 + parseInt(res.hora_fin.substring(3,5))) - horaActual) / 30;
                    const rowspan = Math.max(1, Math.round(duracion));
                    if (rowspan > 1) skipCells[idx] = rowspan - 1;
                    htmlBody += `<td class="estado-ocupado" rowspan="${rowspan}" style="height:${rowspan*40}px;"><div style="font-weight:bold;">${res.hora_inicio.substring(0,5)} - ${res.hora_fin.substring(0,5)}</div><div style="font-size:0.65rem; opacity:0.9;">Ocupado</div></td>`;
                } else {
                    htmlBody += `<td class="estado-disponible" onclick='seleccionarSlot("${c.id_cancha}", "${timeLabel}", "${c.nro_cancha}", "${c.recinto_nombre}", "${c.id_deporte}", "${c.valor_arriendo}")'></td>`;
                }
            });
            htmlBody += `</tr>`; horaActual += 30;
        }
        tbody.innerHTML = htmlBody;
    }

    function guardarFavoritos(club, deporte) {
        const f = new FormData(); f.append('action', 'save_favorites'); f.append('club_favorito', club); f.append('deporte_favorito', deporte);
        fetch('reservar_cancha.php', { method: 'POST', body: f }).then(() => showToast('✅ Favoritos guardados', 'success'));
    }

    function cambiarDia(dias) { const f = new Date(fechaPlanillaActual); f.setDate(f.getDate()+dias); fechaPlanillaActual = f.toISOString().split('T')[0]; document.getElementById('filtroFecha').value = fechaPlanillaActual; aplicarFiltros(); }
    function irAHoy() { fechaPlanillaActual = new Date().toISOString().split('T')[0]; document.getElementById('filtroFecha').value = fechaPlanillaActual; aplicarFiltros(); }

    function seleccionarSlot(id, hora, nro, recinto, deporte, valor) {
        reservaActual = { id_cancha: id, nro_cancha: nro, recinto_nombre: recinto, id_deporte: deporte, valor_arriendo: valor, fecha: fechaPlanillaActual, hora_inicio: hora };
        document.getElementById('modalInfo').innerHTML = `<strong>Cancha:</strong> ${nro}<br><strong>Fecha:</strong> ${fechaPlanillaActual}<br><strong>Hora:</strong> ${hora}`;
        document.getElementById('opcionesDuracion').style.display = (deporte === 'padel') ? 'block' : 'none';
        actualizarPrecioModal((deporte === 'padel') ? 90 : 60);
        document.getElementById('modalReservaInteligente').style.display = 'flex';
    }

    function actualizarPrecioModal(min) { 
        if(!reservaActual) return; 
        const factor = min/60; 
        document.getElementById('precioDisplay').textContent = '$' + Math.round(parseFloat(reservaActual.valor_arriendo)*factor).toLocaleString(); 
    }
    function cerrarModalReserva() { document.getElementById('modalReservaInteligente').style.display = 'none'; }
    
    // ✅ FUNCIÓN CORREGIDA: async + sin código duplicado + orden lógico correcto
    async function confirmarReservaInteligente() {
        if (!reservaActual) {
            showToast('❌ No hay reserva seleccionada', 'error');
            return;
        }

        // 1. Obtener duración PRIMERO (antes de usarla)
        const duracion = parseInt(document.querySelector('input[name="duracion"]:checked')?.value || 60);

        // 2. Validación de horario de cierre (opcional, pero útil)
        const horaCierre = "23:00"; // Idealmente venir de la API por cancha
        const [hInicio, mInicio] = reservaActual.hora_inicio.split(':').map(Number);
        const [hCierre, mCierre] = horaCierre.split(':').map(Number);
        const finMinutos = (hInicio * 60 + mInicio) + duracion;
        const cierreMinutos = hCierre * 60 + mCierre;

        if (finMinutos > cierreMinutos) {
            showToast('❌ Esta reserva termina después del horario de cierre', 'error');
            return;
        }

        // 3. Calcular hora_fin
        const [h,m] = reservaActual.hora_inicio.split(':').map(Number);
        const fin = new Date(`${reservaActual.fecha}T${reservaActual.hora_inicio}:00`);
        fin.setMinutes(fin.getMinutes() + duracion);
        const horaFinStr = fin.toTimeString().substring(0,8);
        
        // 4. Preparar datos
        const datos = {
            id_cancha: reservaActual.id_cancha,
            fecha_base: reservaActual.fecha,
            hora_inicio: reservaActual.hora_inicio,
            hora_fin: horaFinStr,
            duracion_minutos: duracion,
            tipo_patron: 'simple',
            club_id: ''
        };
        
        // 5. Enviar a API con manejo robusto de errores
        try {
            const res = await fetch('../api/crear_reserva_recurrente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(datos)
            });
            
            // Verificar que la respuesta sea JSON válido
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await res.text();
                console.error('❌ API devolvió HTML/Texto:', text.substring(0, 200));
                throw new Error('Respuesta inválida del servidor (HTTP ' + res.status + ')');
            }
            
            const data = await res.json();
            
            if (data.success) {
                showToast('✅ Reserva creada, recibirás un correo de confirmación', 'success');
                cerrarModalReserva();
                aplicarFiltros(); // Recargar planilla
            } else {
                showToast('❌ ' + (data.message || 'Error al crear reserva'), 'error');
            }
        } catch (err) {
            console.error('❌ Error en confirmarReservaInteligente:', err);
            const msg = err.message.includes('JSON') 
                ? '❌ Error en el servidor. Intenta nuevamente.' 
                : '❌ Error de conexión. Verifica tu internet.';
            showToast(msg, 'error');
        }
    }

    function showToast(msg, type) {
        const container = document.getElementById('toast-container');
        if (!container) {
            console.warn('⚠️ toast-container no existe');
            return alert(msg); // Fallback
        }
        const t = document.createElement('div'); 
        t.className = `toast ${type}`; 
        t.textContent = msg; 
        container.appendChild(t);
        setTimeout(() => t.classList.add('show'), 100); 
        setTimeout(() => { t.classList.remove('show'); setTimeout(()=>t.remove(), 300); }, 3000);
    }
</script>
</body>
</html>