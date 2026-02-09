<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
    header('Location: ../index.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Obtener datos del recinto
$stmt = $pdo->prepare("SELECT nombre, logorecinto FROM recintos_deportivos WHERE id_recinto = ?");
$stmt->execute([$id_recinto]);
$recinto = $stmt->fetch();

// Obtener canchas con sus reservas para los próximos 7 días
$fecha_inicio = date('Y-m-d');
$fecha_fin = date('Y-m-d', strtotime('+7 days'));

$stmt = $pdo->prepare("
    SELECT 
        c.id_cancha,
        c.nro_cancha,
        c.nombre_cancha,
        c.id_deporte,
        COALESCE(dc.fecha, ?) as fecha,
        COALESCE(dc.hora_inicio, '07:00:00') as hora_inicio,
        COALESCE(dc.hora_fin, '21:00:00') as hora_fin,
        COALESCE(dc.estado, 'disponible') as estado_disponibilidad,
        r.id_reserva,
        r.estado as estado_reserva,
        COALESCE(r.estado_pago, 'pendiente') as estado_pago,
        cl.nombre as nombre_club,
        s.alias as nombre_responsable,
        r.telefono_cliente,
        r.email_cliente
    FROM canchas c
    LEFT JOIN disponibilidad_canchas dc ON c.id_cancha = dc.id_cancha 
        AND dc.fecha BETWEEN ? AND ?
    LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
    LEFT JOIN clubs cl ON r.id_club = cl.id_club
    LEFT JOIN socios s ON r.id_socio = s.id_socio
    WHERE c.id_recinto = ? AND c.activa = 1
    ORDER BY c.id_deporte, dc.fecha, dc.hora_inicio
");
$stmt->execute([$fecha_inicio, $fecha_inicio, $fecha_fin, $id_recinto]);
$reservas_data = $stmt->fetchAll();

// Agrupar por fecha y deporte
$reservas_agrupadas = [];
foreach ($reservas_data as $reserva) {
    $key = $reserva['fecha'] . '_' . $reserva['id_deporte'];
    if (!isset($reservas_agrupadas[$key])) {
        $reservas_agrupadas[$key] = [
            'fecha' => $reserva['fecha'],
            'deporte' => $reserva['id_deporte'],
            'reservas' => []
        ];
    }
    $reservas_agrupadas[$key]['reservas'][] = $reserva;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CanchaBoard - <?= htmlspecialchars($recinto['nombre']) ?> | Cancha</title>
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
      height: calc(100vh - 80px);
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
    
    .main-title {
      color: #FFD700;
      font-size: 1.5rem;
      margin: 0;
    }
    
    .controls {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      padding: 0.5rem;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
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
    .estado-mantencion { background: #FF9800; } /* Naranja */
    
    .detail-panel {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      height: 100%;
    }
    
    .detail-section {
      background: white;
      padding: 1rem;
      border-radius: 12px;
      overflow-y: auto;
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
    
    .actions-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.5rem;
    }
    
    .action-btn {
      padding: 0.5rem;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      text-align: left;
      transition: background 0.2s;
    }
    
    .action-btn:hover {
      background: rgba(255,255,255,0.2);
    }
    
    .btn-anular { background: #F44336; color: white; }
    .btn-cancelar { background: #FF9800; color: white; }
    .btn-cambiar { background: #2196F3; color: white; }
    .btn-mensaje { background: #4CAF50; color: white; }
    .btn-correo { background: #9C27B0; color: white; }
    
    /* Responsive móvil */
    @media (max-width: 768px) {
      .dashboard-container {
        grid-template-columns: 1fr;
        grid-template-rows: 2fr 1fr;
        height: auto;
        min-height: calc(100vh - 120px);
      }
      
      .reservas-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="main-title">🏟️ CanchaBoard - <?= htmlspecialchars($recinto['nombre']) ?></h1>
    <div>
      <a href="recinto_dashboard.php" style="color: #ffcc00; text-decoration: none;">← Dashboard</a>
    </div>
  </div>
  
  <div class="dashboard-container" style="margin-top: 70px;">
    <div>
      <div class="controls">
        <select class="control-select" id="filtroDeporte">
          <option value="">Todos los deportes</option>
          <option value="futbol">Fútbol</option>
          <option value="futbolito">Futbolito</option>
          <option value="futsal">Futsal</option>
          <option value="tenis">Tenis</option>
          <option value="padel">Pádel</option>
          <option value="voleyball">Voleyball</option>
          <option value="otro">Quincho/Otro</option>
        </select>
        
        <select class="control-select" id="filtroEstado">
          <option value="">Todos los estados</option>
          <option value="disponible">Disponible</option>
          <option value="reservada">Reservada</option>
          <option value="ocupada">Ocupada</option>
          <option value="cancelada">Cancelada</option>
        </select>
        
        <select class="control-select" id="filtroFecha">
            <option value="">Últimos 30 días</option>
            <option value="hoy">Hoy</option>
            <option value="mañana">Mañana</option>
            <option value="semana">Esta semana</option>
            <option value="mes">Este mes</option>
            </select>
      </div>
      
      <div class="reservas-grid" id="reservasGrid">
        <?php foreach ($reservas_data as $reserva): ?>
            <div class="reserva-card" onclick="selectReserva(<?= (int)$reserva['id_disponibilidad'] ?>)">
            <div class="deporte-icon">
                <?php 
                $iconos = [
                    'futbol' => '⚽', 'futbolito' => '⚽', 'futsal' => '⚽',
                    'tenis' => '🎾', 'padel' => '🎾', 'voleyball' => '🏐',
                    'otro' => '🏟️'
                ];
                echo $iconos[$reserva['id_deporte']] ?? '🏟️';
                ?>
            </div>
            <div class="cancha-nombre"><?= htmlspecialchars($reserva['nro_cancha'] ?? 'Sin nombre') ?></div>
            <div class="fecha-hora">
                <?= formatDateSafe($reserva['fecha']) ?><br>
                <?= formatTimeSafe($reserva['hora_inicio']) ?>
            </div>
            <div class="estado-indicator <?= getEstadoClass($reserva['estado_disponibilidad']) ?>"></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    
    <div class="detail-panel">
      <div class="detail-section" id="detalleReserva">
        <h3 class="detail-title">📋 Detalle de Reserva</h3>
        <div id="detalleContent">
          <p>Selecciona una reserva para ver detalles</p>
        </div>
      </div>
      
      <div class="detail-section">
        <h3 class="detail-title">⚙️ Acciones</h3>
        <div class="actions-grid">
          <button class="action-btn btn-anular" onclick="anularReserva()">🗑️ Anular</button>
          <button class="action-btn btn-cancelar" onclick="cancelarReserva()">❌ Cancelar Reserva</button>
          <button class="action-btn btn-cambiar" onclick="cambiarCancha()">🔄 Cambiar de Cancha</button>
          <button class="action-btn btn-mensaje" onclick="enviarMensaje()">💬 Enviar Mensaje</button>
          <button class="action-btn btn-correo" onclick="enviarCorreo()">📧 Correo de Respald</button>
          <button class="action-btn" style="background: #00cc66; color: white;" onclick="crearCampeonato()">
            🏆 Crear Campeonato
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let reservaSeleccionada = null;
    
    function getEstadoClass(estado) {
        switch(estado) {
            case 'disponible': return 'estado-disponible';
            case 'reservada': return 'estado-reservada';
            case 'ocupada': return 'estado-ocupada';
            case 'cancelada': return 'estado-cancelada';
            case 'mantencion': return 'estado-mantencion';
            default: return 'estado-disponible';
        }
    }
    
    function selectReserva(idDisponibilidad) {
        // Quitar selección anterior
        document.querySelectorAll('.reserva-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Seleccionar nueva
        event.currentTarget.classList.add('selected');
        reservaSeleccionada = idDisponibilidad;
        
        // Cargar detalle (simulado por ahora)
        cargarDetalleReserva(idDisponibilidad);
    }
    
    function cargarDetalleReserva(id) {
        // Aquí iría la llamada AJAX para obtener el detalle real
        const detalleDiv = document.getElementById('detalleContent');
        detalleDiv.innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Cancha:</span> 
                <span id="detalleCancha">Cancha A</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Club:</span> 
                <span id="detalleClub">Club XYZ</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Responsable:</span> 
                <span id="detalleResponsable">Juan Pérez</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Teléfono:</span> 
                <span id="detalleTelefono">+56 9 1234 5678</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha/Hora:</span> 
                <span id="detalleFechaHora">15/02/2026 15:00</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado Pago:</span> 
                <span id="detallePago" style="color: #4CAF50;">Pagado</span>
            </div>
        `;
    }
    
    function anularReserva() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        if (confirm('¿Estás seguro de anular esta reserva?')) {
            // Lógica de anulación
            alert('Reserva anulada');
        }
    }
    
    function cancelarReserva() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        if (confirm('¿Estás seguro de cancelar esta reserva?')) {
            // Lógica de cancelación
            alert('Reserva cancelada');
        }
    }
    
    function cambiarCancha() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        // Lógica de cambio de cancha
        alert('Funcionalidad de cambio de cancha en desarrollo');
    }
    
    function enviarMensaje() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        // Usar sistema de notificaciones existente
        alert('Mensaje enviado mediante notificaciones');
    }
    
    function enviarCorreo() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        // Usar sistema de correo Brevo
        alert('Correo de respaldo enviado');
    }
    
    function crearCampeonato() {
        // Redirigir a página de creación de campeonatos
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
    }
    
    // Filtros
    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroFecha').addEventListener('change', aplicarFiltros);
    
    function aplicarFiltros() {
        const deporte = document.getElementById('filtroDeporte').value;
        const estado = document.getElementById('filtroEstado').value;
        const fecha = document.getElementById('filtroFecha').value;
        
        // Lógica de filtrado (simulada)
        console.log('Filtros aplicados:', {deporte, estado, fecha});
    }

    // Función para cargar reservas con rango de días
    async function cargarReservasConRango(rangoDias = 30) {
        try {
            const response = await fetch(`../api/canchaboard.php?action=get_reservas&rango_dias=${rangoDias}`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            reservasData = data;
            renderizarReservas(reservasData);
            
        } catch (error) {
            console.error('Error al cargar reservas:', error);
            alert('Error al cargar las reservas: ' + error.message);
        }
    }

    // Actualizar los controles de rango
    document.getElementById('filtroFecha').addEventListener('change', function() {
        const valor = this.value;
        let rangoDias = 30;
        
        if (valor === 'hoy') rangoDias = 0;
        else if (valor === 'mañana') rangoDias = 1;
        else if (valor === 'semana') rangoDias = 7;
        else if (valor === 'mes') rangoDias = 30;
        
        cargarReservasConRango(rangoDias);
    });

    // Cargar inicialmente con 30 días
    document.addEventListener('DOMContentLoaded', function() {
        cargarReservasConRango(30);
    });
  </script>
</body>
</html>

<?php
function getEstadoClass($estado) {
    switch($estado) {
        case 'disponible': return 'estado-disponible';
        case 'reservada': return 'estado-reservada';
        case 'ocupada': return 'estado-ocupada';
        case 'cancelada': return 'estado-cancelada';
        case 'mantencion': return 'estado-mantencion';
        default: return 'estado-disponible';
    }
}

function formatDateSafe($dateString) {
    if (empty($dateString)) {
        return 'N/A';
    }
    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return 'N/A';
    }
    return date('d/m', $timestamp);
}

function formatTimeSafe($timeString) {
    if (empty($timeString)) {
        return 'N/A';
    }
    // Extraer solo HH:MM de HH:MM:SS
    return substr($timeString, 0, 5);
}
?>