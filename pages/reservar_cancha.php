<?php
// Configuración robusta de sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Validación de sesión
if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
    // Fallback con cookies
    if (isset($_COOKIE['cancha_id_socio']) && isset($_COOKIE['cancha_club_id'])) {
        $_SESSION['id_socio'] = $_COOKIE['cancha_id_socio'];
        $_SESSION['club_id'] = $_COOKIE['cancha_club_id'];
    } else {
        header('Location: ../index.php');
        exit;
    }
}

$id_socio = $_SESSION['id_socio'];
$club_id = $_SESSION['club_id'];

require_once __DIR__ . '/../includes/config.php';

// Verificar que el socio exista
$stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
$stmt->execute([$id_socio, $club_id]);
if (!$stmt->fetch()) {
    header('Location: ../index.php');
    exit;
}

// Obtener datos del usuario
$stmt_user = $pdo->prepare("
    SELECT 
        s.alias, s.email, s.celular,
        c.nombre as nombre_club, c.logo as logo_club
    FROM socios s
    JOIN clubs c ON s.id_club = c.id_club
    WHERE s.id_socio = ? AND c.id_club = ?
");
$stmt_user->execute([$id_socio, $club_id]);
$usuario_data = $stmt_user->fetch();

if (!$usuario_data) {
    header('Location: ../index.php');
    exit;
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
    
    .estado-disponible { background: #FFD700; }
    .estado-reservada { background: #9C27B0; }
    .estado-ocupada { background: #4CAF50; }
    .estado-cancelada { background: #F44336; }
    
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
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" style="color: #ffcc00; text-decoration: none;">← Dashboard</a>
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

  <script>
    // Datos del usuario - INYECCIÓN SEGURA
    const userData = <?= json_encode([
        'club' => $usuario_data['nombre_club'] ?? '',
        'responsable' => $usuario_data['alias'] ?? '',
        'correo' => $usuario_data['email'] ?? '',
        'telefono' => $usuario_data['celular'] ?? ''
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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

    // Funciones principales
    let reservaSeleccionada = null;
    let reservasData = [];

    async function cargarDisponibilidad(filtros = {}) {
        try {
            const formData = new FormData();
            formData.append('deporte', filtros.deporte || '');
            formData.append('recinto', filtros.recinto || '');
            formData.append('rango', filtros.rango || 'semana');
            
            const response = await fetch('../api/reservas_club.php?action=get_disponibilidad', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            // Verificar si la respuesta es JSON válido
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('La API no devolvió JSON válido');
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            reservasData = data;
            renderizarDisponibilidad(reservasData);
            
        } catch (error) {
            console.error('Error al cargar disponibilidad:', error);
            document.getElementById('reservasGrid').innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: white;">
                    Error al cargar la disponibilidad: ${error.message}
                </div>
            `;
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
                if (item.estado !== 'disponible') return;
                
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
        document.querySelectorAll('.reserva-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        event.currentTarget.classList.add('selected');
        reservaSeleccionada = item;
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

    function reservarCancha() {
        if (!validarReservaSeleccionada()) return;
        alert('Funcionalidad de reserva en desarrollo');
    }

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

    document.addEventListener('DOMContentLoaded', function() {
        cargarDisponibilidad({ rango: 'semana' });
    });
  </script>
</body>
</html>