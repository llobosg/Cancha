<?php
require_once __DIR__ . '/../includes/config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación (CEO o Socio)
$is_ceo = isset($_SESSION['ceo_id']) && $_SESSION['ceo_rol'] === 'ceo_cancha';
$is_socio = isset($_SESSION['id_socio']) && !empty($_SESSION['id_socio']);
$can_edit = $is_ceo || $is_socio;

// Si no hay sesión pero se pide perfil propio
if (!$can_edit && isset($_GET['mi_perfil'])) {
    if (isset($_SESSION['id_socio']) && !empty($_SESSION['id_socio'])) {
        $is_socio = true;
        $can_edit = true;
    }
}

if (!$can_edit) {
    header('Location: ../index.php');
    exit;
}

// Obtener puestos para el select
$stmt_puestos = $pdo->query("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
$puestos = $stmt_puestos->fetchAll();

// Obtener socio actual si es modo socio
$current_socio_id = $is_socio ? $_SESSION['id_socio'] : null;
$editing_own_profile = false;

// Obtener socios según rol
if ($is_ceo) {
    // CEO ve todos los socios
    $stmt = $pdo->query("
        SELECT s.*, c.nombre as club_nombre, p.puesto as puesto_nombre
        FROM socios s
        LEFT JOIN clubs c ON s.id_club = c.id_club
        LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
        ORDER BY s.alias
    ");
    $socios = $stmt->fetchAll();
} else {
    // Socio solo ve su propio perfil
    $stmt = $pdo->prepare("
        SELECT s.*, c.nombre as club_nombre, p.puesto as puesto_nombre
        FROM socios s
        LEFT JOIN clubs c ON s.id_club = c.id_club
        LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
        WHERE s.id_socio = ?
    ");
    $stmt->execute([$current_socio_id]);
    $socios = $stmt->fetchAll();
    $editing_own_profile = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $editing_own_profile ? 'Mi Perfil' : 'Mantenedor de Socios' ?> - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
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

    .section-title {
      color: #003366;
      margin: 1.5rem 0;
      font-size: 1.4rem;
    }

    /* Buscador (solo para CEO) */
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

    /* Tabla (solo para CEO) */
    .table-section {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
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

    /* Submodal - formulario completo */
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

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #333;
      margin-bottom: 0.5rem;
    }

    .form-group input, .form-group select, .form-group textarea {
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
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if ($is_ceo): ?>
      <a href="ceo_dashboard.php" class="back-btn">← Volver al Dashboard</a>
      <h1>Mantenedor de Socios</h1>
    <?php else: ?>
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="back-btn">← Volver a mi Dashboard</a>
      <h1>Mi Perfil</h1>
    <?php endif; ?>

    <?php if ($is_ceo): ?>
      <!-- Sección superior: Buscador inteligente -->
      <div class="section-title">Buscar Socio</div>
      <div class="search-section">
        <input type="text" id="searchSocio" class="search-input" placeholder="Escribe para buscar socios..." onkeyup="searchSocios()">
        <div id="searchResults" class="search-results" style="margin-top: 1rem; max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; display: none;"></div>
      </div>
      
      <!-- Sección intermedia: Tabla de socios -->
      <div class="section-title">Todos los Socios</div>
      <div class="table-section">
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
              <td>
                <span class="action-icon" onclick="openSocioModal('update', 
                  <?= $socio['id_socio'] ?>, 
                  '<?= addslashes(htmlspecialchars($socio['alias'])) ?>',
                  '<?= addslashes(htmlspecialchars($socio['fecha_nac'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars($socio['celular'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars($socio['email'])) ?>',
                  '<?= addslashes(htmlspecialchars($socio['direccion'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars($socio['rol'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars($socio['foto_url'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars(strtolower($socio['genero'] ?? ''))) ?>',
                  '<?= addslashes(htmlspecialchars($socio['id_puesto'] ?? '')) ?>',
                  '<?= addslashes(htmlspecialchars($socio['habilidad'] ?? '')) ?>'
                )" style="cursor:pointer; color:#071289;" title="Editar">✏️</span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <!-- Para socios: mostrar directamente su perfil -->
      <?php if (!empty($socios)): ?>
        <?php $socio = $socios[0]; ?>
        <button class="btn-add" onclick="openSocioModal('update', 
          <?= $socio['id_socio'] ?>, 
          '<?= addslashes(htmlspecialchars($socio['alias'])) ?>',
          '<?= addslashes(htmlspecialchars($socio['fecha_nac'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars($socio['celular'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars($socio['email'])) ?>',
          '<?= addslashes(htmlspecialchars($socio['direccion'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars($socio['rol'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars($socio['foto_url'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars(strtolower($socio['genero'] ?? ''))) ?>',
          '<?= addslashes(htmlspecialchars($socio['id_puesto'] ?? '')) ?>',
          '<?= addslashes(htmlspecialchars($socio['habilidad'] ?? '')) ?>'
        )" style="background:#00cc66; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; font-weight:bold; margin-bottom:2rem;">
          Editar Mi Perfil
        </button>
        
        <!-- Vista de perfil actual -->
        <div style="background:white; padding:1.5rem; border-radius:12px; margin-bottom:2rem;">
          <h3>Datos Actuales</h3>
          <p style="color: #040942ff;"><strong>Alias:</strong> <?= htmlspecialchars($socio['alias']) ?></p>
          <p style="color: #040942ff;"><strong>Fecha Nac.:</strong> <?= htmlspecialchars($socio['fecha_nac'] ?? '') ?></p>
          <p style="color: #040942ff;"><strong>Celular:</strong> <?= htmlspecialchars($socio['celular'] ?? '') ?></p>
          <p style="color: #040942ff;"><strong>Email:</strong> <?= htmlspecialchars($socio['email']) ?></p>
          <p style="color: #040942ff;"><strong>Dirección:</strong> <?= htmlspecialchars($socio['direccion'] ?? '') ?></p>
          <p style="color: #040942ff;"><strong>Rol:</strong> <?= htmlspecialchars($socio['rol'] ?? '') ?></p>
          <p style="color: #040942ff;"><strong>Género:</strong> <?= htmlspecialchars(ucfirst($socio['genero'] ?? '')) ?></p>
          <p style="color: #040942ff;"><strong>Puesto:</strong> <?= htmlspecialchars($socio['puesto_nombre'] ?? '') ?></p>
          <p><strong>Habilidad:</strong> <?= htmlspecialchars($socio['habilidad'] ?? '') ?></p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Submodal para insertar/editar -->
  <div id="socioModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closeSocioModal()">&times;</span>
      <h2 id="modalTitle">Editar Perfil</h2>
      
      <form id="socioForm" onsubmit="saveSocio(event)" enctype="multipart/form-data">
        <input type="hidden" id="socioId" name="id_socio">
        <input type="hidden" id="actionType" name="action" value="update">
        <input type="hidden" id="originalEmail" name="original_email">
        
        <div class="form-group">
          <label for="socioAlias">Alias *</label>
          <input type="text" id="socioAlias" name="alias" required>
        </div>
        
        <div class="form-group">
          <label for="socioFechaNac">Fecha de Nacimiento</label>
          <input type="date" id="socioFechaNac" name="fecha_nac">
        </div>
        
        <div class="form-group">
          <label for="socioCelular">Celular</label>
          <input type="text" id="socioCelular" name="celular">
        </div>
        
        <div class="form-group">
          <label for="socioEmail">Email *</label>
          <input type="email" id="socioEmail" name="email" required>
        </div>
        
        <div class="form-group">
          <label for="socioDireccion">Dirección</label>
          <textarea id="socioDireccion" name="direccion" rows="2"></textarea>
        </div>
        
        <div class="form-group">
          <label for="socioRol">Rol</label>
          <select id="socioRol" name="rol" required>
              <option value="">Seleccionar</option>
              <option value="Jugador">Jugador</option>
              <option value="Galleta">Galleta</option>
              <option value="Amigo del club">Amigo del club</option>
              <option value="Tesorero">Tesorero</option>
              <option value="Director">Director</option>
              <option value="Delegado">Delegado</option>
              <option value="Profe">Profe</option>
              <option value="Kine">Kine</option>
              <option value="Preparador Físico">Preparador Físico</option>
              <option value="Utilero">Utilero</option>
          </select>
        </div>
        
        
        <div class="form-group">
          <label for="socioFoto">Foto de Perfil</label>
          <input type="file" id="socioFoto" name="foto_url" accept="image/*">
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
          <label for="socioPuesto">Puesto</label>
          <select id="socioPuesto" name="id_puesto">
            <option value="">Seleccionar</option>
            <?php foreach ($puestos as $puesto): ?>
            <option value="<?= $puesto['id_puesto'] ?>"><?= htmlspecialchars($puesto['puesto']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="socioHabilidad">Habilidad</label>
          <select id="socioHabilidad" name="habilidad">
              <option value="">Seleccionar</option>
              <option value="Básica">Malo</option>
              <option value="Intermedia">Más o Menos</option>
              <option value="Avanzada">Crack</option>
              <option value="Pádel-Sexta">Pádel-Sexta</option>
              <option value="Pádel-Quinta">Pádel-Quinta</option>
              <option value="Pádel-Cuarta">Pádel-Cuarta</option>
              <option value="Pádel-Tercera">Pádel-Tercera</option>
              <option value="Pádel-Segunda">Pádel-Segunda</option>
              <option value="Pádel-Primera">Pádel-Primera</option>
        </select>
        </div>
        
        <button type="submit" class="btn-submit">Guardar Cambios</button>
      </form>
    </div>
  </div>

  <script>
    // Funciones para CEO
    <?php if ($is_ceo): ?>
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
          `<div style="color: #040942ff; padding:0.5rem; cursor:pointer; border-bottom:1px solid #eee;" onclick="selectSocio(${s.id_socio}, '${s.alias}', '${s.fecha_nac || ''}', '${s.celular || ''}', '${s.email}', '${s.direccion || ''}', '${s.rol || ''}', '${s.foto_url || ''}', '${s.genero || ''}', '${s.id_puesto || ''}', '${s.habilidad || ''}')">${s.alias} - ${s.email}</div>`
        ).join('');
        resultsDiv.style.display = 'block';
      } else {
        resultsDiv.style.display = 'none';
      }
    }
    
    function selectSocio(id, alias, fecha_nac, celular, email, direccion, rol, foto_url, genero, id_puesto, habilidad) {
      openSocioModal('update', id, alias, fecha_nac, celular, email, direccion, rol, foto_url, genero, id_puesto, habilidad);
      document.getElementById('searchSocio').value = '';
      document.getElementById('searchResults').style.display = 'none';
    }
    <?php endif; ?>
    
    function openSocioModal(action, id = null, alias = '', fecha_nac = '', celular = '', email = '', direccion = '', rol = '', foto_url = '', genero = '', id_puesto = '', habilidad = '') {
      document.getElementById('actionType').value = action;
      document.getElementById('modalTitle').textContent = action === 'insert' ? 'Agregar Socio' : 'Editar Perfil';
      document.getElementById('socioId').value = id || '';
      document.getElementById('originalEmail').value = email;
      document.getElementById('socioAlias').value = alias;
      document.getElementById('socioFechaNac').value = fecha_nac;
      document.getElementById('socioCelular').value = celular;
      document.getElementById('socioEmail').value = email;
      document.getElementById('socioDireccion').value = direccion;
      document.getElementById('socioRol').value = rol;
      
      // Género
      const generoSelect = document.getElementById('socioGenero');
      generoSelect.value = genero.toLowerCase();
      
      // Puesto
      document.getElementById('socioPuesto').value = id_puesto;
      
      // Habilidad
      document.getElementById('socioHabilidad').value = habilidad;
      
      document.getElementById('socioModal').style.display = 'flex';
    }
    
    function closeSocioModal() {
      document.getElementById('socioModal').style.display = 'none';
    }

    function saveSocio(event) {
      event.preventDefault();

      // Validación de edad
        const fechaNacInput = document.getElementById('socioFechaNac');
        const fechaNac = fechaNacInput.value;
        
        if (fechaNac) {
            const hoy = new Date();
            const nacimiento = new Date(fechaNac); // ✅ 'Date' con mayúscula
            let edad = hoy.getFullYear() - nacimiento.getFullYear(); // ✅ 'let' no 'const'
            const mes = hoy.getMonth() - nacimiento.getMonth();
            
            if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
                edad--;
            }
            
            if (edad < 14) {
                mostrarToast('❌ ohh lo sentimos...la edad mínima para CanchaSport es de 14 años');
                return;
            }
        } else {
            // Si no hay fecha, remover el atributo name para que no se envíe
            fechaNacInput.removeAttribute('name');
        }
      
      const formData = new FormData();
      formData.append('action', document.getElementById('actionType').value);
      formData.append('id_socio', document.getElementById('socioId').value);
      formData.append('original_email', document.getElementById('originalEmail').value);
      formData.append('alias', document.getElementById('socioAlias').value);
      formData.append('fecha_nac', document.getElementById('socioFechaNac').value);
      formData.append('celular', document.getElementById('socioCelular').value);
      formData.append('email', document.getElementById('socioEmail').value);
      formData.append('direccion', document.getElementById('socioDireccion').value);
      formData.append('rol', document.getElementById('socioRol').value);
      formData.append('genero', document.getElementById('socioGenero').value);
      formData.append('id_puesto', document.getElementById('socioPuesto').value);
      formData.append('habilidad', document.getElementById('socioHabilidad').value);
      
      // Archivo de foto
      const fotoFile = document.getElementById('socioFoto').files[0];
      if (fotoFile) {
        formData.append('foto_url', fotoFile);
      }
      
      fetch('../api/gestion_socios.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Perfil actualizado correctamente');
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar el perfil');
      });
    }
    
    window.onclick = function(event) {
      const modal = document.getElementById('socioModal');
      if (event.target === modal) {
        closeSocioModal();
      }
    }

    function mostrarToast(mensaje) {
        // Crear contenedor de toast si no existe
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000;
                max-width: 300px;
            `;
            document.body.appendChild(toastContainer);
        }
        
        // Crear toast
        const toast = document.createElement('div');
        toast.textContent = mensaje;
        toast.style.cssText = `
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease-out, fadeOut 0.5s ease-in 2.5s forwards;
            font-size: 14px;
        `;
        
        toastContainer.appendChild(toast);
        
        // Eliminar toast después de 3 segundos
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    // Animaciones CSS para toasts
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
  </script>
</body>
</html>