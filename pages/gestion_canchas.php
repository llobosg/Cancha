<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación de administrador de recinto
if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
    header('Location: ../index.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Obtener datos del recinto
$stmt = $pdo->prepare("SELECT nombre, logorecinto FROM recintos_deportivos WHERE id_recinto = ?");
$stmt->execute([$id_recinto]);
$recinto = $stmt->fetch();

if (!$recinto) {
    header('Location: ../index.php');
    exit;
}

// Obtener todas las canchas del recinto
$stmt = $pdo->prepare("
    SELECT * FROM canchas 
    WHERE id_recinto = ? 
    ORDER BY nro_cancha, id_deporte
");
$stmt->execute([$id_recinto]);
$canchas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gestión de Canchas - <?= htmlspecialchars($recinto['nombre']) ?> | Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      color: white;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }
    
    .header-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 2rem;
      gap: 2rem;
    }
    
    .titles-section {
      flex: 1;
    }
    
    .main-title {
      color: #FFD700;
      font-size: 2.8rem;
      margin: 0;
      font-weight: bold;
    }
    
    .subtitle {
      color: rgba(255,255,255,0.9);
      font-size: 1.2rem;
      margin: 0.5rem 0;
    }
    
    .recinto-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .recinto-logo {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      object-fit: cover;
      background: rgba(255,255,255,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    
    .close-btn {
      font-size: 2.5rem;
      color: #ffcc00;
      text-decoration: none;
      font-weight: bold;
      align-self: flex-start;
    }
    
    .search-section {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
    }
    
    .search-input {
      width: 100%;
      padding: 0.8rem;
      border: 2px solid #071289;
      border-radius: 6px;
      font-size: 1rem;
      color: #071289;
    }
    
    .search-results {
      margin-top: 1rem;
      max-height: 200px;
      overflow-y: auto;
      border: 1px solid #ccc;
      border-radius: 4px;
      display: none;
    }
    
    .search-result-item {
      padding: 0.5rem;
      cursor: pointer;
      border-bottom: 1px solid #eee;
      color: #040942ff;
    }
    
    .search-result-item:hover {
      background: #f0f0f0;
    }
    
    .form-section {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      margin-bottom: 2rem;
    }
    
    .form-title {
      color: #003366;
      margin-bottom: 1.5rem;
      font-size: 1.4rem;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
    }
    
    .table-section {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
    }
    
    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .btn-add {
      background: #00cc66;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th, td {
      padding: 0.8rem;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    
    th {
      color: #071289;
      font-weight: bold;
    }
    
    .action-icons {
      display: flex;
      gap: 0.5rem;
    }
    
    .action-icon {
      cursor: pointer;
      color: #071289;
      font-size: 1.2rem;
    }
    
    /* Submodal */
    .submodal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1001;
    }
    
    .submodal-content {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      max-width: 600px;
      width: 90%;
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 28px;
      color: #999;
      cursor: pointer;
    }
    
    .btn-submit {
      background: #071289;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      width: 100%;
    }
    
    /* Responsive móvil */
    @media (max-width: 768px) {
      .dashboard-container {
        padding: 1rem;
      }
      
      .header-section {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      
      .recinto-info {
        flex-direction: column;
        gap: 0.5rem;
      }
      
      .main-title {
        font-size: 2.2rem;
      }
      
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .submodal-content {
        padding: 1.5rem;
        margin: 1rem;
      }
      
      th, td {
        padding: 0.6rem;
        font-size: 0.9rem;
      }
      
      .table-header {
        flex-direction: column;
        gap: 1rem;
      }
      
      .btn-add {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Encabezado -->
    <div class="header-section">
      <div class="titles-section">
        <h1 class="main-title">⚽ Cancha</h1>
        <p class="subtitle">Administración de Recintos Deportivos</p>
        <p class="subtitle">Gestión de canchas Recinto Deportivo <?= htmlspecialchars($recinto['nombre']) ?></p>
        
        <div class="recinto-info">
          <?php if (!empty($recinto['logorecinto'])): ?>
            <?php 
            $logo_path = __DIR__ . '/../uploads/logos_recintos/' . $recinto['logorecinto'];
            if (file_exists($logo_path)): ?>
              <img src="../uploads/logos_recintos/<?= htmlspecialchars($recinto['logorecinto']) ?>" 
                  alt="Logo <?= htmlspecialchars($recinto['nombre']) ?>"
                  class="recinto-logo">
            <?php else: ?>
              <div class="recinto-logo">🏟️</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="recinto-logo">🏟️</div>
          <?php endif; ?>
          <strong><?= htmlspecialchars($recinto['nombre']) ?></strong>
        </div>
      </div>
      
      <a href="recinto_dashboard.php" class="close-btn" title="Volver al dashboard">×</a>
    </div>

    <!-- Sección superior: Buscador inteligente -->
    <div class="search-section">
      <input type="text" id="searchCancha" class="search-input" placeholder="Buscar canchas por número, nombre o deporte..." onkeyup="searchCanchas()">
      <div id="searchResults" class="search-results"></div>
    </div>
    
    <!-- Sección intermedia: Formulario para crear/editar cancha -->
    <div class="form-section">
      <h2 class="form-title">Registrar Nueva Cancha</h2>
      <a href="recinto_dashboard.php" class="close-btn" title="Volver al dashboard">×</a>
      
      <form id="canchaForm" onsubmit="saveCancha(event)">
        <input type="hidden" id="canchaId" name="id_cancha">
        <input type="hidden" id="actionType" name="action" value="insert">
        <input type="hidden" id="recintoId" name="id_recinto" value="<?= $id_recinto ?>">
        
        <div class="form-grid">
          <div class="form-group">
            <label for="nroCancha">Número *</label>
            <input type="text" id="nroCancha" name="nro_cancha" required placeholder="Ej: Cancha A, Pádel 1">
          </div>
          
          <div class="form-group">
            <label for="nombreCancha">Nombre Descriptivo</label>
            <input type="text" id="nombreCancha" name="nombre_cancha" placeholder="Ej: Cancha principal, Pista central">
          </div>
          
          <div class="form-group">
            <label for="deporte">Deporte *</label>
            <select id="deporte" name="id_deporte" required>
              <option value="">Seleccionar deporte</option>
              <option value="futbol">Fútbol</option>
              <option value="futbolito">Futbolito</option>
              <option value="futsal">Futsal</option>
              <option value="tenis">Tenis</option>
              <option value="padel">Pádel</option>
              <option value="voleyball">Voleyball</option>
              <option value="otro">Quincho/Otro</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="valorArriendo">Valor Arriendo ($)*</label>
            <input type="number" id="valorArriendo" name="valor_arriendo" required min="0" step="100">
          </div>
          
          <div class="form-group">
            <label for="duracionBloque">Duración Bloque (minutos)*</label>
            <input type="number" id="duracionBloque" name="duracion_bloque" required min="60" max="180" value="60" 
                    title="La duración mínima es de 60 minutos">
          </div>
          
          <div class="form-group">
            <label for="horaInicio">Hora Inicio *</label>
            <input type="time" id="horaInicio" name="hora_inicio" required value="07:00">
          </div>
          
          <div class="form-group">
            <label for="horaFin">Hora Fin *</label>
            <input type="time" id="horaFin" name="hora_fin" required value="21:00">
          </div>
          
          <div class="form-group">
            <label for="capacidadJugadores">Capacidad Jugadores</label>
            <input type="number" id="capacidadJugadores" name="capacidad_jugadores" min="1" value="10">
          </div>
          
          <div class="form-group">
            <label for="diasDisponibles">Días Disponibles *</label>
            <select id="diasDisponibles" name="dias_disponibles[]" multiple required style="height: 120px;">
              <option value="lunes">Lunes</option>
              <option value="martes">Martes</option>
              <option value="miercoles">Miércoles</option>
              <option value="jueves">Jueves</option>
              <option value="viernes">Viernes</option>
              <option value="sabado">Sábado</option>
              <option value="domingo">Domingo</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="activa">Activa</label>
            <select id="activa" name="activa">
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
              <option value="Operativa">Operativa</option>
              <option value="Reservada">Reservada</option>
              <option value="Mantención">Mantención</option>
              <option value="Construcción">Construcción</option>
            </select>
          </div>
        </div>
        
        <button type="submit" class="btn-submit">Guardar Cancha</button>
      </form>
    </div>

    <!-- Sección inferior: Tabla de canchas -->
    <div class="table-section">
      <div class="table-header">
        <h3>Canchas Registradas</h3>
        <button class="btn-add" onclick="resetForm()">+ Agregar Cancha</button>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Nro Cancha</th>
            <th>Nombre</th>
            <th>Deporte</th>
            <th>Duración</th>
            <th>Hora Desde</th>
            <th>Hora Hasta</th>
            <th>Días</th>
            <th>Activa</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($canchas as $cancha): ?>
          <tr>
            <td style="color: #040942ff;"><?= htmlspecialchars($cancha['nro_cancha']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($cancha['nombre_cancha'] ?? '') ?></td>
            <td style="color: #040942ff;"><?= ucfirst(htmlspecialchars($cancha['id_deporte'])) ?></td>
            <td style="color: #040942ff;"><?= $cancha['duracion_bloque'] ?> min</td>
            <td style="color: #040942ff;"><?= htmlspecialchars($cancha['hora_inicio']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($cancha['hora_fin']) ?></td>
            <td style="color: #040942ff;">
              <?php 
              $dias = json_decode($cancha['dias_disponibles'], true);
              echo $dias ? implode(', ', array_slice($dias, 0, 2)) . (count($dias) > 2 ? '...' : '') : 'Sin días';
              ?>
            </td>
            <td style="color: #040942ff;"><?= $cancha['activa'] ? 'Sí' : 'No' ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($cancha['estado']) ?></td>
            <td class="action-icons">
              <span class="action-icon" onclick="editCancha(
                <?= $cancha['id_cancha'] ?>,
                '<?= addslashes(htmlspecialchars($cancha['nro_cancha'])) ?>',
                '<?= addslashes(htmlspecialchars($cancha['nombre_cancha'] ?? '')) ?>',
                '<?= addslashes(htmlspecialchars($cancha['id_deporte'])) ?>',
                <?= $cancha['valor_arriendo'] ?>,
                <?= $cancha['duracion_bloque'] ?>,
                '<?= addslashes(htmlspecialchars($cancha['hora_inicio'])) ?>',
                '<?= addslashes(htmlspecialchars($cancha['hora_fin'])) ?>',
                <?= $cancha['capacidad_jugadores'] ?>,
                '<?= addslashes(htmlspecialchars(json_encode(json_decode($cancha['dias_disponibles'], true)))) ?>',
                <?= $cancha['activa'] ?>,
                '<?= addslashes(htmlspecialchars($cancha['estado'])) ?>'
              )" title="Editar">✏️</span>
              <span class="action-icon" onclick="deleteCancha(<?= $cancha['id_cancha'] ?>)" title="Eliminar">🗑️</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    function searchCanchas() {
      const query = document.getElementById('searchCancha').value.toLowerCase();
      const resultsDiv = document.getElementById('searchResults');
      
      if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      
      const canchas = <?php echo json_encode($canchas); ?>;
      const filtered = canchas.filter(c => 
        c.nro_cancha.toLowerCase().includes(query) || 
        (c.nombre_cancha && c.nombre_cancha.toLowerCase().includes(query)) ||
        c.id_deporte.toLowerCase().includes(query)
      );
      
      if (filtered.length > 0) {
        resultsDiv.innerHTML = filtered.map(c => 
          `<div class="search-result-item" onclick="selectCancha(${c.id_cancha}, '${c.nro_cancha}', '${c.nombre_cancha || ''}', '${c.id_deporte}', ${c.valor_arriendo}, ${c.duracion_bloque}, '${c.hora_inicio}', '${c.hora_fin}', ${c.capacidad_jugadores}, '${JSON.stringify(JSON.parse(c.dias_disponibles))}', ${c.activa}, '${c.estado}')">${c.nro_cancha} - ${c.id_deporte}</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectCancha(id, nro_cancha, nombre_cancha, id_deporte, valor_arriendo, duracion_bloque, hora_inicio, hora_fin, capacidad_jugadores, dias_disponibles, activa, estado) {
      editCancha(id, nro_cancha, nombre_cancha, id_deporte, valor_arriendo, duracion_bloque, hora_inicio, hora_fin, capacidad_jugadores, dias_disponibles, activa, estado);
      document.getElementById('searchCancha').value = '';
      document.getElementById('searchResults').style.display = 'none';
    }
    
    function editCancha(id, nro_cancha, nombre_cancha, id_deporte, valor_arriendo, duracion_bloque, hora_inicio, hora_fin, capacidad_jugadores, dias_disponibles, activa, estado) {
        document.getElementById('actionType').value = 'update';
        document.getElementById('canchaId').value = id;
        document.getElementById('nroCancha').value = nro_cancha;
        document.getElementById('nombreCancha').value = nombre_cancha;
        document.getElementById('deporte').value = id_deporte;
        document.getElementById('valorArriendo').value = valor_arriendo;
        document.getElementById('duracionBloque').value = Math.max(60, duracion_bloque); // Asegurar mínimo 60
        document.getElementById('horaInicio').value = hora_inicio;
        document.getElementById('horaFin').value = hora_fin;
        document.getElementById('capacidadJugadores').value = capacidad_jugadores;
        document.getElementById('activa').value = activa;
        document.getElementById('estado').value = estado;
        
        // Manejar días disponibles - versión mejorada
        const diasSelect = document.getElementById('diasDisponibles');
        const diasArray = JSON.parse(dias_disponibles);
        
        // Desmarcar todos primero
        for (let i = 0; i < diasSelect.options.length; i++) {
            diasSelect.options[i].selected = false;
        }
        
        // Marcar los seleccionados
        if (Array.isArray(diasArray)) {
            for (let i = 0; i < diasSelect.options.length; i++) {
            if (diasArray.includes(diasSelect.options[i].value)) {
                diasSelect.options[i].selected = true;
            }
            }
        }
        
        // Scroll to form
        document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
    
    function resetForm() {
      document.getElementById('actionType').value = 'insert';
      document.getElementById('canchaId').value = '';
      document.getElementById('canchaForm').reset();
      document.getElementById('diasDisponibles').selectedIndex = -1;
    }
    
    function deleteCancha(id) {
      if (confirm('¿Estás seguro de eliminar esta cancha?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id_cancha', id);
        formData.append('id_recinto', <?= $id_recinto ?>);
        
        fetch('../api/gestion_canchas.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        });
      }
    }
    
    function saveCancha(event) {
      event.preventDefault();

      const duracion = parseInt(document.getElementById('duracionBloque').value);
        if (duracion < 60) {
            alert('El bloque mínimo debe ser de 60 minutos');
            return;
        }
      
      const formData = new FormData();
      formData.append('action', document.getElementById('actionType').value);
      formData.append('id_cancha', document.getElementById('canchaId').value);
      formData.append('id_recinto', document.getElementById('recintoId').value);
      formData.append('nro_cancha', document.getElementById('nroCancha').value);
      formData.append('nombre_cancha', document.getElementById('nombreCancha').value);
      formData.append('id_deporte', document.getElementById('deporte').value);
      formData.append('valor_arriendo', document.getElementById('valorArriendo').value);
      formData.append('duracion_bloque', document.getElementById('duracionBloque').value);
      formData.append('hora_inicio', document.getElementById('horaInicio').value);
      formData.append('hora_fin', document.getElementById('horaFin').value);
      formData.append('capacidad_jugadores', document.getElementById('capacidadJugadores').value);
      formData.append('activa', document.getElementById('activa').value);
      formData.append('estado', document.getElementById('estado').value);
      
      // Obtener días seleccionados
      const diasSelect = document.getElementById('diasDisponibles');
      const diasSeleccionados = [];
      for (let i = 0; i < diasSelect.options.length; i++) {
        if (diasSelect.options[i].selected) {
          diasSeleccionados.push(diasSelect.options[i].value);
        }
      }
      formData.append('dias_disponibles', JSON.stringify(diasSeleccionados));
      
      fetch('../api/gestion_canchas.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar la cancha');
      });
    }
    
    // Cerrar resultados de búsqueda al hacer clic fuera
    document.addEventListener('click', function(e) {
      if (!e.target.closest('#searchCancha') && !e.target.closest('#searchResults')) {
        document.getElementById('searchResults').style.display = 'none';
      }
    });
  </script>
</body>
</html>