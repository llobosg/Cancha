<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación CEO
if (!isset($_SESSION['ceo_id']) || $_SESSION['ceo_rol'] !== 'ceo_cancha') {
    header('Location: ceo_login.php');
    exit;
}

// Obtener todos los puestos
$stmt = $pdo->query("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
$puestos = $stmt->fetchAll();

$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mantenedor de Puestos - Cancha</title>
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

    /* Tabla de puestos */
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

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 1rem;
      margin: 1.5rem 0;
    }

    .form-group label {
      font-weight: bold;
      color: #333;
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
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>" class="back-btn">← Volver al Dashboard</a>
    
    <h1>Mantenedor de Puestos</h1>
    
    <!-- Sección superior: Buscador inteligente -->
    <div class="section-title">Buscar Puesto</div>
    <div class="search-section">
      <input style="color: #040942ff;" type="text" id="searchPuesto" class="search-input" placeholder="Escribe para buscar puestos..." onkeyup="searchPuestos()">
      <div id="searchResults" class="search-results"></div>
    </div>
    
    <!-- Sección intermedia: Tabla de puestos -->
    <div class="section-title">Todos los Puestos</div>
    <div class="table-section">
      <div class="table-header">
        <h3>Puestos registrados</h3>
        <button class="btn-add" onclick="openPuestoModal('insert')">+ Agregar Puesto</button>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Puesto</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($puestos as $puesto): ?>
          <tr>
            <td style="color: #040942ff;"><?= htmlspecialchars($puesto['puesto']) ?></td>
            <td class="action-icons">
              <span class="action-icon" onclick="openPuestoModal('edit', <?= $puesto['id_puesto'] ?>, '<?= htmlspecialchars($puesto['puesto']) ?>')" title="Editar">✏️</span>
              <span class="action-icon" onclick="deletePuesto(<?= $puesto['id_puesto'] ?>)" title="Eliminar">🗑️</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Submodal para insertar/editar -->
  <div id="puestoModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closePuestoModal()">&times;</span>
      <h2 id="modalTitle">Agregar Puesto</h2>
      
      <form id="puestoForm" onsubmit="savePuesto(event)">
        <input type="hidden" id="puestoId" name="id_puesto">
        <input type="hidden" id="actionType" name="action" value="insert">
        
        <div class="form-grid">
          <div class="form-group">
            <label for="puestoNombre">Puesto *</label>
          </div>
          <div class="form-group">
            <input type="text" id="puestoNombre" name="puesto" required>
          </div>
        </div>
        
        <button type="submit" class="btn-submit">Guardar Puesto</button>
      </form>
    </div>
  </div>

  <script>
    let currentAction = 'insert';
    let currentPuestoId = null;
    
    function searchPuestos() {
      const query = document.getElementById('searchPuesto').value.toLowerCase();
      const resultsDiv = document.getElementById('searchResults');
      
      if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      
      // Simular búsqueda (en producción usar AJAX)
      const puestos = <?php echo json_encode($puestos); ?>;
      const filtered = puestos.filter(p => p.puesto.toLowerCase().includes(query));
      
      if (filtered.length > 0) {
        resultsDiv.innerHTML = filtered.map(p => 
          `<div class="search-result-item" onclick="selectPuesto(${p.id_puesto}, '${p.puesto}')">${p.puesto}</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectPuesto(id, nombre) {
      openPuestoModal('edit', id, nombre);
      document.getElementById('searchPuesto').value = '';
      document.getElementById('searchResults').style.display = 'none';
    }
    
    function openPuestoModal(action, id = null, nombre = '') {
      currentAction = action;
      currentPuestoId = id;
      
      document.getElementById('modalTitle').textContent = action === 'insert' ? 'Agregar Puesto' : 'Editar Puesto';
      document.getElementById('puestoId').value = id || '';
      document.getElementById('puestoNombre').value = nombre;
      document.getElementById('actionType').value = action;
      
      document.getElementById('puestoModal').style.display = 'flex';
    }
    
    function closePuestoModal() {
      document.getElementById('puestoModal').style.display = 'none';
    }
    
    function savePuesto(event) {
      event.preventDefault();
      
      const formData = new FormData();
      formData.append('action', document.getElementById('actionType').value);
      formData.append('id_puesto', document.getElementById('puestoId').value);
      formData.append('puesto', document.getElementById('puestoNombre').value);
      
      fetch('api/gestion_puestos.php', {
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
        alert('Error al guardar el puesto');
      });
    }
    
    function deletePuesto(id) {
      if (confirm('¿Estás seguro de eliminar este puesto?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id_puesto', id);
        
        fetch('api/gestion_puestos.php', {
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
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
      const modal = document.getElementById('puestoModal');
      if (event.target === modal) {
        closePuestoModal();
      }
    }
  </script>
</body>
</html>