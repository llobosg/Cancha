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
    /* Eliminamos height fija para permitir alineación natural */
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
  .estado-mantencion { background: #FF9800; } /* Naranja */
  
  /* Panel lateral - CORREGIDO */
    .detail-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: sticky;
    top: 120px;
    align-self: flex-start;
    height: fit-content;
    max-height: calc(100vh - 140px);
    overflow: visible;
    }

    .detail-section {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    width: 100%;
    overflow-y: auto;
    max-height: 350px; /* Aumentado para más espacio */
    }

    .actions-section {
    background: white;
    padding: 1rem;
    border-radius: 12px;
    width: 100%;
    overflow-y: auto;
    max-height: 320px; /* Aumentado para que quepan todas las opciones */
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
    color: black;
  }
  
  .action-btn {
    padding: 0.5rem;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    text-align: left;
    transition: background 0.2s;
    color: #333; /* Texto negro por defecto */
    }

    .action-btn:hover {
    background: rgba(255,255,255,0.2);
    color: #000; /* Texto negro en hover */
    }
  
  .btn-anular { background: #F44336; color: white; }
  .btn-cancelar { background: #FF9800; color: white; }
  .btn-cambiar { background: #2196F3; color: white; }
  .btn-mensaje { background: #4CAF50; color: white; }
  .btn-campeonato { background: #00cc66; color: white; }
  
  /* Submodal */
  .submodal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 1001;
  }
  
  .submodal-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    max-width: 500px;
    position: relative;
  }
  
  .close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 28px;
    cursor: pointer;
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
</style>
</head>
<body>
  <div class="header">
    <div class="main-title-section">
      <div class="logo-corporativo">⚽</div>
      <h1 class="main-title">Cancha</h1>
    </div>
    <div>
      <a href="recinto_dashboard.php" style="color: #ffcc00; text-decoration: none;">← Dashboard</a>
    </div>
  </div>
  
  <div class="dashboard-container" style="margin-top: 70px;">
    <div>
      <div class="controls-section">
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
        
        <div class="actions-section">
            <h3 class="detail-title">⚙️ Acciones</h3>
            <div class="actions-grid">
                <button class="action-btn btn-anular" onclick="anularReserva()">🗑️ Anular</button>
                <button class="action-btn btn-cancelar" onclick="cancelarReserva()">❌ Cancelar Reserva</button>
                <button class="action-btn btn-cambiar" onclick="cambiarCancha()">🔄 Cambiar de Cancha</button>
                <button class="action-btn btn-mensaje" onclick="enviarMensaje()">💬 Enviar Mensaje</button>
                <button class="action-btn btn-campeonato" onclick="crearCampeonato()">🏆 Crear Campeonato</button>
                
                <!-- BOTÓN PAGAR (Nuevo) -->
                <button id="btnPagar" class="action-btn" style="background:#4CAF50; color:white; display:none;" onclick="abrirModalPago()">
                    💳 Pagar Reserva
                </button>
            </div>
        </div>

        <!-- Modal de Pago (Nuevo) -->
        <div id="modalPago" class="submodal" style="display:none;">
            <div class="submodal-content">
                <span class="close-modal" onclick="cerrarModalPago()">&times;</span>
                <h3 style="color:#071289; margin-bottom:1rem;">💳 Pagar Reserva</h3>
                <div id="infoPago" style="margin-bottom:1rem; font-size:0.9rem; color:#333;"></div>
                
                <form id="formPago">
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label style="font-weight:bold; display:block; margin-bottom:0.3rem;">Método de Pago</label>
                        <select name="metodo_pago" id="metodoPago" required style="width:100%; padding:0.5rem; border-radius:4px; border:1px solid #ccc;">
                            <option value="">Seleccionar...</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="webpay">Webpay / Tarjeta</option>
                            <option value="efectivo">Efectivo en Recinto</option>
                            <option value="convenio">Convenio Club</option>
                        </select>
                    </div>
                    
                    <div id="campoTransaccion" class="form-group" style="display:none; margin-bottom:1rem;">
                        <label style="font-weight:bold; display:block; margin-bottom:0.3rem;">ID Transacción / Comprobante</label>
                        <input type="text" name="transaccion_id" id="transaccionId" placeholder="Ej: 123456789" style="width:100%; padding:0.5rem; border-radius:4px; border:1px solid #ccc;">
                    </div>
                    
                    <button type="submit" class="btn-submit" style="width:100%; background:#4CAF50;">Confirmar Pago</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Submodal para mensaje - CORREGIDO -->
    <div id="mensajeModal" class="submodal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1001;">
        <div class="submodal-content" style="background:white; padding:2rem; border-radius:16px; max-width:500px; position:relative;">
            <span class="close-modal" onclick="closeMensajeModal()" style="position:absolute; top:15px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
            <h3>Enviar Mensaje</h3>
            <form id="mensajeForm">
            <div class="form-group">
                <label for="mensajeTexto">Mensaje *</label>
                <textarea id="mensajeTexto" name="mensaje" rows="4" required style="width:100%; padding:0.6rem; border:1px solid #ccc; border-radius:5px; color:#071289;"></textarea>
            </div>
            <button type="submit" class="btn-submit" style="width:100%;">Enviar Mensaje y Correo</button>
            </form>
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
        document.querySelectorAll('.reserva-card').forEach(card => card.classList.remove('selected'));
        
        // Seleccionar nueva (si existe el evento)
        if (event?.currentTarget) {
            event.currentTarget.classList.add('selected');
        }
        
        reservaSeleccionada = id;
        console.log("🖱️ Ficha seleccionada ID:", id);

        // Buscar la reserva completa en los datos cargados
        const selectedReserva = reservasData.find(r => {
            // Caso A: Coincidencia por id_disponibilidad (numérico)
            if (r.id_disponibilidad && r.id_disponibilidad !== 'null' && r.id_disponibilidad !== null) {
                return r.id_disponibilidad.toString() === id.toString();
            }
            // Caso B: Coincidencia por clave compuesta (fallback)
            return `${r.id_cancha}_${r.fecha}_${r.hora_inicio}` === id.toString();
        });
        
        if (selectedReserva) {
            console.log("📄 Datos encontrados:", selectedReserva);
            
            // Verificar si es una reserva REAL (tiene id_reserva y estado confirmado/reservado)
            // O si tiene un id_disponibilidad válido de la tabla disponibilidad_canchas
            const tieneIdDisponibilidad = selectedReserva.id_disponibilidad && selectedReserva.id_disponibilidad !== 'null';
            const tieneReservaReal = selectedReserva.id_reserva && selectedReserva.id_reserva !== 'null';
            
            if (tieneIdDisponibilidad) {
                // Tiene ID de disponibilidad → Cargar detalle desde BD
                cargarDetalleReserva(selectedReserva.id_disponibilidad, selectedReserva.id_reserva || null);
            } else if (tieneReservaReal) {
                // Tiene reserva pero falta ID disponibilidad (raro, pero posible)
                // Intentamos cargar igual, aunque podría fallar si la tabla disponibilidad es clave
                alert("⚠️ Error de datos: Reserva encontrada pero falta ID de disponibilidad.");
            } else {
                // Es solo un bloque de disponibilidad generado (sin reserva ni ID en BD)
                mostrarDetalleDisponibilidad(selectedReserva);
            }
        } else {
            document.getElementById('detalleContent').innerHTML = '<p style="color:#F44336;">Reserva no encontrada en datos locales.</p>';
        }
    }

    async function cargarDetalleReserva(id_disponibilidad, id_reserva) {
        if (!id_disponibilidad) {
            document.getElementById('detalleContent').innerHTML = '<p style="color:#FF9800;">⚠️ No hay detalles disponibles para este bloque (es solo disponibilidad).</p>';
            return;
        }

        console.log("📡 Solicitando detalle para ID Disponibilidad:", id_disponibilidad);

        try {
            const formData = new FormData();
            formData.append('id_disponibilidad', id_disponibilidad);
            if (id_reserva) formData.append('id_reserva', id_reserva);
            
            const response = await fetch('../api/canchaboard.php?action=get_detalle_reserva', {
                method: 'POST',
                body: formData
            });
            
            const detalle = await response.json();
            
            if (detalle.error) {
                throw new Error(detalle.error);
            }
            
            console.log("✅ Detalle recibido:", detalle);
            mostrarDetalleReserva(detalle);
            
        } catch (error) {
            console.error(' Error al cargar detalle:', error);
            document.getElementById('detalleContent').innerHTML = `<p style="color:#F44336;">Error: ${error.message}</p>`;
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
        console.log(" Renderizando detalle con datos:", detalle);

        // Función auxiliar para evitar errores con valores nulos
        const val = (v, def = 'N/A') => (v !== null && v !== undefined && v !== '') ? v : def;
        const money = (v) => '$' + parseInt(v || 0).toLocaleString();
        
        // Mapeo de estados para colores
        const estadoPagoColor = {
            'pagado': '#4CAF50', 'pendiente': '#FF9800', 
            'reembolsado': '#2196F3', 'fallido': '#F44336'
        };
        const estadoReservaColor = {
            'confirmada': '#4CAF50', 'pendiente': '#FF9800', 
            'cancelada': '#F44336', 'completada': '#9E9E9E'
        };

        // Construcción del HTML seguro
        const html = `
            <div style="font-size: 0.9rem; line-height: 1.6;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
                    <div><strong> Fecha:</strong> ${val(detalle.fecha)}</div>
                    <div><strong>⏰ Hora:</strong> ${val(detalle.hora_inicio).substring(0,5)} - ${val(detalle.hora_fin).substring(0,5)}</div>
                    <div><strong>🏟️ Cancha:</strong> ${val(detalle.nombre_cancha)} (Nro ${val(detalle.nro_cancha)})</div>
                    <div><strong>🎾 Deporte:</strong> ${val(detalle.id_deporte).toUpperCase()}</div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 1rem 0;">

                <div style="margin-bottom: 1rem;">
                    <div><strong>👤 Cliente:</strong> ${val(detalle.nombre_responsable || detalle.email_cliente)}</div>
                    <div><strong>📞 Teléfono:</strong> ${val(detalle.telefono_cliente)}</div>
                    <div><strong> Email:</strong> ${val(detalle.email_cliente)}</div>
                    ${detalle.nombre_club ? `<div><strong> Club:</strong> ${val(detalle.nombre_club)}</div>` : ''}
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 1rem 0;">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div>
                        <strong>💰 Monto Total:</strong><br>
                        <span style="font-size: 1.1rem; color: #071289; font-weight: bold;">${money(detalle.monto_total)}</span>
                    </div>
                    <div>
                        <strong>📝 Tipo Reserva:</strong><br>
                        ${val(detalle.tipo_reserva).toUpperCase()}
                    </div>
                </div>

                <div style="margin-top: 0.8rem; display:grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div>
                        <strong>🟢 Estado Reserva:</strong><br>
                        <span style="color: ${estadoReservaColor[detalle.estado_reserva] || '#333'}; font-weight: bold;">
                            ${val(detalle.estado_reserva).toUpperCase()}
                        </span>
                    </div>
                    <div>
                        <strong>💳 Estado Pago:</strong><br>
                        <span style="color: ${estadoPagoColor[detalle.estado_pago] || '#333'}; font-weight: bold;">
                            ${val(detalle.estado_pago).toUpperCase()}
                        </span>
                    </div>
                </div>

                ${detalle.notas ? `
                <div style="margin-top: 1rem; background: #fff3cd; padding: 0.5rem; border-radius: 4px; border-left: 4px solid #ffc107;">
                    <strong> Notas:</strong> ${val(detalle.notas)}
                </div>` : ''}
                
                ${detalle.id_convenio ? `
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #666;">
                    ID Convenio: ${detalle.id_convenio}
                </div>` : ''}
            </div>
        `;

        // Inyectar HTML
        const container = document.getElementById('detalleContent');
        if (container) {
            container.innerHTML = html;
            console.log("✅ Detalle renderizado correctamente");
        } else {
            console.error("❌ No se encontró el contenedor #detalleContent");
        }
    }

    async function anularReserva() {
        if (!validarReservaActiva()) return;
        
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
                    showToast('✅ Reserva anulada correctamente', 'success');
                    cargarReservasConRango(0);
                    document.getElementById('detalleContent').innerHTML = '<p>Selecciona una reserva para ver detalles</p>';
                    reservaSeleccionada = null;
                } else {
                    throw new Error(result.message || 'Error al anular');
                }
                
            } catch (error) {
                console.error('Error al anular:', error);
                showToast(`❌ Error: ${error.message}`, 'error');
            }
        }
    }

    function cancelarReserva() {
        if (!validarReservaActiva()) return;
        alert('Funcionalidad de cancelación en desarrollo');
    }

    function cambiarCancha() {
        if (!validarReservaActiva()) return;
        alert('Funcionalidad de cambio de cancha en desarrollo');
    }

    function enviarMensaje() {
        if (!validarReservaActiva()) return;
        document.getElementById('mensajeModal').style.display = 'flex';
    }

    // La acción "Crear Campeonato" NO requiere validación de reserva
    function crearCampeonato() {
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
    }

    function closeMensajeModal() {
        document.getElementById('mensajeModal').style.display = 'none';
    }

    async function enviarMensajeYCorreo(formData) {
        try {
            // Aquí iría la lógica para enviar notificación y correo
            // Por ahora simulamos el envío
            alert('Mensaje enviado y correo de respaldo enviado');
            closeMensajeModal();
        } catch (error) {
            console.error('Error al enviar mensaje:', error);
            alert('Error al enviar el mensaje: ' + error.message);
        }
    }

    function crearCampeonato() {
        window.location.href = 'crear_campeonato.php?id_recinto=<?= $id_recinto ?>';
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

    // Función para verificar si hay una reserva real (no solo disponibilidad)
    function validarReservaActiva() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Debes seleccionar una reserva primero', 'warning');
            return false;
        }
        
        // Buscar la reserva en los datos cargados
        const selectedReserva = reservasData.find(r => 
            r.id_disponibilidad == reservaSeleccionada || 
            (`${r.id_cancha}_${r.fecha}_${r.hora_inicio}` == reservaSeleccionada)
        );
        
        // Verificar si es una reserva real (tiene id_disponibilidad válido)
        if (!selectedReserva || !selectedReserva.id_disponibilidad || selectedReserva.id_disponibilidad === 'null') {
            showToast('⚠️ Para ejecutar esta acción debe existir una reserva activa', 'warning');
            return false;
        }
        
        // Verificar que el estado no sea cancelado
        if (selectedReserva.estado_reserva === 'cancelada') {
            showToast('⚠️ No se pueden ejecutar acciones sobre reservas canceladas', 'warning');
            return false;
        }
        
        return true;
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

    // Filtro por fecha: llama a la acción correcta con POST
    document.getElementById('filtroFecha').addEventListener('change', function() {
        aplicarFiltrosConAPI();
    });

    // Filtro por deporte
    document.getElementById('filtroDeporte').addEventListener('change', aplicarFiltrosConAPI);

    // Filtro por estado
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltrosConAPI);

    // Función que llama a la API de filtrado con POST
    async function aplicarFiltrosConAPI() {
    const deporte = document.getElementById('filtroDeporte').value;
    const estado = document.getElementById('filtroEstado').value;
    const fecha = document.getElementById('filtroFecha').value;
    
    console.log('🔍 Filtros enviados:', { deporte, estado, fecha });

    try {
        const formData = new FormData();
        formData.append('action', 'filtrar_reservas');
        formData.append('deporte', deporte);
        formData.append('estado', estado);
        formData.append('fecha', fecha);
        
        // Log de lo que se envía
        for (let pair of formData.entries()) {
            console.log(pair[0]+ ', ' + pair[1]); 
        }

        const response = await fetch('../api/canchaboard.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📡 Respuesta API (Total items):', data.length);
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        reservasData = data;
        renderizarReservas(reservasData);
        
    } catch (error) {
        console.error('Error al aplicar filtros:', error);
        showToast('❌ Error al filtrar reservas', 'error');
    }
}

    // Cargar datos iniciales con "Hoy" por defecto
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer valores por defecto en los selects
        document.getElementById('filtroFecha').value = 'hoy';
        document.getElementById('filtroDeporte').value = '';
        document.getElementById('filtroEstado').value = '';
        
        // Cargar reservas de hoy
        cargarReservasConRango(0);
    });

    document.getElementById('mensajeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const mensaje = document.getElementById('mensajeTexto').value.trim();
        
        if (!mensaje) {
            showToast('⚠️ El mensaje no puede estar vacío', 'warning');
            return;
        }
        
        // Simular envío
        showToast('✅ Mensaje enviado y correo de respaldo enviado', 'success');
        closeMensajeModal();
        document.getElementById('mensajeTexto').value = '';
    });

    // Cerrar modal con escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMensajeModal();
        }
    });

    // Sistema de Toast Notifications
    function showToast(message, type = 'info') {
        // Eliminar toast anterior si existe
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Mostrar toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Ocultar y eliminar después de 3 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    // Función de validación para acciones
    function validarReservaSeleccionada() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Debes seleccionar una reserva primero', 'warning');
            return false;
        }
        return true;
    }
    // === FUNCIONES PARA MODAL DE PAGO ===
    function abrirModalPago() {
        if (!reservaSeleccionada) {
            showToast('⚠️ Selecciona una reserva primero', 'warning');
            return;
        }
        
        // Buscar reserva en datos cargados
        const reserva = reservasData.find(r => 
            r.id_disponibilidad == reservaSeleccionada || 
            `${r.id_cancha}_${r.fecha}_${r.hora_inicio}` == reservaSeleccionada
        );
        
        if (!reserva || !reserva.id_reserva) {
            showToast('⚠️ Esta ficha no corresponde a una reserva pagable', 'warning');
            return;
        }
        
        if (reserva.estado_pago === 'pagado') {
            showToast('✅ Esta reserva ya está pagada', 'info');
            return;
        }
        
        // Mostrar info de pago
        const infoPago = document.getElementById('infoPago');
        infoPago.innerHTML = `
            <strong>Cancha:</strong> ${reserva.nro_cancha || 'N/A'}<br>
            <strong>Fecha:</strong> ${formatDateDisplay(reserva.fecha)} ${formatTimeDisplay(reserva.hora_inicio)}<br>
            <strong>Monto:</strong> $${parseInt(reserva.monto_total || 0).toLocaleString()}<br>
            <strong>Estado:</strong> <span style="color:#FF9800;">Pendiente</span>
        `;
        
        // Mostrar/ocultar campo de transacción según método
        document.getElementById('metodoPago').onchange = function() {
            const campoTrans = document.getElementById('campoTransaccion');
            if (['transferencia', 'webpay'].includes(this.value)) {
                campoTrans.style.display = 'block';
                document.getElementById('transaccionId').required = true;
            } else {
                campoTrans.style.display = 'none';
                document.getElementById('transaccionId').required = false;
            }
        };
        
        // Guardar ID de reserva en el form
        document.getElementById('formPago').dataset.idReserva = reserva.id_reserva;
        
        // Mostrar modal
        document.getElementById('modalPago').style.display = 'flex';
    }

    function cerrarModalPago() {
        document.getElementById('modalPago').style.display = 'none';
        document.getElementById('formPago').reset();
        document.getElementById('campoTransaccion').style.display = 'none';
        document.getElementById('transaccionId').required = false;
    }

    // Manejar submit del form de pago
    document.getElementById('formPago')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const idReserva = this.dataset.idReserva;
        const metodoPago = document.getElementById('metodoPago').value;
        const transaccionId = document.getElementById('transaccionId').value;
        
        if (!idReserva || !metodoPago) {
            showToast('⚠️ Completa todos los campos requeridos', 'warning');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'procesar_pago');
            formData.append('id_reserva', idReserva);
            formData.append('metodo_pago', metodoPago);
            formData.append('transaccion_id', transaccionId || null);
            
            const response = await fetch('../api/gestion_reservas.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('✅ Pago registrado correctamente', 'success');
                cerrarModalPago();
                // Recargar reservas para actualizar estado
                cargarReservasConRango(0);
                // Actualizar detalle si está visible
                if (reservaSeleccionada) {
                    const reservaActualizada = reservasData.find(r => r.id_reserva == idReserva);
                    if (reservaActualizada) {
                        mostrarDetalleReserva(reservaActualizada);
                    }
                }
            } else {
                throw new Error(result.message || 'Error al procesar pago');
            }
            
        } catch (error) {
            console.error('Error al procesar pago:', error);
            showToast(`❌ ${error.message}`, 'error');
        }
    });

    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalPago();
            closeMensajeModal();
        }
    });

    // Cerrar modal al hacer click fuera
    document.getElementById('modalPago')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalPago();
        }
    });
  </script>
</body>
</html>