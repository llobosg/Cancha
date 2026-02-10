<?php
// LOG DE ENTRADA
error_log("🎯 ACCESO A reservar_cancha.php - Inicio de ejecución");

// 🔥 CONFIGURACIÓN ROBUSTA DE SESIONES
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sesión para Railway
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path' => '/',
        'domain' => '', // Dejar vacío para Railway
        'secure' => isset($_SERVER['HTTPS']), // HTTPS si está disponible
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
error_log("no pasó por session_status === PHP_SESSION_NONE");
// DEBUG INMEDIATO - Ver qué hay en la sesión
error_log("=== DEBUG SESIÓN INMEDIATO ===");
error_log("Session ID: " . session_id());
error_log("Session Status: " . session_status());
error_log("Cookies recibidas: " . print_r($_COOKIE, true));

require_once __DIR__ . '/../includes/config.php';

// DEBUG DETALLADO
error_log("=== DEBUG RESERVAR CANCHA ===");
error_log("Sesión completa: " . print_r($_SESSION, true));
error_log("id_socio en sesión1: " . (isset($_SESSION['id_socio']) ? $_SESSION['id_socio'] : 'NO EXISTE'));
error_log("club_id en sesión1: " . (isset($_SESSION['club_id']) ? $_SESSION['club_id'] : 'NO EXISTE'));

// Verificar requisitos mínimos
if (!isset($_SESSION['id_socio'])) {
    error_log("REDIRECCIÓN: Falta id_socio en sesión");
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['club_id'])) {
    error_log("REDIRECCIÓN: Falta club_id en sesión");
    header('Location: ../index.php');
    exit;
}

$id_socio = $_SESSION['id_socio'];
$id_club = $_SESSION['club_id'];

// Verificar que existan en la base de datos
$stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
$stmt->execute([$id_socio, $id_club]);
$socio_valido = $stmt->fetch();

if (!$socio_valido) {
    error_log("REDIRECCIÓN: Socio no válido o no pertenece al club");
    error_log("id_socio: $id_socio, id_club: $id_club");
    header('Location: ../index.php');
    exit;
}

error_log("✅ ACCESO PERMITIDO: id_socio=$id_socio, id_club=$id_club");

if (!$stmt->fetch()) {
    // Intentar obtener el club_id correcto del socio
    $stmt_fix = $pdo->prepare("SELECT id_club FROM socios WHERE id_socio = ?");
    $stmt_fix->execute([$id_socio]);
    $correct_club = $stmt_fix->fetch();
    
    if ($correct_club) {
        $_SESSION['club_id'] = $correct_club['id_club'];
        $id_club = $correct_club['id_club'];
    } else {
        header('Location: ../index.php');
        exit;
    }
}

