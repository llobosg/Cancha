<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación CEO
if (!isset($_SESSION['ceo_id']) || $_SESSION['ceo_rol'] !== 'ceo_cancha') {
    header('Location: ceo_login.php');
    exit;
}

// Obtener todos los socios con info de club
$stmt = $pdo->query("
    SELECT s.id_socio, s.alias, s.email, s.celular, s.genero, 
           c.nombre as club_nombre, s.es_responsable
    FROM socios s
    LEFT JOIN clubs c ON s.id_club = c.id_club
    ORDER BY s.alias
");
$socios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mantenedor de Socios - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    /* Mismo CSS que mantenedor_clubs.php */
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

    /* Tabla de socios */
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
    
    <h1>Mantenedor de Socios</h1>
    
    <!-- Sección superior: Buscador inteligente -->
    <div class="section-title">Buscar Socio</div>
    <div class="search-section">
      <input type="text" id="searchSocio" class="search-input" placeholder="Escribe para buscar socios..." onkeyup="searchSocios()">
      <div id="searchResults" class="search-results"></div>
    </div>
    
    <!-- Sección intermedia: Tabla de socios -->
    <div class="section-title">Todos los Socios</div>
    <div class="table-section">
      <div class="table-header">
        <h3>Socios registrados</h3>
        <button class="btn-add" onclick="openSocioModal('insert')">+ Agregar Socio</button>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Alias</th>
            <th>Email</th>
            <th>Club</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($socios as $socio): ?>
          <tr>
            <td style="color: #040942ff;"><?= htmlspecialchars($socio['alias']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($socio['email']) ?></td>
            <td style="color: #040942ff;"><?= htmlspecialchars($socio['club_nombre'] ?? 'Sin club') ?></td>
            <td class="action-icons">
              <span class="action-icon" onclick="openSocioModal('update', <?= $socio['id_socio'] ?>, '<?= addslashes(htmlspecialchars($socio['alias'])) ?>', '<?= addslashes(htmlspecialchars($socio['email'])) ?>', '<?= addslashes(htmlspecialchars($socio['celular'])) ?>', '<?= addslashes(htmlspecialchars($socio['genero'])) ?>', '<?= addslashes(htmlspecialchars($socio['es_responsable'])) ?>')" title="Editar">✏️</span>
              <span class="action-icon" onclick="deleteSocio(<?= $socio['id_socio'] ?>)" title="Eliminar">🗑️</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Submodal para insertar/editar -->
  <div id="socioModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closeSocioModal()">&times;</span>
      <h2 id="modalTitle">Agregar Socio</h2>
      
      <form id="socioForm" onsubmit="saveSocio(event)">
        <input type="hidden" id="socioId" name="id_socio">
        <input type="hidden" id="actionType" name="action" value="insert">
        
        <div class="form-group">
          <label for="socioAlias">Alias *</label>
          <input type="text" id="socioAlias" name="alias" required>
        </div>
        
        <div class="form-group">
          <label for="socioEmail">Email *</label>
          <input type="email" id="socioEmail" name="email" required>
        </div>
        
        <div class="form-group">
          <label for="socioCelular">Celular</label>
          <input type="text" id="socioCelular" name="celular">
        </div>
        
        <div class="form-group">
          <label for="socioGenero">Género</label>
          <select id="socioGenero" name="genero">
            <option value="">Seleccionar</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="socioResponsable">Es Responsable</label>
          <select id="socioResponsable" name="es_responsable">
            <option value="0">No</option>
            <option value="1">Sí</option>
          </select>
        </div>
        
        <button type="submit" class="btn-submit">Guardar Socio</button>
      </form>
    </div>
  </div>

  <script>
    function searchSocios() {
      const query = document.getElementById('searchSocio').value.toLowerCase();
      const resultsDiv = document.getElementById('searchResults');
      
      if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
      }
      
      const socios = <?php echo json_encode($socios); ?>;
      const filtered = socios.filter(s => 
        s.alias.toLowerCase().includes(query) || 
        s.email.toLowerCase().includes(query)
      );
      
      if (filtered.length > 0) {
        resultsDiv.innerHTML = filtered.map(s => 
          `<div style="color: #040942ff;" class="search-result-item" onclick="selectSocio(${s.id_socio}, '${s.alias}', '${s.email}', '${s.celular}', '${s.genero}', '${s.es_responsable}')">${s.alias} - ${s.email}</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectSocio(id, alias, email, celular, genero, responsable) {
      openSocioModal('update', id, alias, email, celular, genero, responsable);
      document.getElementById('searchSocio').value = '';
      document.getElementById('searchResults').style.display = 'none';
    }
    
    function openSocioModal(action, id = null, alias = '', email = '', celular = '', genero = '', responsable = '') {
      const actionForApi = (action === 'edit') ? 'update' : action;
      document.getElementById('actionType').value = actionForApi;
      document.getElementById('modalTitle').textContent = action === 'insert' ? 'Agregar Socio' : 'Editar Socio';
      document.getElementById('socioId').value = id || '';
      document.getElementById('socioAlias').value = alias;
      document.getElementById('socioEmail').value = email;
      document.getElementById('socioCelular').value = celular;
      document.getElementById('socioGenero').value = genero;
      document.getElementById('socioResponsable').value = responsable;
      
      document.getElementById('socioModal').style.display = 'flex';
    }
    
    function closeSocioModal() {
      document.getElementById('socioModal').style.display = 'none';
    }

    function deleteSocio(id) {
      if (confirm('¿Estás seguro de eliminar este socio?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id_socio', id);
        
        fetch('../api/gestion_socios.php', {
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
    
    function saveSocio(event) {
        event.preventDefault();
        
        const formData = new FormData();
        formData.append('action', document.getElementById('actionType').value);
        formData.append('id_socio', document.getElementById('socioId').value);
        formData.append('alias', document.getElementById('socioAlias').value);
        formData.append('email', document.getElementById('socioEmail').value);
        formData.append('celular', document.getElementById('socioCelular').value);
        formData.append('genero', document.getElementById('socioGenero').value);
        formData.append('es_responsable', document.getElementById('socioResponsable').value);
        
        fetch('../api/gestion_socios.php', {
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
            alert('Error al guardar el socio');
        });
    }
    
    window.onclick = function(event) {
      const modal = document.getElementById('socioModal');
      if (event.target === modal) {
        closeSocioModal();
      }
    }
  </script>
</body>
</html>