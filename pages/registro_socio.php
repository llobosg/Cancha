<?php
require_once __DIR__ . '/../includes/config.php';

// Evitar problemas de headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === DETECTAR MODO TORNEO ===
$modo_torneo = ($_GET['modo'] ?? '') === 'torneo';
$torneo_slug = $modo_torneo ? ($_GET['slug'] ?? '') : null;
$torneo_code = $modo_torneo ? ($_GET['code'] ?? '') : null;

if ($modo_torneo) {
    if (!$torneo_slug || !$torneo_code || strlen($torneo_slug) !== 8 || strlen($torneo_code) !== 8) {
        header('Location: ../index.php');
        exit;
    }
    // Guardar contexto para redirigir tras registro
    $_SESSION['post_registro_torneo'] = ['slug' => $torneo_slug, 'code' => $torneo_code];
}

// === MODO CLUB O INDIVIDUAL ===
$club_slug_from_url = $_GET['club'] ?? '';
$modo_individual = empty($club_slug_from_url) && !$modo_torneo;

if ($modo_individual) {
    // Modo individual: deportes no grupales
    $stmt_deportes = $pdo->prepare("SELECT deporte FROM deportes WHERE tipo_deporte = '1' ORDER BY deporte");
    $stmt_deportes->execute();
    $deportes_disponibles = $stmt_deportes->fetchAll(PDO::FETCH_COLUMN);
    $club = null;
    $club_nombre = 'Registro Individual';
    $club_logo = null;
} else {
    // Modo club
    if (strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
        header('Location: ../index.php');
        exit;
    }

    $stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre, logo FROM clubs WHERE email_verified = 1");
    $stmt_club->execute();
    $clubs = $stmt_club->fetchAll();

    $club = null;
    foreach ($clubs as $c) {
        $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
        if ($generated_slug === $club_slug_from_url) {
            $club = $c;
            break;
        }
    }

    if (!$club) {
        header('Location: ../index.php');
        exit;
    }

    $club_id = (int)$club['id_club'];
    $club_nombre = $club['nombre'];
    $club_logo = $club['logo'] ?? null;

    // Deportes grupales
    $stmt_deportes = $pdo->prepare("SELECT deporte FROM deportes WHERE tipo_deporte = '2' ORDER BY deporte");
    $stmt_deportes->execute();
    $deportes_disponibles = $stmt_deportes->fetchAll(PDO::FETCH_COLUMN);
}

