<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación CEO
if (!isset($_SESSION['ceo_id']) || $_SESSION['ceo_rol'] !== 'ceo_cancha') {
    header('Location: ceo_login.php');
    exit;
}

// Obtener todos los eventos
$stmt = $pdo->query("SELECT id_tipoevento, tipoevento, players FROM tipoeventos ORDER BY tipoevento");
$eventos = $stmt->fetchAll();

$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mantenedor de Eventos - Cancha</title>
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

    .container {
      width: 95%;
      max-width: 800px;
      margin: 0 auto;
      padding: 2rem;
    }

    .back-btn {
      color: white;
      text-decoration: none;
      margin-bottom: 1.5rem;
      display: inline-block;
      font-weight: bold;
    }

    .back-btn:hover {
      text-decoration: underline;
    }

    .section-title {
      color: #003366;
      margin: 1.5rem 0;
      font-size: 1.4rem;
    }

    /* Buscador inteligente */
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
    }

    .search-result-item:hover {
      background: #f0f0f0;
    }

    /* Tabla de eventos */
    .table-section {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
    }

    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
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
      max-width: 500px;
      width: 90%;
      position: relative;
    }

    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 28px;
      color: #999;
      cursor: pointer;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .form-group input {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      color: #071289;
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
      .container {
        padding: 1rem;
      }
      
      .submodal-content {
        padding: 1.5rem;
        margin: 1rem;
      }
      
      .form-group input {
        padding: 0.5rem;
      }
      
      .btn-submit {
        padding: 0.7rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>" class="back-btn">← Volver al Dashboard</a>
    
    <h1>Mantenedor de Eventos</h1>
    
    <!-- Sección superior: Buscador inteligente -->
    <div class="section-title">Buscar Evento</div>
    <div class="search-section">
      <input type="text" id="searchEvento" class="search-input" placeholder="Escribe para buscar eventos..." onkeyup="searchEventos()">
      <div id="searchResults" class="search-results"></div>
    </div>
    
    <!-- Sección intermedia: Tabla de eventos -->
    <div class="section-title">Todos los Eventos</div>
    <div class="table-section">
      <div class="table-header">
        <h3>Eventos registrados</h3>
        <button class="btn-add" onclick="openEventoModal('insert')">+ Agregar Evento</button>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Tipo Evento</th>
            <th>Jugadores</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
            <?php foreach ($eventos as $evento): ?>
            <tr>
                <td style="color: #040942ff;"><?= htmlspecialchars($evento['tipoevento']) ?></td>
                <td style="color: #040942ff;"><?= htmlspecialchars($evento['players']) ?></td>
                <td class="action-icons">
                <span class="action-icon" 
                        onclick="openEventoModal('update', <?= (int)$evento['id_tipoevento'] ?>, '<?= addslashes(htmlspecialchars($evento['tipoevento'])) ?>', '<?= addslashes(htmlspecialchars($evento['players'])) ?>')" 
                        title="Editar">✏️</span>
                <span class="action-icon" 
                        onclick="deleteEvento(<?= (int)$evento['id_tipoevento'] ?>)" 
                        title="Eliminar">🗑️</span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Submodal para insertar/editar -->
  <div id="eventoModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closeEventoModal()">&times;</span>
      <h2 id="modalTitle">Agregar Evento</h2>
      
      <form id="eventoForm" onsubmit="saveEvento(event)">
        <input type="hidden" id="eventoId" name="id_tipoevento">
        <input type="hidden" id="actionType" name="action" value="insert">
        
        <div class="form-group">
          <label for="eventoTipo">Tipo Evento *</label>
          <input type="text" id="eventoTipo" name="tipoevento" required>
        </div>
        
        <div class="form-group">
          <label for="eventoPlayers">Jugadores *</label>
          <input type="number" id="eventoPlayers" name="players" min="1" required>
        </div>
        
        <button type="submit" class="btn-submit">Grabar Evento</button>
      </form>
    </div>
  </div>

  <script>
    let currentAction = 'insert';
    let currentEventoId = null;
    
    function searchEventos() {
      const query = document.getElementById('searchEvento').value.toLowerCase();
      const resultsDiv = document.getElementById('searchResults');
      
      if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      
      // Simular búsqueda (en producción usar AJAX)
      const eventos = <?php echo json_encode($eventos); ?>;
      const filtered = eventos.filter(e => 
        e.tipoevento.toLowerCase().includes(query) || 
        e.players.toString().includes(query)
      );
      
      if (filtered.length > 0) {
        resultsDiv.innerHTML = filtered.map(e => 
        `<div style="color: #040942ff;" class="search-result-item" onclick="selectEvento(${e.id_tipoevento}, ${JSON.stringify(e.tipoevento)}, ${JSON.stringify(e.players)})">${e.tipoevento} (${e.players} jugadores)</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectEvento(id, tipo, players) {
        openEventoModal('edit', id, tipo, players);
        document.getElementById('searchEvento').value = '';
        document.getElementById('searchResults').style.display = 'none';
    }
    
    function openEventoModal(action, id = null, tipo = '', players = '') {
        // Convertir 'edit' a 'update'
        const actionForApi = (action === 'edit') ? 'update' : action;
        
        document.getElementById('actionType').value = actionForApi;
        document.getElementById('modalTitle').textContent = action === 'insert' ? 'Agregar Evento' : 'Editar Evento';
        document.getElementById('eventoId').value = id || '';
        document.getElementById('eventoTipo').value = tipo;
        document.getElementById('eventoPlayers').value = players;
        
        document.getElementById('eventoModal').style.display = 'flex';
    }
    
    function closeEventoModal() {
      document.getElementById('eventoModal').style.display = 'none';
    }

    function deleteEvento(id) {
        if (confirm('¿Estás seguro de eliminar este evento?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_tipoevento', id);
            
            fetch('../api/gestion_eventos.php', {  // ← Cambiado a '../api/'
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

    function saveEvento(event) {
        event.preventDefault();
            
        const formData = new FormData();
        formData.append('action', document.getElementById('actionType').value);
        formData.append('id_tipoevento', document.getElementById('eventoId').value);
        formData.append('tipoevento', document.getElementById('eventoTipo').value);
        formData.append('players', document.getElementById('eventoPlayers').value);
            
        console.log('Enviando acción:', document.getElementById('actionType').value);
            
        fetch('../api/gestion_eventos.php', {  // ← Cambiado a '../api/'
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
            alert('Error al guardar el evento');
        });
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
      const modal = document.getElementById('eventoModal');
      if (event.target === modal) {
        closeEventoModal();
      }
    }
  </script>
</body>
</html>