// Obtener recintos deportivos disponibles
$stmt_recintos = $pdo->prepare("
    SELECT id_recinto, nombre 
    FROM recintos_deportivos 
    WHERE email_verified = 1
    ORDER BY nombre
");
$stmt_recintos->execute();
$recintos = $stmt_recintos->fetchAll();

// Obtener deportes disponibles
$deportes = [
    'futbol' => 'Fútbol',
    'futbolito' => 'Futbolito', 
    'futsal' => 'Futsal',
    'tenis' => 'Tenis',
    'padel' => 'Pádel',
    'voleyball' => 'Voleyball',
    'otro' => 'Quincho/Otro'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reservar Cancha | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
                 url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      color: white;
    }
    
    .dashboard-container {
      display: grid;
      grid-template-columns: 4fr 1fr;
      gap: 1rem;
      max-width: 1400px;
      margin: 0 auto;
      padding: 1rem;
    }
    
    .header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 60px;
      background: rgba(0, 51, 102, 0.95);
      backdrop-filter: blur(10px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 1.5rem;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    .main-title-section {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .logo-corporativo {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: #FFD700;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }
    
    .main-title {
      color: #FFD700;
      font-size: 1.5rem;
      margin: 0;
    }
    
    .controls-section {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      padding: 0.5rem;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
      position: sticky;
      top: 70px;
      z-index: 999;
    }
    
    .control-select {
      background: white;
      padding: 0.3rem;
      border-radius: 4px;
      color: #071289;
      border: none;
    }
    
    .reservas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1rem;
      overflow-y: auto;
      padding-right: 0.5rem;
    }
    
    .reserva-card {
      background: white;
      border-radius: 12px;
      padding: 1rem;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      position: relative;
      overflow: hidden;
    }
    
    .reserva-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    
    .reserva-card.selected {
      border: 3px solid #071289;
    }
    
    .deporte-icon {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
    }
    
    .cancha-nombre {
      font-weight: bold;
      color: #071289;
      margin-bottom: 0.3rem;
    }
    
    .fecha-hora {
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 0.5rem;
    }
    
    .estado-indicator {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }
    
    .estado-disponible { background: #FFD700; } /* Amarillo */
    .estado-reservada { background: #9C27B0; }  /* Morado */
    .estado-ocupada { background: #4CAF50; }    /* Verde */
    .estado-cancelada { background: #F44336; }  /* Rojo */
    
    /* Panel lateral fijo */
    .detail-panel {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      position: sticky;
      top: 120px;
      align-self: flex-start;
      height: fit-content;
      max-height: calc(100vh - 140px);
    }
    
    .detail-section {
      background: white;
      padding: 1rem;
      border-radius: 12px;
      width: 100%;
      overflow-y: auto;
      max-height: 350px;
    }
    
    .actions-section {
      background: white;
      padding: 1rem;
      border-radius: 12px;
      width: 100%;
      overflow-y: auto;
      max-height: 320px;
    }
    
    .detail-title {
      color: #071289;
      margin-bottom: 1rem;
      font-size: 1.2rem;
    }
    
    .detail-item {
      margin-bottom: 0.5rem;
    }
    
    .detail-label {
      font-weight: bold;
      color: #333;
    }
    
    .action-btn {
      padding: 0.5rem;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      text-align: left;
      transition: background 0.2s;
      color: #333;
    }
    
    .action-btn:hover {
      background: rgba(255,255,255,0.2);
      color: #000;
    }
    
    .btn-reservar {
      background: #00cc66;
      color: white !important;
    }
    
    /* Toast Notifications */
    .toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 12px 20px;
      border-radius: 8px;
      color: white;
      font-weight: bold;
      z-index: 10000;
      transform: translateX(120%);
      transition: transform 0.3s ease-in-out;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    
    .toast.show {
      transform: translateX(0);
    }
    
    .toast.success {
      background: linear-gradient(135deg, #4CAF50, #2E7D32);
    }
    
    .toast.error {
      background: linear-gradient(135deg, #F44336, #C62828);
    }
    
    .toast.warning {
      background: linear-gradient(135deg, #FF9800, #EF6C00);
    }
    
    .toast.info {
      background: linear-gradient(135deg, #2196F3, #1565C0);
    }
    
    /* Responsive móvil */
    @media (max-width: 768px) {
      .dashboard-container {
        grid-template-columns: 1fr;
        padding-top: 80px;
      }
      
      .reservas-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      }
      
      .detail-panel {
        position: static;
        top: auto;
        align-self: auto;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="main-title-section">
      <div class="logo-corporativo">⚽</div>
      <h1 class="main-title">Reservar Cancha</h1>
    </div>
    <div>
      <a href="dashboard_socio.php" style="color: #ffcc00; text-decoration: none;">← Dashboard</a>
    </div>
  </div>
  
  <div class="dashboard-container" style="margin-top: 70px;">
    <div>
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
          <option value="semana" selected>Esta semana</option>
          <option value="hoy">Hoy</option>
          <option value="mañana">Mañana</option>
          <option value="mes">Este mes</option>
        </select>
      </div>
      
      <div class="reservas-grid" id="reservasGrid">
        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">
          Cargando disponibilidad...
        </div>
      </div>
    </div>
    
    <div class="detail-panel">
      <div class="detail-section" id="detalleReserva">
        <h3 class="detail-title">📋 Detalle de Reserva</h3>
        <div id="detalleContent">
          <p>Selecciona una cancha disponible para ver detalles</p>
        </div>
      </div>
      
      <div class="actions-section">
        <h3 class="detail-title">🎯 Acciones</h3>
        <div class="actions-grid">
          <button class="action-btn btn-reservar" onclick="reservarCancha()">
            📅 Confirmar Reserva
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Submodal para tipo de reserva -->
  <div id="tipoReservaModal" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1001;">
    <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:500px; position:relative;">
      <span class="close-modal" onclick="closeTipoReservaModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
      <h3>Seleccionar Tipo de Reserva</h3>
      <form id="tipoReservaForm">
        <div class="form-group">
          <label>
            <input type="radio" name="tipo_reserva" value="spot" checked> Spot (Una vez)
          </label><br>
          <label>
            <input type="radio" name="tipo_reserva" value="semanal"> Semanal (Mismo día/hora toda la semana)
          </label><br>
          <label>
            <input type="radio" name="tipo_reserva" value="mensual"> Mensual (Mismo día/hora todo el mes)
          </label>
        </div>
        <button type="submit" class="btn-submit" style="width:100%;">Confirmar Tipo</button>
      </form>
    </div>
  </div>

  <script>
    let reservaSeleccionada = null;
    let reservasData = [];
    let tipoReservaSeleccionado = 'spot';

    // Datos del usuario
    const userData = {
        club: '<?= addslashes(htmlspecialchars($usuario_data['nombre_club'])) ?>',
        responsable: '<?= addslashes(htmlspecialchars($usuario_data['alias'])) ?>',
        correo: '<?= addslashes(htmlspecialchars($usuario_data['email'])) ?>',
        telefono: '<?= addslashes(htmlspecialchars($usuario_data['celular'])) ?>'
    };

    // Sistema de Toast Notifications
    function showToast(message, type = 'info') {
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    function validarReservaSeleccionada() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Debes seleccionar una cancha disponible primero', 'warning');
            return false;
        }
        return true;
    }

    // Cargar disponibilidad inicial
    async function cargarDisponibilidad(filtros = {}) {
        try {
            const formData = new FormData();
            formData.append('deporte', filtros.deporte || '');
            formData.append('recinto', filtros.recinto || '');
            formData.append('rango', filtros.rango || 'semana');
            
            const response = await fetch('../api/reservas_club.php?action=get_disponibilidad', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            reservasData = data;
            renderizarDisponibilidad(reservasData);
            
        } catch (error) {
            console.error('Error al cargar disponibilidad:', error);
            document.getElementById('reservasGrid').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">Error al cargar la disponibilidad</div>';
        }
    }

    function renderizarDisponibilidad(disponibilidad) {
        const grid = document.getElementById('reservasGrid');
        
        if (disponibilidad.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">No hay canchas disponibles en el período seleccionado</div>';
            return;
        }
        
        // Agrupar por fecha
        const porFecha = {};
        disponibilidad.forEach(item => {
            const fecha = item.fecha;
            if (!porFecha[fecha]) {
                porFecha[fecha] = [];
            }
            porFecha[fecha].push(item);
        });
        
        grid.innerHTML = '';
        
        Object.keys(porFecha).sort().forEach(fecha => {
            const fechaDiv = document.createElement('div');
            fechaDiv.style.gridColumn = '1/-1';
            fechaDiv.style.marginTop = '1.5rem';
            fechaDiv.style.paddingBottom = '0.5rem';
            fechaDiv.style.borderBottom = '1px solid rgba(255,255,255,0.2)';
            fechaDiv.style.color = '#FFD700';
            fechaDiv.style.fontWeight = 'bold';
            fechaDiv.textContent = new Date(fecha).toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
            grid.appendChild(fechaDiv);
            
            porFecha[fecha].forEach(item => {
                if (item.estado !== 'disponible') return; // Solo mostrar disponibles
                
                const card = document.createElement('div');
                card.className = 'reserva-card';
                card.onclick = () => selectDisponibilidad(item);
                
                const iconos = {
                    'futbol': '⚽', 'futbolito': '⚽', 'futsal': '⚽',
                    'tenis': '🎾', 'padel': '🎾', 'voleyball': '🏐',
                    'otro': '🏟️'
                };
                
                card.innerHTML = `
                    <div class="deporte-icon">${iconos[item.id_deporte] || '🏟️'}</div>
                    <div class="cancha-nombre">${item.nro_cancha || 'Sin nombre'}</div>
                    <div class="fecha-hora">
                        ${item.hora_inicio.substring(0, 5)}<br>
                        ${item.recinto_nombre}
                    </div>
                    <div class="estado-indicator estado-disponible"></div>
                `;
                
                grid.appendChild(card);
            });
        });
    }

    function selectDisponibilidad(item) {
        // Quitar selección anterior
        document.querySelectorAll('.reserva-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        event.currentTarget.classList.add('selected');
        reservaSeleccionada = item;
        
        // Mostrar detalle
        mostrarDetalleDisponibilidad(item);
    }

    function mostrarDetalleDisponibilidad(item) {
        const fechaHora = new Date(item.fecha).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + item.hora_inicio.substring(0, 5);
        
        document.getElementById('detalleContent').innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Club:</span> 
                <span>${userData.club}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Responsable:</span> 
                <span>${userData.responsable}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Correo:</span> 
                <span>${userData.correo}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Teléfono:</span> 
                <span>${userData.telefono || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Cancha:</span> 
                <span>${item.nro_cancha}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Recinto:</span> 
                <span>${item.recinto_nombre}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Deporte:</span> 
                <span>${item.id_deporte}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha/Hora:</span> 
                <span>${fechaHora}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Valor:</span> 
                <span>$${item.valor_arriendo}</span>
            </div>
        `;
    }

    async function reservarCancha() {
        if (!validarReservaSeleccionada()) return;
        
        // Mostrar modal para seleccionar tipo de reserva
        document.getElementById('tipoReservaModal').style.display = 'flex';
    }

    function closeTipoReservaModal() {
        document.getElementById('tipoReservaModal').style.display = 'none';
    }

    document.getElementById('tipoReservaForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        tipoReservaSeleccionado = document.querySelector('input[name="tipo_reserva"]:checked').value;
        closeTipoReservaModal();
        
        // Confirmar la reserva
        if (tipoReservaSeleccionado !== 'spot') {
            const mensaje = tipoReservaSeleccionado === 'semanal' 
                ? '¿Deseas reservar esta cancha para el mismo día y hora durante toda la semana?'
                : '¿Deseas reservar esta cancha para el mismo día y hora durante todo el mes?';
            
            if (!confirm(mensaje)) {
                return;
            }
        }
        
        await confirmarReserva();
    });

    async function confirmarReserva() {
        try {
            const formData = new FormData();
            formData.append('id_cancha', reservaSeleccionada.id_cancha);
            formData.append('id_club', <?= $id_club ?>);
            formData.append('id_socio', <?= $id_socio ?>);
            formData.append('fecha', reservaSeleccionada.fecha);
            formData.append('hora_inicio', reservaSeleccionada.hora_inicio);
            formData.append('hora_fin', reservaSeleccionada.hora_fin);
            formData.append('tipo_reserva', tipoReservaSeleccionado);
            formData.append('valor_arriendo', reservaSeleccionada.valor_arriendo);
            
            const response = await fetch('../api/reservas_club.php?action=crear_reserva', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ ¡Reserva confirmada! Recibirás un correo con los detalles.', 'success');
                
                // Notificar a miembros del club y admin del recinto
                setTimeout(() => {
                    showToast('📨 Notificaciones enviadas a todos los miembros del club y al administrador del recinto.', 'info');
                }, 2000);
                
                // Resetear selección
                reservaSeleccionada = null;
                document.querySelectorAll('.reserva-card').forEach(card => {
                    card.classList.remove('selected');
                });
                document.getElementById('detalleContent').innerHTML = '<p>Selecciona una cancha disponible para ver detalles</p>';
                
                // Recargar disponibilidad
                aplicarFiltros();
                
            } else {
                throw new Error(result.message || 'Error al crear la reserva');
            }
            
        } catch (error) {
            console.error('Error al reservar:', error);
            showToast(`❌ Error: ${error.message}`, 'error');
        }
    }

    // Filtros
    function aplicarFiltros() {
        const filtros = {
            deporte: document.getElementById('filtroDeporte').value,
            recinto: document.getElementById('filtroRecinto').value,
            rango: document.getElementById('filtroFecha').value
        };
        cargarDisponibilidad(filtros);
    }

    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroRecinto').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroFecha').addEventListener('change', aplicarFiltros);

    // Cargar inicialmente
    document.addEventListener('DOMContentLoaded', function() {
        cargarDisponibilidad({ rango: 'semana' });
    });
  </script>
</body>
</html>