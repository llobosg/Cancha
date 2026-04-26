<?php
require_once __DIR__ . '/../includes/config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
}

// Verificar autenticación
if (!isset($_SESSION['id_socio'])) {
    header('Location: ../index.php');
    exit;
}

$id_socio_logueado = $_SESSION['id_socio'];
$modo_individual = !isset($_SESSION['club_id']);

// Cargar datos del socio logueado
$stmt_self = $pdo->prepare("SELECT * FROM socios WHERE id_socio = ?");
$stmt_self->execute([$id_socio_logueado]);
$socio_logueado = $stmt_self->fetch();

if (!$socio_logueado) {
    header('Location: ../index.php');
    exit;
}

// Determinar qué socio se va a editar
$id_socio_a_editar = $id_socio_logueado;

// Si NO es modo individual y se pasa id_socio por GET
if (!$modo_individual && isset($_GET['id_socio'])) {
    $id_socio_request = (int)$_GET['id_socio'];
    
    if ($id_socio_request === $id_socio_logueado) {
        $id_socio_a_editar = $id_socio_request;
    }
    elseif (!empty($socio_logueado) && isset($socio_logueado['es_responsable']) && $socio_logueado['es_responsable'] == 1) {
        $stmt_check = $pdo->prepare("SELECT sc.id_socio FROM socio_club sc WHERE sc.id_socio = ? AND sc.id_club = ? AND sc.estado = 'activo'");
        $stmt_check->execute([$id_socio_request, $_SESSION['club_id']]);
        if ($row = $stmt_check->fetch()) {
            $id_socio_a_editar = $row['id_socio'];
        }
    } else {
        header('Location: dashboard_socio.php');
        exit;
    }
}

// Cargar datos del socio a editar
$stmt_edit = $pdo->prepare("SELECT s.*, c.nombre as club_nombre, p.puesto as puesto_nombre, sc.id_club as id_club_asociado FROM socios s LEFT JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo' LEFT JOIN clubs c ON sc.id_club = c.id_club LEFT JOIN puestos p ON s.id_puesto = p.id_puesto WHERE s.id_socio = ? LIMIT 1");
$stmt_edit->execute([$id_socio_a_editar]);
$socio_editar = $stmt_edit->fetch();

if (!$socio_editar) {
    header('Location: dashboard_socio.php');
    exit;
}

