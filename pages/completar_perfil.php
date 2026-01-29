<!-- pages/completar_perfil.php -->
<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener datos actuales del socio (simulado - en producción usarías sesión)
$socio_id = $_SESSION['id_socio'] ?? 1; // Ajusta según tu sistema de autenticación

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos obligatorios
        $required = ['alias', 'celular', 'direccion'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Campos obligatorios incompletos');
            }
        }

        // Actualizar perfil completo
        $stmt = $pdo->prepare("
            UPDATE socios 
            SET alias = ?, fecha_nac = ?, celular = ?, direccion = ?, 
                rol = ?, id_puesto = ?, genero = ?, habilidad = ?, datos_completos = 1 
            WHERE id_socio = ?
        ");
        $stmt->execute([
            $_POST['alias'],
            $_POST['fecha_nac'] ?: null,
            $_POST['celular'],
            $_POST['direccion'],
            $_POST['rol'] ?: null,
            $_POST['id_puesto'] ?: null,
            $_POST['genero'] ?: null,
            $_POST['habilidad'] ?: 'Básica',
            $socio_id
        ]);

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

    /* Responsive móvil */
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.7rem;
      }
      
      .col-span-2 {
        grid-column: span 2 !important;
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
      <a href="dashboard_socio.php" class="btn-submit" style="text-decoration: none; text-align: center; display: block;">
        Ir al dashboard
      </a>
    <?php else: ?>
      <h2>Completa tu perfil</h2>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-grid">
          <!-- Fila 1 -->
          <div class="form-group"><label for="alias">Alias *</label></div>
          <div class="form-group"><input type="text" id="alias" name="alias" required></div>
          <div class="form-group"><label for="fecha_nac">Fecha Nac.</label></div>
          <div class="form-group"><input type="date" id="fecha_nac" name="fecha_nac"></div>
          <div class="form-group"><label for="celular">Celular *</label></div>
          <div class="form-group"><input type="tel" id="celular" name="celular" required></div>

          <!-- Fila 2 -->
          <div class="form-group"><label for="direccion">Dirección *</label></div>
          <div class="form-group col-span-2"><input type="text" id="direccion" name="direccion" required></div>
          <div class="form-group"><label for="rol">Rol</label></div>
          <div class="form-group">
            <select id="rol" name="rol" required>
              <option value="">Seleccionar</option>
              <option value="Jugador">Jugador</option>
              <option value="Capitán">Galleta</option>
              <option value="Entrenador">Amigo del club</option>
              <option value="Tesorero">Tesorero</option>
              <option value="Director">Director</option>
              <option value="Delegado">Delegado</option>
              <option value="Profe">Profe</option>
              <option value="Kine">Kine</option>
              <option value="Preparador Físico">Preparador Físico</option>
              <option value="Utilero">Utilero</option>
            </select>
          </div>

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
          <div class="form-group"><label for="genero">Género</label></div>
          <div class="form-group">
            <select id="genero" name="genero">
              <option value="">Seleccionar</option>
              <option value="masculino">Masculino</option>
              <option value="femenino">Femenino</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        
        <button type="submit" class="btn-submit">Guardar perfil completo</button>
      </form>
      
      <a href="javascript:history.back()" class="back-link">
        ← Volver
      </a>
    <?php endif; ?>
  </div>

  <script>
    // Cargar puestos desde API
    document.addEventListener('DOMContentLoaded', () => {
      const puestoSelect = document.getElementById('id_puesto');
      
      fetch('../api/get_puestos.php')
        .then(response => response.json())
        .then(puestos => {
          // Limpiar opción de carga
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

      // Validación de teléfono
      document.getElementById('celular')?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9+]/g, '');
      });
    });
  </script>
</body>
</html>