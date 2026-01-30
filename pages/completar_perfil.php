<?php
// INICIAR SESIÓN AL PRINCIPIO ABSOLUTO - SIN NADA ANTES
session_start();

require_once __DIR__ . '/../includes/config.php';

// Debug inicial
error_log("=== DEBUG COMPLETAR PERFIL ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("GET: " . print_r($_GET, true));
error_log("SESSION: " . print_r($_SESSION, true));

// Guardar club en sesión si viene por URL
if (isset($_GET['club'])) {
    $_SESSION['club_slug'] = $_GET['club'];
    error_log("Guardado club_slug en sesión: " . $_GET['club']);
}

// Obtener socio_id de la sesión
$socio_id = $_SESSION['id_socio'] ?? null;
$club_slug = $_SESSION['club_slug'] ?? null;

error_log("socio_id desde sesión: " . ($socio_id ?? 'NULL'));
error_log("club_slug desde sesión: " . ($club_slug ?? 'NULL'));

// Si no tenemos socio_id, intentar obtenerlo del email en sesión
if (!$socio_id && isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ?");
    $stmt->execute([$_SESSION['user_email']]);
    $result = $stmt->fetch();
    if ($result) {
        $socio_id = $result['id_socio'];
        $_SESSION['id_socio'] = $socio_id;
        error_log("socio_id obtenido por email: $socio_id");
    }
}

// Si seguimos sin socio_id pero tenemos club_slug, buscar como responsable
if (!$socio_id && $club_slug) {
    // Obtener el email del responsable desde la sesión o localStorage
    $email_responsable = $_SESSION['user_email'] ?? null;
    
    if ($email_responsable) {
        $stmt = $pdo->prepare("
            SELECT s.id_socio 
            FROM socios s 
            JOIN clubs c ON s.id_club = c.id_club 
            WHERE c.email_responsable = ? AND s.es_responsable = 1
        ");
        $stmt->execute([$email_responsable]);
        $result = $stmt->fetch();
        if ($result) {
            $socio_id = $result['id_socio'];
            $_SESSION['id_socio'] = $socio_id;
            error_log("socio_id obtenido como responsable: $socio_id");
        }
    }
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("=== PROCESANDO POST ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("FILES data: " . print_r($_FILES, true));
        
        // Validar campos obligatorios
        $required = ['alias', 'celular', 'direccion'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Campo obligatorio faltante: ' . $field);
            }
        }

        if (!$socio_id) {
            throw new Exception('No se pudo identificar tu perfil. socio_id es NULL.');
        }

        // Subir foto si existe
        $foto_url = null;
        if (!empty($_FILES['foto_url']['name'])) {
            error_log("Subiendo foto: " . $_FILES['foto_url']['name']);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['foto_url']['type'], $allowed_types)) {
                throw new Exception('Tipo de imagen no permitido');
            }
            
            if ($_FILES['foto_url']['size'] > 2 * 1024 * 1024) {
                throw new Exception('Imagen demasiado grande');
            }
            
            $foto_url = uniqid() . '_' . basename($_FILES['foto_url']['name']);
            $upload_dir = __DIR__ . '/../uploads/fotos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (!move_uploaded_file($_FILES['foto_url']['tmp_name'], $upload_dir . $foto_url)) {
                throw new Exception('Error al mover archivo');
            }
            error_log("Foto subida: $foto_url");
        }

        // Preparar datos para UPDATE
        $update_data = [
            $_POST['alias'],
            $_POST['fecha_nac'] ?: null,
            $_POST['celular'],
            $_POST['direccion'],
            $_POST['rol'] ?: null,
            $_POST['id_puesto'] ?: null,
            $_POST['genero'] ?: null,
            $_POST['habilidad'] ?: null,
            $foto_url,
            $socio_id
        ];
        
        error_log("Datos para UPDATE: " . print_r($update_data, true));

        // Ejecutar UPDATE
        $stmt = $pdo->prepare("
            UPDATE socios 
            SET alias = ?, fecha_nac = ?, celular = ?, direccion = ?, 
                rol = ?, id_puesto = ?, genero = ?, habilidad = ?, 
                foto_url = ?, datos_completos = 1 
            WHERE id_socio = ?
        ");
        
        $result = $stmt->execute($update_data);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("ERROR SQL: " . implode(', ', $errorInfo));
            throw new Exception('Error en la base de datos');
        }

        $rowCount = $stmt->rowCount();
        error_log("Filas actualizadas: $rowCount");
        
        if ($rowCount === 0) {
            error_log("ADVERTENCIA: Ninguna fila actualizada - socio_id=$socio_id puede no existir");
            throw new Exception('Tu perfil no se encontró en el sistema');
        }

        $_SESSION['perfil_completo'] = true;
        $success = true;
        error_log("=== ÉXITO: Perfil actualizado ===");

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("EXCEPCIÓN: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Completar Perfil - Cancha</title>
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
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
    }

    .form-container {
      width: 95%;
      max-width: 600px;
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
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.85rem;
      color: #071289;
    }

    .col-span-2 {
      grid-column: span 2;
    }

    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: #050d66;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #071289;
      text-decoration: none;
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
      
      .col-span-2 {
        grid-column: span 2 !important;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <a href="javascript:history.back()" class="close-btn" title="Volver atrás">×</a>

<?php if ($success): ?>
    <h2>✅ ¡Perfil completado!</h2>
    <div class="success">
      Tu perfil ha sido actualizado correctamente. Ahora tienes acceso a todas las funcionalidades. Bienvenido a la comunidad Cancha.
    </div>
    
    <?php
    $redirect_url = "dashboard_socio.php?id_club=" . htmlspecialchars($club_slug ?? 'default');
    ?>
    
    <div style="text-align: center; margin-top: 1.5rem;">
      <div class="redirect-message" style="color: #071289; font-style: italic; margin-bottom: 1rem;">
        Redirigiendo automáticamente en 2 segundos...
      </div>
      <a href="<?= $redirect_url ?>" class="btn-submit" style="text-decoration: none; display: inline-block; width: auto; padding: 0.8rem 2rem;">
        Ir al dashboard ahora
      </a>
    </div>
    
    <script>
      setTimeout(() => {
        window.location.href = '<?= $redirect_url ?>';
      }, 2000);
    </script>
    
<?php else: ?>
    <h2>📝 Completa tu perfil</h2>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="form-grid">
        <!-- Fila 1 -->
        <div class="form-group"><label for="alias">Alias *</label></div>
        <div class="form-group"><input type="text" id="alias" name="alias" required></div>
        <div class="form-group"><label for="fecha_nac">Fecha Nac.</label></div>
        <div class="form-group"><input type="date" id="fecha_nac" name="fecha_nac"></div>
        <div class="form-group"><label for="celular">Celular *</label></div>
        <div class="form-group"><input type="tel" id="celular" name="celular" required></div>

        <!-- Fila 2 -->
        <div class="form-group"><label for="genero">Género</label></div>
        <div class="form-group">
          <select id="genero" name="genero">
            <option value="">Seleccionar</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group"><label for="rol">Rol</label></div>
        <div class="form-group">
          <select id="rol" name="rol">
            <option value="">Seleccionar</option>
            <option value="jugador">Jugador</option>
            <option value="entrenador">Entrenador</option>
            <option value="director">Director Técnico</option>
            <option value="administrativo">Administrativo</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 3 -->
        <div class="form-group"><label for="id_puesto">Puesto</label></div>
        <div class="form-group">
          <select id="id_puesto" name="id_puesto">
            <option value="">Cargando puestos...</option>
          </select>
        </div>
        <div class="form-group"><label for="habilidad">Habilidad</label></div>
        <div class="form-group">
          <select id="habilidad" name="habilidad">
            <option value="">Seleccionar</option>
            <option value="Básica">Básica</option>
            <option value="Intermedia">Intermedia</option>
            <option value="Avanzada">Avanzada</option>
          </select>
        </div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 4 -->
        <div class="form-group"><label for="direccion">Dirección *</label></div>
        <div class="form-group col-span-2"><input type="text" id="direccion" name="direccion" required></div>
         <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 5 -->
        <div class="form-group"><label for="foto_url">Foto</label></div>
        <div class="form-group"><input type="file" id="foto_url" name="foto_url" accept="image/*"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
      </div>
      
      <button type="submit" class="btn-submit">Guardar perfil completo</button>
    </form>
    
    <a href="javascript:history.back()" class="back-link">
      ← Volver
    </a>
<?php endif; ?>
  </div>

  <script>
  // Función robusta para cargar puestos
  function loadPuestos() {
      const puestoSelect = document.getElementById('id_puesto');
      if (puestoSelect && puestoSelect.options.length <= 1) {
          fetch('../api/get_puestos.php')
              .then(response => response.json())
              .then(puestos => {
                  puestoSelect.innerHTML = '<option value="">Seleccionar puesto</option>';
                  puestos.forEach(puesto => {
                      const option = document.createElement('option');
                      option.value = puesto.id_puesto;
                      option.textContent = puesto.puesto;
                      puestoSelect.appendChild(option);
                  });
              })
              .catch(error => {
                  console.error('Error al cargar puestos:', error);
                  puestoSelect.innerHTML = '<option value="">Error al cargar puestos</option>';
              });
      }
  }

  // Cargar puestos inmediatamente y cuando el DOM esté listo
  loadPuestos();
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', loadPuestos);
  }

  // Validación de teléfono
  const celularInput = document.getElementById('celular');
  if (celularInput) {
      celularInput.addEventListener('input', function(e) {
          this.value = this.value.replace(/[^0-9+]/g, '');
      });
  }
  </script>
</body>
</html>