// Cargar regiones de Chile
$stmt_regiones = $pdo->query("SELECT DISTINCT codigo_region, nombre_region FROM regiones_chile ORDER BY nombre_region");
$regiones_chile = [];
while ($row = $stmt_regiones->fetch()) {
    $regiones_chile[$row['codigo_region']] = $row['nombre_region'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $modo_torneo ? 'Registro Express - Torneo' : ($modo_individual ? 'Socio Individual 💪' : 'Inscríbete a: ' . htmlspecialchars($club_nombre)) ?></title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="mobile-web-app-capable" content="yes">
  <style>
    body {
      background: linear-gradient(rgba(0, 10, 20, 0.40), rgba(0, 15, 30, 0.50)),
                 url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0; padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex; justify-content: center; align-items: center;
      color: white;
    }
    .form-container {
      width: 95%; max-width: 1200px;
      background: white; padding: 2rem;
      border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative; margin: 0 auto;
    }
    .form-container::before, .form-container::after {
      content: "⚽🎾🏐";
      position: absolute; font-size: 2.2rem; color: #003366; opacity: 0.65; z-index: 2;
    }
    .form-container::before { top: 22px; left: 22px; }
    .form-container::after { bottom: 22px; right: 22px; }
    .close-btn {
      position: absolute; top: 15px; right: 15px; font-size: 2.2rem; color: #003366;
      text-decoration: none; opacity: 0.7; transition: opacity 0.2s; z-index: 10;
    }
    .header-container {
      display: flex; flex-direction: column; align-items: center; gap: 1rem; margin-bottom: 1.8rem;
    }
    .header-container h2 {
      text-align: center; color: #003366; font-weight: 700; font-size: 1.4rem; margin: 0;
    }
    .club-header {
      display: flex; align-items: center; gap: 0.8rem;
      background: rgba(0, 51, 102, 0.05); padding: 0.6rem 1.2rem;
      border-radius: 12px; border: 1px solid rgba(0, 51, 102, 0.1);
    }
    .club-logo {
      width: 45px; height: 45px; border-radius: 8px; object-fit: cover;
      background: #e0e0e0; display: flex; align-items: center; justify-content: center;
      font-weight: bold; color: #666; font-size: 1.1rem;
    }
    .club-name { font-size: 1.2rem; font-weight: 600; color: #ba08e7ff; white-space: nowrap; }
    .form-grid {
      display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem 1.5rem; margin-bottom: 1.5rem;
    }
    .form-group { margin: 0; }
    .form-group label {
      text-align: right; padding-right: 0.5rem; display: block;
      font-size: 0.85rem; color: #333; font-weight: normal;
    }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 5px;
      font-size: 0.9rem; color: #071289; background: #fafcff;
    }
    .col-span-2 { grid-column: span 2; }
    .submit-section {
      grid-column: 1 / -1; text-align: center; margin-top: 1.8rem;
    }
    .btn-submit {
      width: auto; min-width: 220px; padding: 0.65rem 1.8rem;
      background: #071289; color: white; border: none; border-radius: 6px;
      font-size: 0.95rem; font-weight: bold; cursor: pointer; transition: background 0.2s;
    }
    .btn-submit:hover { background: #050d66; }
    .error {
      background: #ffebee; color: #c62828; padding: 0.7rem; border-radius: 6px;
      margin-bottom: 1.5rem; text-align: center; font-size: 0.85rem;
    }
    @media (max-width: 768px) {
      body { background: white !important; color: #333 !important; }
      .form-container {
        width: 100%; max-width: none; min-height: 100vh; border-radius: 0;
        box-shadow: none; padding: 1.5rem; background: white !important;
      }
      .form-container::before, .form-container::after { display: none; }
      .form-grid { grid-template-columns: 1fr 2fr; gap: 0.8rem; }
      .form-group label { text-align: left; padding-right: 0; font-weight: bold; }
      .full-width-mobile { grid-column: span 2 !important; }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>

    <div class="header-container">
      <h2>
        <?= $modo_torneo ? '✅ Registro Express - Torneo' : ($modo_individual ? 'Socio Individual 💪' : 'Inscríbete a:') ?>
      </h2>
      <div class="club-header">
        <?php if ($modo_individual || $modo_torneo): ?>
          🎾🏐🏊‍♂️
        <?php else: ?>
          <div class="club-logo">
            <?php if ($club_logo): ?>
              <img src="../uploads/logos/<?= htmlspecialchars($club_logo) ?>" alt="Logo" style="width:100%;height:100%;border-radius:8px;">
            <?php else: ?>
              ⚽
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="club-name">
          <?= htmlspecialchars($modo_individual || $modo_torneo ? '' : $club_nombre) ?>
        </div>
      </div>
    </div>

    <?php if ($_GET['error'] ?? ''): ?>
      <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form id="registroForm" enctype="multipart/form-data">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
      <input type="hidden" name="pais" value="Chile">
      
      <?php if (!$modo_individual && !$modo_torneo): ?>
        <input type="hidden" name="club_slug" value="<?= htmlspecialchars($club_slug_from_url) ?>">
      <?php endif; ?>

      <!-- Modo torneo: forzar valores -->
      <?php if ($modo_torneo): ?>
        <input type="hidden" name="rol" value="Jugador">
        <input type="hidden" name="deporte" value="Pádel">
      <?php endif; ?>

      <div class="form-grid">
        <!-- Fila 1 -->
        <div class="form-group"><label for="nombre">Nombre</label></div>
        <div class="form-group"><input type="text" id="nombre" name="nombre" required></div>
        <div class="form-group"><label for="alias">Alias</label></div>
        <div class="form-group"><input type="text" id="alias" name="alias" required></div>
        
        <!-- Rol -->
        <div class="form-group"><label for="rol">Rol</label></div>
        <div class="form-group">
          <?php if ($modo_individual || $modo_torneo): ?>
            <!-- Campo oculto + visual solo lectura -->
            <input type="hidden" name="rol" value="Jugador">
            <input type="text" value="Jugador" disabled style="padding:0.6rem;border:1px solid #ccc;border-radius:5px;background:#fafcff;color:#071289;width:100%;">
          <?php else: ?>
            <select id="rol" name="rol" required>
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
          <?php endif; ?>
        </div>

        <!-- Fila 2 -->
        <div class="form-group"><label for="fecha_nac">Fecha Nac.</label></div>
        <div class="form-group"><input type="date" id="fecha_nac" name="fecha_nac"></div>
        <div class="form-group"><label for="genero">Género</label></div>
        <div class="form-group">
          <select id="genero" name="genero" required>
            <option value="">Seleccionar</option>
            <option value="Femenino">Femenino</option>
            <option value="Masculino">Masculino</option>
            <option value="Otro">Otro</option>
          </select>
        </div>
        <div class="form-group"><label for="celular">Celular</label></div>
        <div class="form-group"><input type="tel" id="celular" name="celular"></div>

        <!-- Fila 3 -->
        <div class="form-group"><label for="region">Región *</label></div>
        <div class="form-group">
          <select id="region" name="region" required onchange="actualizarCiudades()">
            <option value="">Seleccionar región</option>
            <?php foreach ($regiones_chile as $codigo => $nombre): ?>
              <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="ciudad">Ciudad *</label></div>
        <div class="form-group">
          <select id="ciudad" name="ciudad" required disabled>
            <option value="">Seleccionar región primero</option>
          </select>
        </div>
        <div class="form-group"><label for="comuna">Comuna *</label></div>
        <div class="form-group">
          <select id="comuna" name="comuna" required disabled>
            <option value="">Seleccionar ciudad primero</option>
          </select>
        </div>

        <!-- Fila 4 -->
        <div class="form-group"><label for="direccion">Dirección</label></div>
        <div class="form-group full-width-mobile"><input type="text" id="direccion" name="direccion" placeholder="Ej: Av. Grecia 2001, Ñuñoa"></div>
        <div class="form-group"><label for="email">Correo</label></div>
        <div class="form-group"><input type="email" id="email" name="email" required></div>

        <!-- Fila 5 -->
        <div class="form-group"><label for="deporte">Deporte *</label></div>
        <div class="form-group">
          <?php if ($modo_torneo): ?>
            <input type="text" value="Pádel" disabled style="padding:0.6rem;border:1px solid #ccc;border-radius:5px;background:#f0f0f0;color:#071289;width:100%;">
          <?php else: ?>
            <select id="deporte" name="deporte" required>
              <option value="">Seleccionar deporte..</option>
              <?php foreach ($deportes_disponibles as $dep): ?>
                <option value="<?= $dep ?>"><?= htmlspecialchars($dep) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="form-group"><label for="id_puesto">Puesto</label></div>
        <div class="form-group">
          <select id="id_puesto" name="id_puesto" required>
            <option value="">Seleccionar</option>
          </select>
        </div>
        <div class="form-group"><label for="habilidad">Habilidad</label></div>
        <div class="form-group">
          <select id="habilidad" name="habilidad" required>
            <option value="">Seleccionar</option>
            <option value="Básica">Malo</option>
            <option value="Intermedia">Más o Menos</option>
            <option value="Avanzada">Crack</option>
          </select>
        </div>

        <!-- Fila 6 -->
        <div class="form-group"><label for="foto">Foto</label></div>
        <div class="form-group col-span-2"><input type="file" id="foto" name="foto" accept="image/*"></div>
        <div></div><div></div><div></div><div></div>

        <!-- Fila 7 -->
        <div class="form-group"><label for="password">Contraseña *</label></div>
        <div class="form-group"><input type="password" id="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres"></div>
        <div class="form-group"><label for="password_confirm">Confirmar *</label></div>
        <div class="form-group"><input type="password" id="password_confirm" name="password_confirm" required></div>
        <div></div><div></div>
      </div>

      <div class="submit-section">
        <button type="submit" class="btn-submit">Enviar código de verificación</button>
      </div>
      <?php
      $torneo_slug = $_GET['torneo'] ?? null;
      if ($torneo_slug): ?>
          <input type="hidden" name="torneo_slug" value="<?= htmlspecialchars($torneo_slug) ?>">
      <?php endif; ?>
    </form>
  </div>

  <!-- Toast -->
  <div id="toast" class="toast" style="display:none;">
    <span>ℹ️</span>
    <span id="toast-message">Mensaje</span>
  </div>

  <script>
    // === VALIDACIÓN EDAD ===
    function validarEdad(fechaNac) {
      if (!fechaNac) return true;
      const hoy = new Date();
      const nacimiento = new Date(fechaNac);
      let edad = hoy.getFullYear() - nacimiento.getFullYear();
      const mes = hoy.getMonth() - nacimiento.getMonth();
      if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
      return edad >= 14;
    }

    // === MANEJO FORMULARIO ===
    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const fechaNacInput = document.getElementById('fecha_nac');
      if (fechaNacInput.value && !validarEdad(fechaNacInput.value)) {
        alert('La edad mínima es 14 años');
        return;
      }
      
      const password = document.getElementById('password').value;
      const passwordConfirm = document.getElementById('password_confirm').value;
      if (password !== passwordConfirm) {
        alert('Las contraseñas no coinciden');
        return;
      }
      if (password.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres');
        return;
      }

      const formData = new FormData(e.target);
      const btn = e.submitter;
      const originalText = btn.innerHTML;
      btn.innerHTML = 'Enviando...';
      btn.disabled = true;

      try {
        const response = await fetch('../api/enviar_codigo_socio.php', {
          method: 'POST',
          body: formData
        });
        const textResponse = await response.text();
        let data;
        try {
          data = JSON.parse(textResponse);
        } catch (e) {
          throw new Error('Error interno del servidor');
        }
        
        if (data.success) {
          alert('✅ Código enviado a tu correo');
          <?php if ($modo_torneo): ?>
            window.location.href = '/torneo_pair.php?slug=<?= $torneo_slug ?>&code=<?= $torneo_code ?>';
          <?php else: ?>
            if (data.club_slug && data.club_slug.trim() !== '') {
              window.location.href = 'verificar_socio.php?club=' + encodeURIComponent(data.club_slug);
            } else {
              window.location.href = 'verificar_socio.php?id_socio=' + encodeURIComponent(data.id_socio);
            }
          <?php endif; ?>
        } else {
          alert('❌ ' + data.message);
          btn.innerHTML = originalText;
          btn.disabled = false;
        }
      } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al enviar el código');
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });

    // === CARGAR PUESTOS ===
    function cargarPuestosPorDeporte(deporte) {
      const url = deporte 
        ? '../api/get_puestos.php?deporte=' + encodeURIComponent(deporte)
        : '../api/get_puestos.php';
      
      fetch(url)
        .then(r => r.json())
        .then(puestos => {
          const select = document.getElementById('id_puesto');
          select.innerHTML = '<option value="">Seleccionar</option>';
          puestos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id_puesto;
            opt.textContent = p.puesto;
            select.appendChild(opt);
          });
          
          // En modo torneo + Pádel, seleccionar "Sexta"
          if (<?= json_encode($modo_torneo) ?> && deporte === 'Pádel') {
            const sextaOpt = Array.from(select.options).find(opt => opt.textContent.trim() === 'Sexta');
            if (sextaOpt) select.value = sextaOpt.value;
          }
        })
        .catch(error => console.error('Error al cargar puestos:', error));
    }

    // === INICIALIZAR ===
    document.addEventListener('DOMContentLoaded', () => {
      const deporteSelect = document.getElementById('deporte');
      if (deporteSelect?.value) {
        cargarPuestosPorDeporte(deporteSelect.value);
      }
      deporteSelect?.addEventListener('change', function() {
        cargarPuestosPorDeporte(this.value);
      });

      // Cargar regiones
      fetch('../api/get_regiones.php')
        .then(response => response.json())
        .then(data => {
          window.datosChile = data;
        })
        .catch(error => console.error('Error al cargar regiones:', error));
    });

    function actualizarCiudades() {
      const region = document.getElementById('region').value;
      const ciudadSelect = document.getElementById('ciudad');
      const comunaSelect = document.getElementById('comuna');
      
      ciudadSelect.innerHTML = '<option value="">Seleccionar ciudad</option>';
      comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
      ciudadSelect.disabled = !region;
      comunaSelect.disabled = true;
      
      if (region && window.datosChile?.[region]) {
        Object.entries(window.datosChile[region].ciudades).forEach(([codigo, nombre]) => {
          const option = document.createElement('option');
          option.value = codigo;
          option.textContent = nombre;
          ciudadSelect.appendChild(option);
        });
        ciudadSelect.disabled = false;
      }
    }

    document.getElementById('ciudad')?.addEventListener('change', function() {
      const region = document.getElementById('region').value;
      const ciudad = this.value;
      const comunaSelect = document.getElementById('comuna');
      
      comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
      comunaSelect.disabled = !(region && ciudad && window.datosChile?.[region]?.comunas?.[ciudad]);
      
      if (region && ciudad && window.datosChile?.[region]?.comunas?.[ciudad]) {
        window.datosChile[region].comunas[ciudad].forEach(comuna => {
          const option = document.createElement('option');
          option.value = comuna.toLowerCase().replace(/\s+/g, '_');
          option.textContent = comuna;
          comunaSelect.appendChild(option);
        });
        comunaSelect.disabled = false;
      }
    });
  </script>
</body>
</html>