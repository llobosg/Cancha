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
    .main-container { max-width: 1400px; margin: 1rem auto; padding: 0 1rem; }
    
    /* Filtros */
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
    .planilla-scroll { overflow-x: auto; max-height: 70vh; }
    
    .planilla-table {
        width: 100%; border-collapse: separate; border-spacing: 2px;
        table-layout: fixed; min-width: 800px;
    }
    
    /* Headers */
    .planilla-table th {
        background: #AB47BC; color: white; padding: 10px;
        position: sticky; top: 0; z-index: 10; font-size: 0.85rem;
    }
    .planilla-table th:first-child {
        left: 0; z-index: 11; background: #8E24AA; width: 70px; min-width: 70px;
    }
    
    /* Celdas */
    .planilla-table td {
        background: #f8f9fa; padding: 8px; text-align: center;
        border: 1px solid #eee; font-size: 0.8rem; height: 40px;
        cursor: pointer; transition: 0.2s;
    }
    .planilla-table td:first-child {
        background: #e3f2fd; font-weight: bold; color: #071289;
        position: sticky; left: 0; z-index: 5;
    }
    
    /* Estados */
    td.slot-disponible:hover { background: #E8F5E9; border-color: #4CAF50; }
    td.slot-ocupado { background: #FFEBEE; color: #C62828; cursor: not-allowed; opacity: 0.7; }
    td.slot-seleccionado { background: #FFFDE7; border: 2px solid #FBC02D; }
    
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
    .form-group input, .form-group select { width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px; }
    
    /* Toast */
    .toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: white; font-weight: bold; z-index: 3000; transform: translateX(120%); transition: transform 0.3s; }
    .toast.show { transform: translateX(0); }
    .toast.success { background: #4CAF50; }
    .toast.error { background: #F44336; }
</style>
</head>
<body>

<div class="header">
    <div class="brand-logo">🎾 Reservar Cancha</div>
    <a href="dashboard_socio.php" style="color: white; text-decoration: none;">← Volver</a>
</div>

<div class="main-container">
    <!-- Filtros -->
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
        
        <select class="control-select" id="filtroFecha">
            <option value="hoy">Hoy</option>
            <option value="manana">Mañana</option>
            <option value="semana" selected>Esta Semana</option>
        </select>
        
        <button onclick="aplicarFiltros()" style="background:#4ECDC4; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; font-weight:bold; color:#071289;">🔍 Buscar</button>
    </div>

    <!-- Planilla -->
    <div class="planilla-wrapper">
        <div class="planilla-scroll">
            <table class="planilla-table" id="tablaReservas">
                <thead>
                    <tr id="tablaHeader">
                        <th>Hora</th>
                        <!-- Se llena con JS -->
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr><td colspan="100%" style="padding:2rem; text-align:center;">Selecciona filtros para ver disponibilidad</td></tr>
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
    let slotsData = []; // Almacenará la estructura de la planilla

    // Inicialización
    document.addEventListener('DOMContentLoaded', () => {
        aplicarFiltros();
    });

    async function aplicarFiltros() {
        const deporte = document.getElementById('filtroDeporte').value;
        const recinto = document.getElementById('filtroRecinto').value;
        const rango = document.getElementById('filtroFecha').value;

        document.getElementById('tablaBody').innerHTML = '<tr><td colspan="100%" style="padding:2rem; text-align:center;">Cargando...</td></tr>';

        try {
            const formData = new FormData();
            formData.append('deporte', deporte);
            formData.append('recinto', recinto);
            formData.append('rango', rango);
            formData.append('id_socio', <?= $id_socio ?>);

            const res = await fetch('../api/reservas_club.php?action=get_disponibilidad', {
                method: 'POST', body: formData, credentials: 'include'
            });
            
            const data = await res.json();
            if(data.error) throw new Error(data.error);
            
            renderizarPlanilla(data);
        } catch (error) {
            console.error(error);
            document.getElementById('tablaBody').innerHTML = `<tr><td colspan="100%" style="padding:2rem; color:red;">Error: ${error.message}</td></tr>`;
        }
    }

    function renderizarPlanilla(data) {
        const thead = document.getElementById('tablaHeader');
        const tbody = document.getElementById('tablaBody');
        
        // 1. Identificar Canchas Únicas en los datos
        const canchas = [...new Map(data.map(item => [item.id_cancha, item])).values()]
            .sort((a,b) => a.nro_cancha.localeCompare(b.nro_cancha));
        
        if(canchas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="100%" style="padding:2rem;">No hay canchas disponibles con estos filtros.</td></tr>';
            return;
        }

        // 2. Construir Header
        let htmlHead = '<th>Hora</th>';
        canchas.forEach(c => {
            htmlHead += `<th>${c.nro_cancha}<br><small style="font-weight:normal;">${c.recinto_nombre}</small></th>`;
        });
        thead.innerHTML = htmlHead;

        // 3. Generar Slots de Tiempo (De 07:00 a 23:00 cada 30 min)
        // Nota: Idealmente esto viene del backend, pero lo generamos frontend para la vista de grilla
        let htmlBody = '';
        let horaActual = 7 * 60; // 07:00 en minutos
        const horaFinDia = 23 * 60; // 23:00

        while(horaActual < horaFinDia) {
            const h = Math.floor(horaActual / 60).toString().padStart(2,'0');
            const m = (horaActual % 60).toString().padStart(2,'0');
            const timeLabel = `${h}:${m}`;
            
            htmlBody += `<tr><td>${timeLabel}</td>`;
            
            canchas.forEach(cancha => {
                // Buscar si hay disponibilidad exacta para esta cancha y hora
                // Nota: La API debe devolver items con hora_inicio coincidente
                const slot = data.find(d => 
                    d.id_cancha == cancha.id_cancha && 
                    d.hora_inicio.substring(0,5) == timeLabel
                );

                if(slot && slot.estado === 'disponible') {
                    htmlBody += `<td class="slot-disponible" onclick='seleccionarSlot(${JSON.stringify(slot).replace(/'/g, "&#39;")})'>Disponible</td>`;
                } else {
                    htmlBody += `<td class="slot-ocupado">Ocupado</td>`;
                }
            });
            
            htmlBody += `</tr>`;
            horaActual += 30; // Avanzar 30 min
        }
        tbody.innerHTML = htmlBody;
    }

    function seleccionarSlot(slot) {
        reservaActual = slot;
        const modal = document.getElementById('modalReservaInteligente');
        
        // Configurar Modal
        document.getElementById('modalInfo').innerHTML = `
            <strong>Cancha:</strong> ${slot.nro_cancha} (${slot.recinto_nombre})<br>
            <strong>Fecha:</strong> ${slot.fecha}<br>
            <strong>Hora Inicio:</strong> ${slot.hora_inicio.substring(0,5)}
        `;
        
        // Mostrar/Ocultar opción duración según deporte
        const esPadel = (slot.id_deporte === 'padel');
        document.getElementById('opcionesDuracion').style.display = esPadel ? 'block' : 'none';
        
        // Default selección
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
        // Lógica simple de precio: Base * (minutos/60)
        // Ajusta esto según tu regla de negocio real
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
        
        // Calcular Hora Fin
        const [h, m] = reservaActual.hora_inicio.substring(0,5).split(':').map(Number);
        const fechaInicio = new Date();
        fechaInicio.setHours(h, m, 0);
        const fechaFin = new Date(fechaInicio.getTime() + duracion * 60000);
        const horaFinStr = fechaFin.toTimeString().substring(0,8);

        const datos = {
            id_cancha: reservaActual.id_cancha,
            fecha_base: reservaActual.fecha,
            hora_inicio: reservaActual.hora_inicio,
            hora_fin: horaFinStr, // Importante: Hora Fin calculada
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
                aplicarFiltros(); // Recargar planilla
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