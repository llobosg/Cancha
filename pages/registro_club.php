<!-- pages/registro_club.php -->
<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener regiones únicas desde la base de datos
$stmt_regiones = $pdo->query("SELECT DISTINCT codigo_region, nombre_region FROM regiones_chile ORDER BY nombre_region");
$regiones_chile = [];
while ($row = $stmt_regiones->fetch()) {
    $regiones_chile[$row['codigo_region']] = $row['nombre_region'];
}

$error_message = '';
$error_type = '';
$success = false;
$club_slug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos requeridos
        $required = ['nombre', 'deporte', 'jugadores_por_lado', 'ciudad', 'comuna', 'responsable', 'email_responsable'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Todos los campos marcados son obligatorios');
            }
        }

        // Validar email_responsable
        if (!filter_var($_POST['email_responsable'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico del responsable inválido');
        }

        // Validar jugadores_por_lado
        $jugadores = (int)$_POST['jugadores_por_lado'];
        if ($jugadores < 1 || $jugadores > 20) {
            throw new Exception('Jugadores por lado debe estar entre 1 y 20');
        }

        // Verificar si el correo ya tiene un club registrado
        $stmt_check = $pdo->prepare("SELECT id_club FROM clubs WHERE email_responsable = ?");
        $stmt_check->execute([$_POST['email_responsable']]);
        if ($stmt_check->fetch()) {
            $error_type = 'duplicate';
            $error_message = 'duplicate_email';
        } else {
            // Subir logo si existe
            $logo_filename = null;
            if (!empty($_FILES['logo']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                    throw new Exception('Solo se permiten imágenes JPG, PNG o GIF');
                }
                
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('El logo debe pesar menos de 2MB');
                }
                
                $logo_filename = uniqid() . '_' . basename($_FILES['logo']['name']);
                $upload_dir = __DIR__ . '/../uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename)) {
                    throw new Exception('Error al subir el logo');
                }
            }

            // Insertar club
            $stmt = $pdo->prepare("
                INSERT INTO clubs (
                    nombre, deporte, jugadores_por_lado, fecha_fundacion, pais, ciudad, comuna, 
                    responsable, telefono, email_responsable, logo, email_verified, created_at
                ) VALUES (?, ?, ?, ?, 'Chile', ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['deporte'],
                $jugadores,
                $_POST['fecha_fundacion'] ?: null,
                $_POST['ciudad'],
                $_POST['comuna'],
                $_POST['responsable'],
                $_POST['telefono'] ?: null,
                $_POST['email_responsable'],
                $logo_filename
            ]);

            $club_id = $pdo->lastInsertId();

            // Crear socio automático
            $verification_code = rand(1000, 9999);
            $stmt = $pdo->prepare("
                INSERT INTO socios (id_club, email, nombre, alias, verification_code, es_responsable, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $club_id, 
                $_POST['email_responsable'],
                $_POST['responsable'], 
                'Responsable', 
                $verification_code
            ]);

            $club_slug = substr(md5($club_id . $_POST['email_responsable']), 0, 8);
            $success = true;
        }

    } catch (Exception $e) {
        $error_type = 'general';
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registra tu Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 10, 20, 0.40), rgba(0, 15, 30, 0.50)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
    }

    .form-container {
      width: 95%;
      max-width: 900px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
    }

    @media (max-width: 768px) {
      body {
        background: white !important;
        color: #333 !important;
      }
      
      .form-container {
        width: 100%;
        max-width: none;
        height: auto;
        min-height: 100vh;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        padding: 1.5rem;
        background: white !important;
      }
    }

    .close-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 2.2rem;
      color: #003366;
      text-decoration: none;
      opacity: 0.7;
      transition: opacity 0.2s;
      z-index: 10;
    }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.6rem;
    }

    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .success {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 0.8rem 1.2rem;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin: 0;
    }

    .form-group label {
      text-align: right;
      padding-right: 0.5rem;
      display: block;
      font-size: 0.85rem;
      color: #333;
      font-weight: normal;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.85rem;
      color: #071289;
      background: #fafcff;
    }

    .col-span-2 {
      grid-column: span 2;
    }

    .col-span-nombre {
      grid-column: span 2;
    }

    .empty-col {
      visibility: hidden;
    }

    .submit-section {
      grid-column: 1 / -1;
      text-align: center;
      margin-top: 1.8rem;
    }

    .btn-submit {
      width: auto;
      min-width: 220px;
      padding: 0.65rem 1.8rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 0.95rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.7rem;
      }
      
      .col-span-nombre {
        grid-column: span 2 !important;
      }
      
      .empty-col {
        display: none;
      }
      
      .form-group label {
        text-align: left;
        padding-right: 0;
        font-size: 0.8rem;
      }
      
      .form-group input,
      .form-group select {
        font-size: 0.85rem;
        padding: 0.45rem;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>

    <?php if ($success): ?>
      <h2>✅ ¡Club registrado exitosamente!</h2>
      <div class="success">
        Hemos creado tu club y te hemos inscrito automáticamente como responsable.
        <br>Recibirás un código de verificación en tu correo para activar tu cuenta.
      </div>
    <?php else: ?>
      <h2>Registra tu Club ⚽</h2>

      <?php if ($error_message): ?>
        <div class="error">
          <?php if ($error_type === 'duplicate'): ?>
            <div style="text-align: left; line-height: 1.6;">
              <strong>⚠️ ¡Hola! Ya tienes un club registrado con este correo.</strong><br><br>
              En Cancha, la versión <strong>Gratuita</strong> permite registrar <strong>1 club por responsable</strong>.<br><br>
              Si deseas gestionar <strong>múltiples clubes</strong>, te invitamos a conocer nuestra versión <strong>Premiere League</strong> con beneficios exclusivos:<br>
              • Gestión de múltiples clubes<br>
              • Estadísticas avanzadas<br>
              • Soporte prioritario<br>
              • Funciones premium<br><br>
              ¿Te interesa? Escríbenos a <strong>hola@cancha-sport.cl</strong> para más información.
            </div>
          <?php else: ?>
            <?= htmlspecialchars($error_message) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        
        <div class="form-grid">
          <!-- Fila 1 -->
          <div class="form-group"><label for="nombre">Nombre club *</label></div>
          <div class="form-group col-span-nombre"><input type="text" id="nombre" name="nombre" required></div>
          <div class="form-group"><label for="fecha_fundacion">Fecha Fund.</label></div>
          <div class="form-group"><input type="date" id="fecha_fundacion" name="fecha_fundacion"></div>
          <div class="form-group empty-col"></div>

          <!-- Fila 2 -->
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

          <!-- Fila 3 -->
          <div class="form-group"><label for="deporte">Deporte *</label></div>
          <div class="form-group">
            <select id="deporte" name="deporte" required>
              <option value="">Seleccionar</option>
              <option value="futbol">Fútbol</option>
              <option value="futbolito">Futbolito</option>
              <option value="baby">Baby fútbol</option>
              <option value="tenis">Tenis</option>
              <option value="padel">Pádel</option>
            </select>
          </div>
          <div class="form-group"><label for="jugadores_por_lado">Jugadores</label></div>
          <div class="form-group"><input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="14" required></div>
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>

          <!-- Fila 4 -->
          <div class="form-group"><label for="responsable">Responsable *</label></div>
          <div class="form-group"><input type="text" id="responsable" name="responsable" required></div>
          <div class="form-group"><label for="email_responsable">Correo *</label></div>
          <div class="form-group"><input type="email" id="email_responsable" name="email_responsable" required></div>
          <div class="form-group"><label for="telefono">Teléfono</label></div>
          <div class="form-group"><input type="tel" id="telefono" name="telefono"></div>

          <!-- Espacios vacíos para mantener alineación -->
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>
          <div class="form-group empty-col"></div>

          <!-- LOGO al final -->
          <div class="form-group"><label for="logo">Logo del club</label></div>
          <div class="form-group col-span-2"><input type="file" id="logo" name="logo" accept="image/*"></div>
          <div class="form-group"></div>
          <div class="form-group"></div>
          <div class="form-group"></div>

          <!-- Botón -->
          <div class="submit-section">
            <button type="submit" class="btn-submit">Registrar club</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    // Cargar datos de regiones desde API
    let datosChile = {};
    
    fetch('../api/get_regiones.php')
      .then(response => response.json())
      .then(data => {
        datosChile = data;
        console.log('Regiones cargadas:', Object.keys(datosChile).length);
      })
      .catch(error => {
        console.error('Error al cargar regiones:', error);
        alert('Error al cargar las regiones. Por favor recarga la página.');
      });

    function actualizarCiudades() {
      const region = document.getElementById('region').value;
      const ciudadSelect = document.getElementById('ciudad');
      const comunaSelect = document.getElementById('comuna');
      
      ciudadSelect.innerHTML = '<option value="">Seleccionar ciudad</option>';
      comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
      ciudadSelect.disabled = !region;
      comunaSelect.disabled = true;
      
      if (region && datosChile[region]) {
        Object.entries(datosChile[region].ciudades).forEach(([codigo, nombre]) => {
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
      comunaSelect.disabled = !(region && ciudad && datosChile[region]?.comunas?.[ciudad]);
      
      if (region && ciudad && datosChile[region]?.comunas?.[ciudad]) {
        datosChile[region].comunas[ciudad].forEach(comuna => {
          const option = document.createElement('option');
          option.value = comuna.toLowerCase().replace(/\s+/g, '_');
          option.textContent = comuna;
          comunaSelect.appendChild(option);
        });
        comunaSelect.disabled = false;
      }
    });

    document.getElementById('telefono')?.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9+]/g, '');
    });
  </script>
</body>
</html>