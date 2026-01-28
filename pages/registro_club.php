<!-- pages/registro_club.php -->
<?php
require_once __DIR__ . '/../includes/config.php';

// Datos de Chile
$regiones_chile = [
    '1' => 'Tarapacá',
    '2' => 'Antofagasta', 
    '3' => 'Atacama',
    '4' => 'Coquimbo',
    '5' => 'Valparaíso',
    '6' => 'O\'Higgins',
    '7' => 'Maule',
    '8' => 'Biobío',
    '9' => 'La Araucanía',
    '10' => 'Los Lagos',
    '11' => 'Aysén',
    '12' => 'Magallanes',
    '13' => 'Metropolitana',
    '14' => 'Los Ríos',
    '15' => 'Arica y Parinacota',
    '16' => 'Ñuble'
];

$error = '';
$success = false;
$club_slug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos requeridos
        $required = ['nombre', 'deporte', 'region', 'ciudad', 'comuna', 'responsable', 'correo'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Todos los campos marcados son obligatorios');
            }
        }

        // Validar correo
        if (!filter_var($_POST['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico inválido');
        }

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
                nombre, deporte, fecha_fundacion, pais, region, ciudad, comuna, 
                responsable, correo, telefono, logo, email_verified, created_at
            ) VALUES (?, ?, ?, 'Chile', ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['deporte'],
            $_POST['fecha_fundacion'] ?: null,
            $_POST['region'],
            $_POST['ciudad'],
            $_POST['comuna'],
            $_POST['responsable'],
            $_POST['correo'],
            $_POST['telefono'] ?: null,
            $logo_filename
        ]);

        $club_id = $pdo->lastInsertId();

        // Crear socio automático para el responsable
        $verification_code = rand(1000, 9999);
        $stmt = $pdo->prepare("
            INSERT INTO socios (id_club, email, nombre, alias, verification_code, es_responsable, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $club_id, 
            $_POST['correo'], 
            $_POST['responsable'], 
            'Responsable', 
            $verification_code
        ]);

        // Generar slug del club
        $club_slug = substr(md5($club_id . $_POST['correo']), 0, 8);

        $success = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
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

    .close-btn:hover {
      opacity: 1;
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
    .form-group select,
    .form-group textarea {
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

    .btn-submit:hover {
      background: #050d66;
    }

    /* QR Section */
    .qr-section {
      text-align: center;
      padding: 2rem;
      background: #f8f9fa;
      border-radius: 12px;
      margin-top: 2rem;
    }

    .qr-code {
      margin: 1rem auto;
      width: 200px;
      height: 200px;
      background: #fff;
      padding: 10px;
      border-radius: 8px;
    }

    .share-link {
      background: #e9ecef;
      padding: 0.8rem;
      border-radius: 6px;
      margin: 1rem 0;
      word-break: break-all;
      font-family: monospace;
      font-size: 0.9rem;
    }

    .copy-btn {
      background: #071289;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 0.5rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.7rem;
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
    <a href="index.php" class="close-btn" title="Volver al inicio">×</a>

    <?php if ($success): ?>
      <h2>✅ ¡Club registrado exitosamente!</h2>
      
      <div class="success">
        Hemos creado tu club y te hemos inscrito automáticamente como responsable.
        <br>Recibirás un código de verificación en tu correo para activar tu cuenta.
      </div>

      <div class="qr-section">
        <h3>Comparte tu club</h3>
        <p>Envía este enlace a tus compañeros para que se inscriban fácilmente:</p>
        
        <?php
        $share_url = "https://cancha-sport.cl/pages/registro_socio.php?club=" . $club_slug;
        ?>
        
        <div class="qr-code" id="qrCode"></div>
        <div class="share-link" id="shareLink"><?= htmlspecialchars($share_url) ?></div>
        <button class="copy-btn" onclick="copyLink()">📋 Copiar enlace</button>
      </div>

    <?php else: ?>
      <h2>Registra tu Club ⚽</h2>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        
        <div class="form-grid">
          <!-- Fila 1 -->
          <div class="form-group"><label for="nombre">Nombre club *</label></div>
          <div class="form-group col-span-2"><input type="text" id="nombre" name="nombre" required></div>
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
          <div class="form-group"></div>

          <!-- Fila 2 -->
          <div class="form-group"><label for="fecha_fundacion">Fecha Fund.</label></div>
          <div class="form-group"><input type="date" id="fecha_fundacion" name="fecha_fundacion"></div>
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

          <!-- Fila 3 -->
          <div class="form-group"><label for="comuna">Comuna *</label></div>
          <div class="form-group">
            <select id="comuna" name="comuna" required disabled>
              <option value="">Seleccionar ciudad primero</option>
            </select>
          </div>
          <div class="form-group"><label for="responsable">Responsable *</label></div>
          <div class="form-group"><input type="text" id="responsable" name="responsable" required></div>
          <div class="form-group"><label for="correo">Correo *</label></div>
          <div class="form-group"><input type="email" id="correo" name="correo" required></div>

          <!-- Fila 4 -->
          <div class="form-group"><label for="telefono">Teléfono</label></div>
          <div class="form-group"><input type="tel" id="telefono" name="telefono"></div>
          <div class="form-group"></div>
          <div class="form-group"></div>
          <div class="form-group"></div>
          <div class="form-group"></div>

          <!-- LOGO al final -->
          <div class="form-group"><label for="logo">Logo del club</label></div>
          <div class="form-group col-span-2"><input type="file" id="logo" name="logo" accept="image/*"></div>
          <div class="form-group"></div>
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

  <?php if ($success): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
      // Generar QR
      const shareUrl = '<?= htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8') ?>';
      new QRCode(document.getElementById("qrCode"), {
        text: shareUrl,
        width: 180,
        height: 180,
        colorDark: "#003366",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });

      function copyLink() {
        const link = document.getElementById('shareLink').textContent;
        navigator.clipboard.writeText(link).then(() => {
          alert('¡Enlace copiado al portapapeles!');
        });
      }
    </script>
  <?php endif; ?>

  <script>
    // Datos de ciudades y comunas por región
    const datosChile = {
      '13': { // Metropolitana
        ciudades: {
          'santiago': 'Santiago',
          'providencia': 'Providencia',
          'las_condes': 'Las Condes',
          'vitacura': 'Vitacura',
          'ñuñoa': 'Ñuñoa'
        },
        comunas: {
          'santiago': ['Santiago Centro', 'Bellavista', 'Barrio Brasil'],
          'providencia': ['Providencia Centro', 'Plaza Baquedano'],
          'las_condes': ['Las Condes Centro', 'Los Trapenses'],
          'vitacura': ['Vitacura Centro', 'San Carlos de Apoquindo'],
          'ñuñoa': ['Ñuñoa Centro', 'Plaza Ñuñoa']
        }
      },
      '5': { // Valparaíso
        ciudades: {
          'valparaiso': 'Valparaíso',
          'vina_del_mar': 'Viña del Mar',
          'quilpue': 'Quilpué'
        },
        comunas: {
          'valparaiso': ['Valparaíso Centro', 'Cerro Alegre', 'Cerro Concepción'],
          'vina_del_mar': ['Viña Centro', 'Reñaca', 'Forestal'],
          'quilpue': ['Quilpué Centro', 'Villa Alemana']
        }
      }
      // Agrega más regiones según necesites
    };

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

    document.getElementById('ciudad').addEventListener('change', function() {
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

    // Validación de teléfono
    document.getElementById('telefono')?.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9+]/g, '');
    });
  </script>
</body>
</html>