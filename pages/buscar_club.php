<!-- pages/buscar_club.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buscar Club - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    body {
      background: #f5f7fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 2rem;
    }
    .container {
      max-width: 800px;
      margin: 2rem auto;
    }
    h2 {
      text-align: center;
      color: #3a4f63;
      margin-bottom: 1.5rem;
    }
    .search-box {
      display: flex;
      gap: 0.8rem;
      margin-bottom: 2rem;
    }
    .search-box input {
      flex: 1;
      padding: 0.8rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }
    .search-box button {
      background: #009966;
      color: white;
      border: none;
      padding: 0.8rem 1.2rem;
      border-radius: 6px;
      cursor: pointer;
    }
    .results {
      display: grid;
      gap: 1.2rem;
    }
    .club-card {
      background: white;
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
    }
    .club-info h3 {
      margin: 0 0 0.3rem 0;
      color: #333;
    }
    .club-info p {
      margin: 0;
      color: #666;
      font-size: 0.95rem;
    }
    .no-results {
      text-align: center;
      color: #888;
      padding: 2rem;
    }
    /* Toast */
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
    .toast.error { background: linear-gradient(135deg, #cc0000, #990000); }
  </style>
</head>
<body>
  <div class="container">
    <h2>🔍 Buscar club</h2>
    <div class="search-box">
      <input type="text" id="buscarInput" placeholder="Nombre del club, ciudad o comuna...">
      <button id="buscarBtn">Buscar</button>
    </div>
    <div class="results" id="resultados"></div>
  </div>

  <!-- Toast -->
  <div id="toast" class="toast error" style="display:none;">
    <span id="toast-message">Error</span>
  </div>

  <script>
    function mostrarToast(mensaje, tipo = 'error') {
      const toast = document.getElementById('toast');
      const msg = document.getElementById('toast-message');
      msg.textContent = mensaje;
      toast.className = `toast ${tipo}`;
      toast.style.display = 'flex';
      void toast.offsetWidth;
      toast.classList.add('show');
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.style.display = 'none', 400);
      }, 4000);
    }

    function buscarClubes() {
      const term = document.getElementById('buscarInput').value.trim();
      if (!term) return;
      fetch(`../api/buscar_club.php?q=${encodeURIComponent(term)}`)
        .then(r => r.json())
        .then(data => {
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
        })
        .catch(() => mostrarToast('Error al buscar clubes'));
    }

    function seleccionarClub(slug) {
      window.location.href = `registro_socio.php?club=${slug}`;
    }

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