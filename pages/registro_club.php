<!-- pages/registro_club.php -->
<?php
require_once __DIR__ . '/../includes/config.php';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registrar Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    /* Fondo de estadio vibrante - menos oscuro */
    body {
      background: 
        linear-gradient(rgba(0, 10, 20, 0.60), rgba(0, 15, 30, 0.70)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-container {
      width: 90%;
      max-width: 1100px;
      margin: 0 auto;
      background: white;
      padding: 2.2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
    }

    /* Logos ⚽ en esquinas */
    .form-container::before,
    .form-container::after {
      content: "⚽";
      position: absolute;
      font-size: 2.2rem;
      color: #003366;
      opacity: 0.65;
      z-index: 2;
    }
    .form-container::before { top: 22px; left: 22px; }
    .form-container::after { bottom: 22px; right: 22px; }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.8rem;
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

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #071289;
      box-shadow: 0 0 0 2px rgba(7, 18, 137, 0.15);
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

    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.7rem;
      }
      .form-group label {
        text-align: left;
        padding-right: 0;
      }
      .submit-section {
        grid-column: 1 / -1;
      }
      .col-span-2 {
        grid-column: span 2;
      }
      .form-container {
        padding: 1.8rem;
        margin: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Registra tu Club</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="registroForm" enctype="multipart/form-data">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

      <div class="form-grid">
        <!-- Fila 1 -->
        <div class="form-group"><label for="nombre">Nombre Club</label></div>
        <div class="form-group col-span-2"><input type="text" id="nombre" name="nombre" required></div>
        <div class="form-group"><label for="fecha_fundacion">Fecha Fundación</label></div>
        <div class="form-group"><input type="date" id="fecha_fundacion" name="fecha_fundacion"></div>
        <div class="form-group"><label for="pais">País</label></div>
        <div class="form-group"><input type="text" id="pais" name="pais" value="Chile" required></div>

        <!-- Fila 2 -->
        <div class="form-group"><label for="region">Región</label></div>
        <div class="form-group">
          <select id="region" name="region" required>
            <option value="">Seleccionar</option>
          </select>
        </div>
        <div class="form-group"><label for="ciudad">Ciudad</label></div>
        <div class="form-group">
          <select id="ciudad" name="ciudad" required disabled>
            <option value="">Seleccione región</option>
          </select>
        </div>
        <div class="form-group"><label for="comuna">Comuna</label></div>
        <div class="form-group">
          <select id="comuna" name="comuna" required disabled>
            <option value="">Seleccione ciudad</option>
          </select>
        </div>

        <!-- Fila 3 -->
        <div class="form-group"><label for="deporte">Deporte</label></div>
        <div class="form-group">
          <select id="deporte" name="deporte" required>
            <option value="">Seleccionar</option>
            <option value="futbol">Fútbol</option>
            <option value="futbolito">Futbolito</option>
            <option value="futsal">Futsal</option>
            <option value="futbol11">Fútbol 11</option>
            <option value="tenis">Tenis</option>
            <option value="padel">Pádel</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="form-group"><label for="jugadores_por_lado">Jugadores por lado</label></div>
        <div class="form-group"><input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="5" required></div>
        <div class="form-group"><label for="logo">Logo del club</label></div>
        <div class="form-group"><input type="file" id="logo" name="logo" accept="image/*"></div>

        <!-- Fila 4 -->
        <div class="form-group"><label for="responsable">Responsable</label></div>
        <div class="form-group"><input type="text" id="responsable" name="responsable" required></div>
        <div class="form-group"><label for="telefono">Teléfono</label></div>
        <div class="form-group"><input type="tel" id="telefono" name="telefono" required></div>
        <div class="form-group"><label for="email_responsable">Correo</label></div>
        <div class="form-group"><input type="email" id="email_responsable" name="email_responsable" required></div>

        <!-- Fila 6: Logo del club -->
        <div class="form-group"></div>
        <div class="form-group"></div> 
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <!-- Botón -->
        <div class="submit-section">
          <button type="submit" class="btn-submit">Enviar código de verificación</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Toast de notificaciones -->
  <div id="toast" class="toast" style="display:none;">
    <span>ℹ️</span>
    <span id="toast-message">Mensaje</span>
  </div>

  <script>
    // === FUNCIONES DE NOTIFICACIÓN ===
    function mostrarNotificacion(mensaje, tipo = 'info') {
      const tipoMap = {
        'exito': 'success',
        'error': 'error',
        'advertencia': 'warning',
        'info': 'info'
      };
      const claseTipo = tipoMap[tipo] || 'info';

      const toast = document.getElementById('toast');
      const msg = document.getElementById('toast-message');
      if (!toast || !msg) return;

      msg.textContent = mensaje;
      toast.className = 'toast ' + claseTipo;
      toast.style.display = 'flex';
      void toast.offsetWidth;
      toast.classList.add('show');

      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.style.display = 'none', 400);
      }, 5000);
    }

    function exito(msg) { mostrarNotificacion(msg, 'exito'); }
    function error(msg) { mostrarNotificacion(msg, 'error'); }

    // === CARGAR REGIONES AL INICIAR ===
    document.addEventListener('DOMContentLoaded', () => {
      fetch('https://apis.gob.cl/regiones')
        .then(r => r.json())
        .then(regiones => {
          const regionSelect = document.getElementById('region');
          regiones.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.nombre;
            opt.textContent = r.nombre;
            regionSelect.appendChild(opt);
          });
        })
        .catch(() => {
          advertencia('No se pudieron cargar las regiones. Usa valores manuales.');
        });
    });

    // === AL CAMBIAR REGIÓN → CIUDADES ===
    document.getElementById('region').addEventListener('change', function() {
      const ciudadSelect = document.getElementById('ciudad');
      const comunaSelect = document.getElementById('comuna');
      ciudadSelect.innerHTML = '<option value="">Cargando...</option>';
      comunaSelect.innerHTML = '<option value="">Seleccione ciudad</option>';
      ciudadSelect.disabled = true;
      comunaSelect.disabled = true;

      if (!this.value) return;

      fetch(`https://apis.gob.cl/regiones/${encodeURIComponent(this.value)}/provincias`)
        .then(r => r.json())
        .then(provincias => {
          ciudadSelect.innerHTML = '<option value="">Seleccionar</option>';
          provincias.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.nombre;
            opt.textContent = p.nombre;
            ciudadSelect.appendChild(opt);
          });
          ciudadSelect.disabled = false;
        })
        .catch(() => {
          error('Error al cargar ciudades');
          ciudadSelect.innerHTML = '<option value="">Error de conexión</option>';
        });
    });

    // === AL CAMBIAR CIUDAD → COMUNAS ===
    document.getElementById('ciudad').addEventListener('change', function() {
      const comunaSelect = document.getElementById('comuna');
      comunaSelect.innerHTML = '<option value="">Cargando...</option>';
      comunaSelect.disabled = true;

      if (!this.value) return;

      const region = document.getElementById('region').value;
      fetch(`https://apis.gob.cl/regiones/${encodeURIComponent(region)}/provincias/${encodeURIComponent(this.value)}/comunas`)
        .then(r => r.json())
        .then(comunas => {
          comunaSelect.innerHTML = '<option value="">Seleccionar</option>';
          comunas.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.nombre;
            opt.textContent = c.nombre;
            comunaSelect.appendChild(opt);
          });
          comunaSelect.disabled = false;
        })
        .catch(() => {
          error('Error al cargar comunas');
          comunaSelect.innerHTML = '<option value="">Error de conexión</option>';
        });
    });

    // === MANEJO DEL FORMULARIO ===
    document.getElementById('registroForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      const btn = e.submitter;
      const originalText = btn.innerHTML;
      
      btn.innerHTML = 'Enviando...';
      btn.disabled = true;

      try {
        const response = await fetch('../api/enviar_codigo_club.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          exito('Código enviado a tu correo');
          setTimeout(() => window.location.href = data.redirect, 1500);
        } else {
          error(data.message || 'Error al enviar código');
        }
      } catch (err) {
        console.error('Error:', err);
        error('Error de conexión. Revisa la consola.');
      } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>