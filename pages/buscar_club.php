<!-- pages/buscar_club.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buscar Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.45), rgba(0, 30, 15, 0.55)),
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

    .search-container {
      width: 95%;
      max-width: 800px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
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

    .search-box {
      margin-bottom: 2rem;
    }

    #buscarInput {
      width: 100%;
      padding: 0.8rem;
      font-size: 1.1rem;
      border: 2px solid #ccc;
      border-radius: 8px;
      box-sizing: border-box;
    }

    #resultados {
      display: grid;
      gap: 1rem;
    }

    .club-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .club-card:hover {
      background: #f5f7fa;
      border-color: #003366;
    }

    .club-logo {
      width: 60px;
      height: 60px;
      border-radius: 8px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #666;
      font-size: 1.5rem;
    }

    .club-info h3 {
      margin: 0 0 0.3rem 0;
      color: #333;
    }

    .club-info p {
      margin: 0;
      color: #666;
      font-size: 0.9rem;
    }

    .no-results {
      text-align: center;
      color: #666;
      padding: 2rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .search-container {
        width: 100%;
        max-width: none;
        height: 100vh;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        padding: 1.5rem;
      }
      
      body {
        background: white !important;
        color: #333 !important;
      }
    }
  </style>
</head>
<body>
  <div class="search-container">
    <!-- Botón de cierre -->
    <a href="index.php" class="close-btn" title="Volver al inicio">×</a>

    <h2>🔍 Buscar Club</h2>
    
    <div class="search-box">
      <input type="text" id="buscarInput" placeholder="Nombre del club, ciudad o comuna..." autocomplete="off">
    </div>
    
    <div id="resultados">
      <div class="no-results">Ingresa un nombre, ciudad o comuna para buscar clubes.</div>
    </div>
  </div>

  <!-- Toast de notificaciones -->
  <div id="toast" class="toast" style="display:none;">
      <i class="fas fa-info-circle"></i> 
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

    function error(msg) { mostrarNotificacion(msg, 'error'); }

    // === BÚSQUEDA DE CLUBES ===
    let searchTimeout;
    document.getElementById('buscarInput').addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const term = this.value.trim();
      
      if (term.length < 2) {
        document.getElementById('resultados').innerHTML = '<div class="no-results">Ingresa al menos 2 caracteres para buscar.</div>';
        return;
      }

      searchTimeout = setTimeout(() => {
        fetch(`../api/buscar_club.php?q=${encodeURIComponent(term)}`)
          .then(response => response.text())
          .then(text => {
            try {
              const data = JSON.parse(text);
              const cont = document.getElementById('resultados');
              
              if (data.length === 0) {
                cont.innerHTML = '<div class="no-results">No se encontraron clubes.</div>';
                return;
              }
              
              cont.innerHTML = data.map(club => `
                <div class="club-card" onclick="seleccionarClub('${club.slug}')">
                  <div class="club-logo">
                    ${club.logo ? `<img src="../uploads/logos/${club.logo}" alt="Logo" style="width:100%;height:100%;border-radius:8px;">` : '⚽'}
                  </div>
                  <div class="club-info">
                    <h3>${club.nombre}</h3>
                    <p>${club.deporte} • ${club.ciudad}, ${club.comuna}</p>
                  </div>
                </div>
              `).join('');
            } catch (e) {
              console.error('Error parseando JSON:', e);
              error('Error al procesar la respuesta del servidor');
            }
          })
          .catch(err => {
            console.error('Error de red:', err);
            error('Error de conexión');
          });
      }, 300);
    });

    function seleccionarClub(slug) {
      window.location.href = `registro_socio.php?club=${slug}`;
    }
  </script>
</body>
</html>