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
        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">
          Cargando disponibilidad...
        </div>
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
    let reservasData = [];

    // Definir todas las funciones primero
    function renderizarReservas(reservas) {
        const grid = document.getElementById('reservasGrid');
        
        if (reservas.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">No hay disponibilidad en el período seleccionado</div>';
            return;
        }
        
        // Agrupar por fecha para mejor visualización
        const reservasPorFecha = {};
        reservas.forEach(reserva => {
            const fecha = reserva.fecha;
            if (!reservasPorFecha[fecha]) {
                reservasPorFecha[fecha] = [];
            }
            reservasPorFecha[fecha].push(reserva);
        });
        
        grid.innerHTML = '';
        
        // Renderizar por fechas
        Object.keys(reservasPorFecha).sort().forEach(fecha => {
            const fechaDiv = document.createElement('div');
            fechaDiv.style.gridColumn = '1/-1';
            fechaDiv.style.marginTop = '1.5rem';
            fechaDiv.style.paddingBottom = '0.5rem';
            fechaDiv.style.borderBottom = '1px solid rgba(255,255,255,0.2)';
            fechaDiv.style.color = '#FFD700';
            fechaDiv.style.fontWeight = 'bold';
            fechaDiv.textContent = formatDateDisplay(fecha);
            grid.appendChild(fechaDiv);
            
            reservasPorFecha[fecha].forEach(reserva => {
                const card = document.createElement('div');
                card.className = 'reserva-card';
                card.onclick = () => selectReserva(reserva.id_disponibilidad || `${reserva.id_cancha}_${reserva.fecha}_${reserva.hora_inicio}`);
                
                const iconos = {
                    'futbol': '⚽', 'futbolito': '⚽', 'futsal': '⚽',
                    'tenis': '🎾', 'padel': '🎾', 'voleyball': '🏐',
                    'otro': '🏟️'
                };
                
                const estadoClass = getEstadoClass(reserva.estado_disponibilidad || 'disponible');
                
                card.innerHTML = `
                    <div class="deporte-icon">${iconos[reserva.id_deporte] || '🏟️'}</div>
                    <div class="cancha-nombre">${reserva.nro_cancha || 'Sin nombre'}</div>
                    <div class="fecha-hora">
                        ${formatTimeDisplay(reserva.hora_inicio)}<br>
                        ${getEstadoTexto(reserva.estado_disponibilidad || 'disponible')}
                    </div>
                    <div class="estado-indicator ${estadoClass}"></div>
                `;
                
                grid.appendChild(card);
            });
        });
    }

    function formatDateDisplay(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        const options = { weekday: 'long', day: 'numeric', month: 'long' };
        return date.toLocaleDateString('es-ES', options);
    }

    function formatTimeDisplay(timeString) {
        if (!timeString) return 'N/A';
        return timeString.substring(0, 5);
    }

    function getEstadoTexto(estado) {
        const estados = {
            'disponible': 'Disponible',
            'reservada': 'Reservada',
            'ocupada': 'Ocupada',
            'cancelada': 'Cancelada',
            'mantencion': 'Mantención'
        };
        return estados[estado] || estado;
    }

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

    function selectReserva(id) {
        // Quitar selección anterior
        document.querySelectorAll('.reserva-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Seleccionar nueva
        event.currentTarget.classList.add('selected');
        reservaSeleccionada = id;
        
        // Cargar detalle real si existe id_disponibilidad
        const selectedReserva = reservasData.find(r => 
            r.id_disponibilidad == id || 
            (`${r.id_cancha}_${r.fecha}_${r.hora_inicio}` == id)
        );
        
        if (selectedReserva && selectedReserva.id_disponibilidad) {
            cargarDetalleReserva(selectedReserva.id_disponibilidad);
        } else {
            mostrarDetalleDisponibilidad(selectedReserva);
        }
    }

    async function cargarDetalleReserva(id) {
        try {
            const formData = new FormData();
            formData.append('id_disponibilidad', id);
            
            const response = await fetch('../api/canchaboard.php?action=get_detalle_reserva', {
                method: 'POST',
                body: formData
            });
            
            const detalle = await response.json();
            
            if (detalle.error) {
                throw new Error(detalle.error);
            }
            
            mostrarDetalleReserva(detalle);
            
        } catch (error) {
            console.error('Error al cargar detalle:', error);
            document.getElementById('detalleContent').innerHTML = '<p>Error al cargar el detalle</p>';
        }
    }

    function mostrarDetalleDisponibilidad(reserva) {
        if (!reserva) {
            document.getElementById('detalleContent').innerHTML = '<p>Disponibilidad básica</p>';
            return;
        }
        
        document.getElementById('detalleContent').innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Cancha:</span> 
                <span>${reserva.nro_cancha || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Deporte:</span> 
                <span>${reserva.id_deporte || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha/Hora:</span> 
                <span>${formatDateDisplay(reserva.fecha)} ${formatTimeDisplay(reserva.hora_inicio)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado:</span> 
                <span>${getEstadoTexto(reserva.estado_disponibilidad || 'disponible')}</span>
            </div>
            <div class="detail-item" style="margin-top: 1rem; color: #00cc66; font-weight: bold;">
                ✅ Disponible para reservar
            </div>
        `;
    }

    function mostrarDetalleReserva(detalle) {
        const pagoColor = {
            'pagado': '#4CAF50',
            'pendiente': '#FF9800',
            'reembolsado': '#2196F3',
            'fallido': '#F44336'
        };
        
        const pagoTexto = {
            'pagado': 'Pagado',
            'pendiente': 'Pendiente',
            'reembolsado': 'Reembolsado',
            'fallido': 'Fallido'
        };
        
        document.getElementById('detalleContent').innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Cancha:</span> 
                <span>${detalle.nro_cancha || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Club:</span> 
                <span>${detalle.nombre_club || 'Particular'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Responsable:</span> 
                <span>${detalle.nombre_responsable || detalle.email_cliente || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Teléfono:</span> 
                <span>${detalle.telefono_cliente || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha/Hora:</span> 
                <span>${formatDateDisplay(detalle.fecha)} ${formatTimeDisplay(detalle.hora_inicio)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Monto:</span> 
                <span>$${detalle.monto_total || '0'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado Pago:</span> 
                <span style="color: ${pagoColor[detalle.estado_pago] || '#666'};">
                    ${pagoTexto[detalle.estado_pago] || detalle.estado_pago}
                </span>
            </div>
            ${detalle.notas ? `
            <div class="detail-item">
                <span class="detail-label">Notas:</span> 
                <span>${detalle.notas}</span>
            </div>` : ''}
        `;
    }

    async function anularReserva() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        
        if (confirm('¿Estás seguro de anular esta reserva? Esta acción no se puede deshacer.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'anular');
                formData.append('id_disponibilidad', reservaSeleccionada);
                
                const response = await fetch('../api/gestion_reservas.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Reserva anulada correctamente');
                    cargarReservasConRango(30);
                    document.getElementById('detalleContent').innerHTML = '<p>Selecciona una reserva para ver detalles</p>';
                    reservaSeleccionada = null;
                } else {
                    throw new Error(result.message || 'Error al anular');
                }
                
            } catch (error) {
                console.error('Error al anular:', error);
                alert('Error al anular la reserva: ' + error.message);
            }
        }
    }

    function aplicarFiltros() {
        const deporte = document.getElementById('filtroDeporte').value;
        const estado = document.getElementById('filtroEstado').value;
        
        let datosFiltrados = [...reservasData];
        
        if (deporte) {
            datosFiltrados = datosFiltrados.filter(r => r.id_deporte === deporte);
        }
        
        if (estado) {
            datosFiltrados = datosFiltrados.filter(r => (r.estado_disponibilidad || 'disponible') === estado);
        }
        
        renderizarReservas(datosFiltrados);
    }

    // Funciones placeholder para otras acciones
    function cancelarReserva() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        alert('Funcionalidad de cancelación en desarrollo');
    }

    function cambiarCancha() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        alert('Funcionalidad de cambio de cancha en desarrollo');
    }

    function enviarMensaje() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        alert('Sistema de notificaciones integrado en desarrollo');
    }

    function enviarCorreo() {
        if (!reservaSeleccionada) {
            alert('Selecciona una reserva primero');
            return;
        }
        alert('Sistema de correo Brevo integrado en desarrollo');
    }

    function crearCampeonato() {
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
    }

    // Ahora sí, cargar los datos iniciales
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
            document.getElementById('reservasGrid').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">Error al cargar las reservas</div>';
        }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        cargarReservasConRango(30);
    });

    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroFecha').addEventListener('change', function() {
        const valor = this.value;
        let rangoDias = 30;
        
        if (valor === 'hoy') rangoDias = 0;
        else if (valor === 'mañana') rangoDias = 1;
        else if (valor === 'semana') rangoDias = 7;
        else if (valor === 'mes') rangoDias = 30;
        
        cargarReservasConRango(rangoDias);
    });
</script>
</body>
</html>