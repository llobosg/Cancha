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
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    /* Fondo corporativo */
    body {
      background: 
        linear-gradient(rgba(0, 10, 20, 0.45), rgba(0, 15, 30, 0.55)),
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

    /* Submodal en web */
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

    /* En móvil: pantalla completa */
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
        position: relative;
      }
    }

    /* Botón de cierre */
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

    /* Formulario */
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

    /* Mobile layout */
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
        grid-column: span 2;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <!-- Botón de cierre -->
    <a href="index.php" class="close-btn" title="Volver al inicio">×</a>

    <h2>Registra tu Club ⚽</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

      <div class="form-grid">
        <!-- Fila 1 -->
        <div class="form-group"><label for="nombre">Nombre Club</label></div>
        <div class="form-group"><input type="text" id="nombre" name="nombre" required></div>
        <div class="form-group"><label for="fecha_fundacion">Fecha Fundación</label></div>
        <div class="form-group"><input type="date" id="fecha_fundacion" name="fecha_fundacion"></div>
        <div class="form-group"><label for="pais">País</label></div>
        <div class="form-group"><input type="text" id="pais" name="pais" value="Chile" required></div>

        <!-- Fila 2 -->
        <div class="form-group"><label for="region">Región</label></div>
          <div class="form-group">
            <select id="region" name="region" required>
              <option value="">Seleccionar</option>
              <option value="Arica y Parinacota">Arica y Parinacota</option>
              <option value="Tarapacá">Tarapacá</option>
              <option value="Antofagasta">Antofagasta</option>
              <option value="Atacama">Atacama</option>
              <option value="Coquimbo">Coquimbo</option>
              <option value="Valparaíso">Valparaíso</option>
              <option value="Metropolitana">Metropolitana</option>
              <option value="O'Higgins">O'Higgins</option>
              <option value="Maule">Maule</option>
              <option value="Ñuble">Ñuble</option>
              <option value="Biobío">Biobío</option>
              <option value="Araucanía">Araucanía</option>
              <option value="Los Ríos">Los Ríos</option>
              <option value="Los Lagos">Los Lagos</option>
              <option value="Aysén">Aysén</option>
              <option value="Magallanes">Magallanes</option>
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
        <div class="form-group"><label for="jugadores_por_lado">Jugadores</label></div>
        <div class="form-group"><input type="number" id="jugadores_por_lado" name="jugadores_por_lado" min="1" max="20" value="14" required></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
          
        <!-- Fila 4 -->  
        <div class="form-group"><label for="responsable">Responsable</label></div>
        <div class="form-group"><input type="text" id="responsable" name="responsable" required></div>
        <div class="form-group"><label for="telefono">Teléfono</label></div>
        <div class="form-group"><input type="tel" id="telefono" name="telefono" required></div>
        <div class="form-group"><label for="email_responsable">Correo</label></div>
        <div class="form-group"><input type="email" id="email_responsable" name="email_responsable" required></div>

        <!-- Fila 5 --> 
        <div class="form-group"><label for="logo">Logo del club</label></div>
        <div class="form-group col-span-2"><input type="file" id="logo" name="logo" accept="image/*"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>
        <div class="form-group"></div>

        <!-- Fila 6 -->  
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
    function advertencia(msg) { mostrarNotificacion(msg, 'warning'); } // ← ¡Definida!

    // === DATOS LOCALES DE CHILE ===
    const regionesComunas = {
      "Valparaíso": {
        "Valparaíso": ["Valparaíso", "Viña del Mar", "Quilpué", "Villa Alemana", "Concón", "Limache", "Olmué", "Casablanca", "Juan Fernández"],
        "Isla de Pascua": ["Isla de Pascua"]
      },
      "Metropolitana": {
        "Santiago": ["Santiago", "Providencia", "Las Condes", "Ñuñoa", "La Florida", "Maipú", "Puente Alto", "San Bernardo", "Quilicura", "Pudahuel", "Recoleta", "Independencia", "Renca", "Lo Prado", "Cerro Navia", "Estación Central", "Conchalí", "Huechuraba", "Vitacura", "Lo Barnechea", "Peñalolén", "La Reina", "Macul", "San Joaquín", "La Granja", "San Ramón", "La Pintana", "El Bosque", "San Miguel", "Lo Espejo", "Pedro Aguirre Cerda", "Cerrillos", "Buin", "Calera de Tango", "Paine", "San José de Maipo", "Alhué", "Curacaví", "María Pinto", "Melipilla", "San Pedro", "Isla de Maipo", "El Monte", "Padre Hurtado", "Peñaflor", "Talagante", "Tiltil"],
        "Cordillera": ["Puente Alto", "San José de Maipo", "Pirque"]
      },
      "Biobío": {
        "Concepción": ["Concepción", "Talcahuano", "Hualpén", "San Pedro de la Paz", "Chiguayante", "Coronel", "Lota", "Santa Juana", "Tomé", "Penco", "Florida", "Hualqui", "Cabrero", "Yumbel", "San Rosendo", "Laja", "Nacimiento", "Los Ángeles", "Mulchén", "Negrete", "Quilaco", "Quilleco", "San Pablo", "Tucapel", "Antuco", "Coihueco", "Ñiquén", "San Fabián", "San Nicolás"]
      }
      // Agrega más regiones si lo deseas
    };

    // === CARGAR CIUDADES AL CAMBIAR REGIÓN ===
    document.getElementById('region').addEventListener('change', function() {
      const ciudadSelect = document.getElementById('ciudad');
      const comunaSelect = document.getElementById('comuna');
      ciudadSelect.innerHTML = '<option value="">Seleccione ciudad</option>';
      comunaSelect.innerHTML = '<option value="">Seleccione ciudad</option>';
      ciudadSelect.disabled = true;
      comunaSelect.disabled = true;

      if (!this.value) return;

      const ciudades = Object.keys(regionesComunas[this.value] || {});
      if (ciudades.length > 0) {
        ciudades.forEach(ciudad => {
          const opt = document.createElement('option');
          opt.value = ciudad;
          opt.textContent = ciudad;
          ciudadSelect.appendChild(opt);
        });
        ciudadSelect.disabled = false;
      } else {
        ciudadSelect.innerHTML = '<option value="">Sin ciudades</option>';
      }
    });

    // === CARGAR COMUNAS AL CAMBIAR CIUDAD ===
    document.getElementById('ciudad').addEventListener('change', function() {
      const comunaSelect = document.getElementById('comuna');
      comunaSelect.innerHTML = '<option value="">Cargando...</option>';
      comunaSelect.disabled = true;

      const region = document.getElementById('region').value;
      if (!region || !this.value) return;

      const comunas = regionesComunas[region]?.[this.value] || [];
      if (comunas.length > 0) {
        comunaSelect.innerHTML = '<option value="">Seleccionar</option>';
        comunas.forEach(comuna => {
          const opt = document.createElement('option');
          opt.value = comuna;
          opt.textContent = comuna;
          comunaSelect.appendChild(opt);
        });
        comunaSelect.disabled = false;
      } else {
        comunaSelect.innerHTML = '<option value="">Sin comunas</option>';
      }
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
    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW registrado:', reg.scope))
          .catch(err => console.log('Error SW:', err));
      });
    }
  </script>
</body>
</html>