// Obtener puestos para el select
$stmt_puestos = $pdo->query("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
$puestos = $stmt_puestos->fetchAll();

$editing_own_profile = ($id_socio_a_editar == $id_socio_logueado);
$is_ceo = isset($_SESSION['ceo_id']) && $_SESSION['ceo_rol'] === 'ceo_cancha';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title><?= $editing_own_profile ? 'Mi Perfil' : 'Mantenedor de Socios' ?> - CanchaSport</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    /* ESTILOS V2 HOMOLOGADOS */
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    
    body {
      background-color: #0f172a;
      background-image: url('/assets/img/cancha_pasto2.jpg');
      background-size: cover;
      background-position: center;
      color: #f1f5f9;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 20px 0;
    }
    body::before {
      content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
      pointer-events: none; z-index: -1;
    }

    .app-container { width: 100%; max-width: 600px; padding-bottom: 40px; position: relative; }
    
    .logo-header { text-align: center; margin: 20px 0 15px; }
    .logo-header h1 { 
      font-size: 1.8rem; 
      background: linear-gradient(135deg, #4ade80, #3b82f6); 
      -webkit-background-clip: text; 
      -webkit-text-fill-color: transparent; 
      font-weight: 900;
    }
    .logo-header p { color: #cbd5e1; font-size: 0.9rem; }

    .card {
      background: rgba(30, 41, 59, 0.85);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 25px;
      margin: 0 16px;
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      color: #f1f5f9;
    }

    .back-btn {
      color: #94a3b8;
      text-decoration: none;
      margin-bottom: 1.5rem;
      display: inline-block;
      font-weight: 600;
      font-size: 0.9rem;
      transition: color 0.2s;
    }
    .back-btn:hover { color: #fff; }

    /* Inputs y Formularios */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
    .full-width { grid-column: span 2; }
    .input-group { display: flex; flex-direction: column; }
    
    .input-label {
      color: #94a3b8; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px;
      text-transform: uppercase; letter-spacing: 0.5px;
    }

    .input, select, textarea {
      width: 100%; padding: 10px 12px; border-radius: 10px;
      border: 1px solid rgba(255,255,255,0.15);
      background: rgba(15,23,42,0.6); color: white; font-size: 0.95rem;
      transition: all 0.3s;
    }
    .input:focus, select:focus, textarea:focus {
      outline: none; border-color: #3b82f6; background: rgba(15,23,42,0.9);
    }

    .btn {
      width: 100%; padding: 14px; border-radius: 12px; border: none;
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      color: white; font-weight: bold; font-size: 1rem; cursor: pointer;
      margin-top: 10px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
    }
    .btn:active { transform: scale(0.98); }
    
    .btn-danger {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
      margin-top: 15px;
    }

    .btn-edit {
      background: linear-gradient(135deg, #10b981, #059669);
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
      margin-bottom: 20px;
    }

    /* Vista de Perfil Actual */
    .profile-view {
      background: rgba(15, 23, 42, 0.4);
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 20px;
    }
    .profile-item {
      display: flex; justify-content: space-between;
      padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
      font-size: 0.9rem;
    }
    .profile-item:last-child { border-bottom: none; }
    .profile-label { color: #94a3b8; }
    .profile-value { color: #e2e8f0; font-weight: 500; }

    /* Modal Estilos V2 */
    .submodal {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.8); z-index: 3000;
      display: flex; justify-content: center; align-items: center;
      backdrop-filter: blur(5px);
    }
    .submodal-content {
      background: #1e293b;
      width: 90%; max-width: 500px;
      padding: 25px;
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 20px 50px rgba(0,0,0,0.5);
      animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      color: #f1f5f9;
      max-height: 90vh; overflow-y: auto;
    }
    @keyframes popIn { from {transform: scale(0.9); opacity: 0;} to {transform: scale(1); opacity: 1;} }
    
    .close-modal {
      position: absolute; top: 15px; right: 15px;
      font-size: 1.5rem; color: #94a3b8; cursor: pointer;
      background: rgba(255,255,255,0.1); width: 30px; height: 30px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
    }
    .close-modal:hover { background: rgba(255,255,255,0.2); color: white; }

    .modal-title { color: #f1f5f9; font-size: 1.4rem; margin-bottom: 20px; font-weight: bold; text-align: center; }

    /* Toast */
    #toast {
      visibility: hidden; min-width: 250px; background-color: #333; color: #fff;
      text-align: center; border-radius: 8px; padding: 16px; position: fixed;
      z-index: 5000; left: 50%; bottom: 30px; transform: translateX(-50%);
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
    @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
    @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

    @media (max-width: 480px) {
      .form-grid { grid-template-columns: 1fr; }
      .full-width { grid-column: span 1; }
      .app-container { padding: 10px; }
    }
  </style>
</head>

<body>
  <div class="app-container">
    <div class="logo-header">
      <h1><?= $editing_own_profile ? 'Mi Perfil' : 'Gestión de Socios' ?></h1>
      <p>CanchaSport</p>
    </div>

    <div class="card">
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="back-btn">← Volver al Dashboard</a>

      <?php if ($is_ceo): ?>
        <!-- MODO CEO (Simplificado visualmente para mantener estilo) -->
        <div style="text-align:center; padding: 20px; color: #94a3b8;">
          <p>Modo Administrador Global Activo</p>
          <p style="font-size:0.8rem; margin-top:5px;">Funcionalidad completa disponible en versión Desktop.</p>
        </div>
        <!-- Aquí iría la tabla de CEO si fuera necesario, pero mantenemos foco en el perfil usuario -->
      <?php else: ?>
        
        <!-- MODO USUARIO / RESPONSABLE -->
        <?php if ($socio_editar): ?>
          
          <button class="btn btn-edit" onclick="openSocioModal('update', 
            <?= $socio_editar['id_socio'] ?>, 
            '<?= addslashes(htmlspecialchars($socio_editar['alias'])) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['fecha_nac'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['celular'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['email'])) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['direccion'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['rol'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['foto_url'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars(strtolower($socio_editar['genero'] ?? ''))) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['id_puesto'] ?? '')) ?>',
            '<?= addslashes(htmlspecialchars($socio_editar['habilidad'] ?? '')) ?>'
          )">
            ✏️ Editar Mi Perfil
          </button>
          
          <!-- Vista de Perfil Actual (Estilo Tarjeta) -->
          <h3 style="margin-bottom:15px; font-size:1.1rem; color:#e2e8f0;">Datos Actuales</h3>
          <div class="profile-view">
            <div class="profile-item"><span class="profile-label">Alias</span><span class="profile-value"><?= htmlspecialchars($socio_editar['alias']) ?></span></div>
            <div class="profile-item"><span class="profile-label">Email</span><span class="profile-value"><?= htmlspecialchars($socio_editar['email']) ?></span></div>
            <div class="profile-item"><span class="profile-label">Celular</span><span class="profile-value"><?= htmlspecialchars($socio_editar['celular'] ?? '-') ?></span></div>
            <div class="profile-item"><span class="profile-label">Rol</span><span class="profile-value"><?= htmlspecialchars($socio_editar['rol']) ?></span></div>
            <div class="profile-item"><span class="profile-label">Club</span><span class="profile-value"><?= htmlspecialchars($socio_editar['club_nombre'] ?? 'Individual') ?></span></div>
            <div class="profile-item"><span class="profile-label">Puesto</span><span class="profile-value"><?= htmlspecialchars($socio_editar['puesto_nombre'] ?? '-') ?></span></div>
            <div class="profile-item"><span class="profile-label">Habilidad</span><span class="profile-value"><?= htmlspecialchars($socio_editar['habilidad']) ?></span></div>
            <div class="profile-item"><span class="profile-label">Género</span><span class="profile-value"><?= htmlspecialchars(ucfirst($socio_editar['genero'])) ?></span></div>
          </div>

        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Submodal para insertar/editar (Estilo V2) -->
  <div id="socioModal" class="submodal" style="display:none;">
    <div class="submodal-content">
      <span class="close-modal" onclick="closeSocioModal()">&times;</span>
      <h2 id="modalTitle" class="modal-title">Editar Perfil</h2>
      
      <form id="socioForm" onsubmit="saveSocio(event)" enctype="multipart/form-data">
        <input type="hidden" id="socioId" name="id_socio">
        <input type="hidden" id="actionType" name="action" value="update">
        <input type="hidden" id="originalEmail" name="original_email">
        
        <div class="form-grid">
          <div class="input-group full-width">
            <label class="input-label">Alias *</label>
            <input type="text" id="socioAlias" name="alias" class="input" required>
          </div>
          
          <div class="input-group">
            <label class="input-label">Fecha Nacimiento</label>
            <input type="date" id="socioFechaNac" name="fecha_nac" class="input">
          </div>
          
          <div class="input-group">
            <label class="input-label">Celular</label>
            <input type="text" id="socioCelular" name="celular" class="input">
          </div>
          
          <div class="input-group full-width">
            <label class="input-label">Email *</label>
            <input type="email" id="socioEmail" name="email" class="input" required>
          </div>
          
          <div class="input-group full-width">
            <label class="input-label">Dirección</label>
            <textarea id="socioDireccion" name="direccion" rows="2" class="input"></textarea>
          </div>
          
          <div class="input-group">
            <label class="input-label">Rol</label>
            <select id="socioRol" name="rol" class="input" required>
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
          
          <div class="input-group">
            <label class="input-label">Género</label>
            <select id="socioGenero" name="genero" class="input">
              <option value="">Seleccionar</option>
              <option value="masculino">Masculino</option>
              <option value="femenino">Femenino</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          
          <div class="input-group">
            <label class="input-label">Puesto</label>
            <select id="socioPuesto" name="id_puesto" class="input">
              <option value="">Seleccionar</option>
              <?php foreach ($puestos as $puesto): ?>
              <option value="<?= $puesto['id_puesto'] ?>"><?= htmlspecialchars($puesto['puesto']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="input-group">
            <label class="input-label">Habilidad</label>
            <select id="socioHabilidad" name="habilidad" class="input">
                <option value="">Seleccionar</option>
                <option value="Básica">Malo</option>
                <option value="Intermedia">Más o Menos</option>
                <option value="Avanzada">Crack</option>
            </select>
          </div>

          <div class="input-group full-width">
            <label class="input-label">Foto de Perfil</label>
            <input type="file" id="socioFoto" name="foto_url" accept="image/*" class="input" style="padding: 8px;">
          </div>
        </div>
        
        <button type="submit" class="btn">Guardar Cambios</button>
        
        <?php if (!$is_ceo): ?>
          <button type="button" class="btn btn-danger" onclick="eliminarSocio(<?= $socio_editar['id_socio'] ?>)">
            🗑️ Eliminar mi cuenta
          </button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div id="toast">Mensaje</div>

  <script>
    // Funciones para CEO (Placeholder si es necesario)
    <?php if ($is_ceo): ?>
    function searchSocios() { /* ... */ }
    function selectSocio() { /* ... */ }
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
      
      const generoSelect = document.getElementById('socioGenero');
      generoSelect.value = genero.toLowerCase();
      
      document.getElementById('socioPuesto').value = id_puesto;
      document.getElementById('socioHabilidad').value = habilidad;
      
      document.getElementById('socioModal').style.display = 'flex';
    }

    function saveSocio(event) {
      event.preventDefault();
      const fechaNacInput = document.getElementById('socioFechaNac');
      const fechaNac = fechaNacInput.value;
      
      if (fechaNac) {
          const hoy = new Date();
          const nacimiento = new Date(fechaNac);
          let edad = hoy.getFullYear() - nacimiento.getFullYear();
          const mes = hoy.getMonth() - nacimiento.getMonth();
          if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) { edad--; }
          if (edad < 14) {
              mostrarToast('❌ Edad mínima: 14 años');
              return;
          }
      } else {
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
      
      const fotoFile = document.getElementById('socioFoto').files[0];
      if (fotoFile) formData.append('foto_url', fotoFile);
      
      fetch('../api/gestion_socios.php', { method: 'POST', body: formData })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          mostrarToast('✅ Perfil actualizado');
          setTimeout(() => location.reload(), 1500);
        } else {
          mostrarToast('❌ Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        mostrarToast('❌ Error de conexión');
      });
    }

    function closeSocioModal() {
      document.getElementById('socioModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
      const modal = document.getElementById('socioModal');
      if (event.target === modal) closeSocioModal();
    }

    function mostrarToast(mensaje) {
        const t = document.getElementById("toast");
        if(!t) return;
        t.textContent = mensaje;
        t.className = "show";
        setTimeout(() => t.className = t.className.replace("show", ""), 3000);
    }

    function eliminarSocio(idSocio) {
        if (!confirm('¿Seguro de eliminar tu cuenta? Esta acción es irreversible.')) return;
        fetch('../api/eliminar_socio.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id_socio: idSocio})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                mostrarToast('✅ Cuenta eliminada');
                setTimeout(() => location.href = '../index.php', 1500);
            } else {
                mostrarToast('❌ ' + data.message);
            }
        });
    }
  </script>
</body>
</html>