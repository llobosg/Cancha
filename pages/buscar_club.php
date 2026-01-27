<!-- pages/buscar_club.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buscar Club - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    /* Fondo corporativo de Cancha */
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
      align-items: flex-start;
      padding-top: 2rem;
    }

    /* Submodal flotante */
    .submodal {
      position: relative;
      width: 95%;
      max-width: 700px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      margin: 0 auto;
    }

    /* Logo ⚽ en esquinas del submodal */
    .submodal::before,
    .submodal::after {
      content: "⚽";
      position: absolute;
      font-size: 1.8rem;
      color: #003366;
      opacity: 0.65;
      z-index: 2;
    }
    .submodal::before { top: 20px; left: 20px; }
    .submodal::after { bottom: 20px; right: 20px; }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.6rem;
    }

    .search-box {
      display: flex;
      gap: 0.8rem;
      margin-bottom: 2rem;
    }

    .search-box input {
      flex: 1;
      padding: 0.7rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 0.95rem;
      color: #071289;
    }

    .search-box button {
      background: #071289;
      color: white;
      border: none;
      padding: 0.7rem 1.2rem;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
    }

    .results {
      display: grid;
      gap: 1.2rem;
    }

    .club-card {
      background: #f8f9ff;
      padding: 1.2rem;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      display: flex;
      align-items: center;
      gap: 1.2rem;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .club-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }

    .club-logo {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: #666;
      font-size: 1.2rem;
    }

    .club-info h3 {
      margin: 0 0 0.3rem 0;
      color: #333;
      font-size: 1.1rem;
    }

    .club-info p {
      margin: 0;
      color: #666;
      font-size: 0.9rem;
    }

    .no-results {
      text-align: center;
      color: #888;
      padding: 2rem;
      font-size: 0.95rem;
    }

    /* Toast de notificaciones */
    .toast {
      position: fixed;
      bottom: 20px;
      right: 20px;
      max-width: 350px;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      color: white;
      font-size: 0.95rem;
      font-weight: bold;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
      z-index: 32000;
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.4s ease;
      display: flex;
      align-items: center;
      gap: 0.7rem;
    }

    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast.success { background: linear-gradient(135deg, #009966, #006644); }
    .toast.error { background: linear-gradient(135deg, #cc0000, #990000); }
    .toast.warning { background: linear-gradient(135deg, #ff9900, #cc6600); }
    .toast.info { background: linear-gradient(135deg, #0066cc, #004080); }

    @media (max-width: 600px) {
      .search-box {
        flex-direction: column;
      }
      .club-card {
        flex-direction: column;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="submodal">
    <!-- Botón de cierre -->
    <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>
    
    <h2>🔍 Buscar club</h2>
    
    <div class="search-box">
      <input type="text" id="buscarInput" placeholder="Nombre del club, ciudad o comuna...">
      <button id="buscarBtn">Buscar</button>
    </div>

    <div class="results" id="resultados"></div>
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
    function buscarClubes() {
      const term = document.getElementById('buscarInput').value.trim();
      if (!term) {
        error('Ingresa un nombre, ciudad o comuna');
        return;
      }

      fetch(`../api/buscar_club.php?q=${encodeURIComponent(term)}`)
        .then(response => {
          console.log('Status:', response.status);
          return response.text(); // ← Obtener texto crudo
        })
        .then(text => {
          console.log('Respuesta API:', text); // ← ¡Esto es clave!
          
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
    }

    function seleccionarClub(slug) {
      window.location.href = `registro_socio.php?club=${slug}`;
    }

    // Eventos
    document.getElementById('buscarBtn').addEventListener('click', buscarClubes);
    document.getElementById('buscarInput').addEventListener('keypress', e => {
      if (e.key === 'Enter') buscarClubes();
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