<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación CEO
if (!isset($_SESSION['ceo_id']) || $_SESSION['ceo_rol'] !== 'ceo_cancha') {
    header('Location: ceo_login.php');
    exit;
}

// Obtener todos los clubs
$stmt = $pdo->query("
    SELECT id_club, nombre, pais, ciudad, comuna, email_responsable, telefono 
    FROM clubs 
    ORDER BY nombre
");
$clubs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mantenedor de Clubs - Cancha</title>
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

    /* Tabla de clubs */
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

    .form-group input, .form-group select {
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
      
      .form-group input, .form-group select {
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
    <a href="ceo_dashboard.php" class="back-btn">← Volver al Dashboard</a>
    
    <h1>Mantenedor de Clubs</h1>
    
    <!-- Sección superior: Buscador inteligente -->
    <div class="section-title">Buscar Club</div>
    <div class="search-section">
      <input type="text" id="searchClub" class="search-input" placeholder="Escribe para buscar clubs..." onkeyup="searchClubs()">
      <div id="searchResults" class="search-results"></div>
    </div>
    
    <!-- Sección intermedia: Tabla de clubs -->
    <div class="section-title">Todos los Clubs</div>
    <div class="table-section">
      <div class="table-header">
        <h3>Clubs registrados</h3>
        <button class="btn-add" onclick="openClubModal('insert')">+ Agregar Club</button>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>País</th>
            <th>Ciudad</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clubs as $club): ?>
          <tr>
            <td style="color: #040942ff;"><?= htmlspecialchars($club['nombre']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($club['pais']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($club['ciudad']) ?></td>
            <td class="action-icons">
              <span class="action-icon" onclick="openClubModal('update', <?= $club['id_club'] ?>, '<?= addslashes(htmlspecialchars($club['nombre'])) ?>', '<?= addslashes(htmlspecialchars($club['pais'])) ?>', '<?= addslashes(htmlspecialchars($club['ciudad'])) ?>', '<?= addslashes(htmlspecialchars($club['comuna'])) ?>', '<?= addslashes(htmlspecialchars($club['email_responsable'])) ?>', '<?= addslashes(htmlspecialchars($club['telefono'])) ?>')" title="Editar">✏️</span>
              <span class="action-icon" onclick="deleteClub(<?= $club['id_club'] ?>)" title="Eliminar">🗑️</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Submodal para insertar/editar -->
  <div id="clubModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closeClubModal()">&times;</span>
      <h2 id="modalTitle">Agregar Club</h2>
      
      <form id="clubForm" onsubmit="saveClub(event)">
        <input type="hidden" id="clubId" name="id_club">
        <input type="hidden" id="actionType" name="action" value="insert">
        
        <div class="form-group">
          <label for="clubNombre">Nombre *</label>
          <input type="text" id="clubNombre" name="nombre" required>
        </div>
        
        <div class="form-group">
          <label for="clubPais">País *</label>
          <input type="text" id="clubPais" name="pais" required>
        </div>
        
        <div class="form-group">
          <label for="clubCiudad">Ciudad *</label>
          <input type="text" id="clubCiudad" name="ciudad" required>
        </div>
        
        <div class="form-group">
          <label for="clubComuna">Comuna *</label>
          <input type="text" id="clubComuna" name="comuna" required>
        </div>
        
        <div class="form-group">
          <label for="clubEmail">Email Responsable *</label>
          <input type="email" id="clubEmail" name="email_responsable" required>
        </div>
        
        <div class="form-group">
          <label for="clubTelefono">Teléfono</label>
          <input type="text" id="clubTelefono" name="telefono">
        </div>
        
        <button type="submit" class="btn-submit">Guardar Club</button>
      </form>
    </div>
  </div>

  <script>
    function searchClubs() {
      const query = document.getElementById('searchClub').value.toLowerCase();
      const resultsDiv = document.getElementById('searchResults');
      
      if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      
      const clubs = <?php echo json_encode($clubs); ?>;
      const filtered = clubs.filter(c => 
        c.nombre.toLowerCase().includes(query) || 
        c.pais.toLowerCase().includes(query) ||
        c.ciudad.toLowerCase().includes(query)
      );
      
      if (filtered.length > 0) {
        resultsDiv.innerHTML = filtered.map(c => 
          `<div style="color: #040942ff;" class="search-result-item" onclick="selectClub(${c.id_club}, '${c.nombre}', '${c.pais}', '${c.ciudad}', '${c.comuna}', '${c.email_responsable}', '${c.telefono}')">${c.nombre} - ${c.ciudad}</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectClub(id, nombre, pais, ciudad, comuna, email, telefono) {
      openClubModal('update', id, nombre, pais, ciudad, comuna, email, telefono);
      document.getElementById('searchClub').value = '';
      document.getElementById('searchResults').style.display = 'none';
    }
    
    function openClubModal(action, id = null, nombre = '', pais = '', ciudad = '', comuna = '', email = '', telefono = '') {
      const actionForApi = (action === 'edit') ? 'update' : action;
      document.getElementById('actionType').value = actionForApi;
      document.getElementById('modalTitle').textContent = action === 'insert' ? 'Agregar Club' : 'Editar Club';
      document.getElementById('clubId').value = id || '';
      document.getElementById('clubNombre').value = nombre;
      document.getElementById('clubPais').value = pais;
      document.getElementById('clubCiudad').value = ciudad;
      document.getElementById('clubComuna').value = comuna;
      document.getElementById('clubEmail').value = email;
      document.getElementById('clubTelefono').value = telefono;
      
      document.getElementById('clubModal').style.display = 'flex';
    }
    
    function closeClubModal() {
      document.getElementById('clubModal').style.display = 'none';
    }

    function deleteClub(id) {
      if (confirm('¿Estás seguro de eliminar este club?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id_club', id);
        
        fetch('../api/gestion_clubs.php', {
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
    
    function saveClub(event) {
        event.preventDefault();
        
        const formData = new FormData();
        formData.append('action', document.getElementById('actionType').value);
        formData.append('id_club', document.getElementById('clubId').value);
        formData.append('nombre', document.getElementById('clubNombre').value);
        formData.append('pais', document.getElementById('clubPais').value);
        formData.append('ciudad', document.getElementById('clubCiudad').value);
        formData.append('comuna', document.getElementById('clubComuna').value);
        formData.append('email_responsable', document.getElementById('clubEmail').value);
        formData.append('telefono', document.getElementById('clubTelefono').value);
        
        fetch('../api/gestion_clubs.php', {
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
            alert('Error al guardar el club');
        });
    }
    
    window.onclick = function(event) {
      const modal = document.getElementById('clubModal');
      if (event.target === modal) {
        closeClubModal();
      }
    }
  </script>
</body>
</html>