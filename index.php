<!-- pages/index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cancha - Gestión para clubes deportivos</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    /* Fondo nuevo */
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.50), rgba(0, 30, 15, 0.60)),
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

    .hero {
      text-align: center;
      max-width: 900px;
      padding: 2rem;
    }

    h1 {
      font-size: 3.2rem;
      margin-bottom: 1.5rem;
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
      color: white;
    }

    .subtitle {
      font-size: 1.3rem;
      margin-bottom: 2.5rem;
      opacity: 0.95;
      text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }

    /* Grid de fichas - 3 en fila (web) / 1 en móvil */
    .cards-container {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.8rem;
      margin-bottom: 2.5rem;
      width: 100%;
    }

    .card {
      background: rgba(255, 255, 255, 0.1); /* ← 90% transparencia */
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 16px;
      padding: 1.8rem;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }

    .card:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }

    .card::before {
      content: "⚽";
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 2rem;
      opacity: 0.3;
    }

    .card h3 {
      font-size: 1.3rem;
      margin-bottom: 1rem;
      color: white;
    }

    .card p {
      font-size: 0.95rem;
      opacity: 0.9;
      line-height: 1.5;
    }

    /* Recordar club - sin borde gris y más abajo */
    .remember-section {
      background: transparent; /* ← sin fondo gris */
      padding: 0.5rem 0;
      margin-top: 2.5rem; /* ← más abajo */
      border: none; /* ← sin borde */
    }

    .remember-section label {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      cursor: pointer;
      font-size: 0.95rem;
      color: white;
      text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .remember-section input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: #00cc66;
    }

    .btn-direct {
      background: #00cc66;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      margin-top: 1.2rem;
      transition: all 0.2s;
      display: none;
    }

    .btn-direct:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }

    /* Modal QR */
    .qr-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.8);
      z-index: 40000;
      justify-content: center;
      align-items: center;
    }

    .qr-content {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      text-align: center;
      max-width: 400px;
      width: 90%;
    }

    .qr-content h3 {
      color: #003366;
      margin-bottom: 1.5rem;
    }

    .qr-code {
      margin: 1rem auto;
      width: 200px;
      height: 200px;
      background: #f0f0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
    }

    .qr-url {
      word-break: break-all;
      font-size: 0.9rem;
      color: #666;
      margin: 1rem 0;
    }

    .close-qr {
      background: #6c757d;
      color: white;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      cursor: pointer;
      margin-top: 1rem;
    }

    /* Responsive: en móviles, una columna */
    @media (max-width: 768px) {
      .cards-container {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      
      h1 { font-size: 2.5rem; }
      .subtitle { font-size: 1.1rem; }
    }
  </style>
</head>
<body>
  <div class="hero">
    <h1>🏟️ Cancha</h1>
    <p class="subtitle">Gestiona tu club. Juega mejor. Sin WhatsApp.</p>

    <div class="cards-container">
      <!-- Ficha 1: Registrar club -->
      <div class="card" onclick="window.location.href='/../pages/registro_club.php'">
        <h3>Registra tu club</h3>
        <p>Crea tu espacio único para gestionar socios, eventos y finanzas de tu club deportivo.</p>
      </div>

      <!-- Ficha 2: Inscribirse -->
      <div class="card" onclick="window.location.href='/../pages/buscar_club.php'">
        <h3>Inscripción socio</h3>
        <p>Únete a un club existente, confirma tu inscripción y comienza a participar en eventos.</p>
      </div>

      <!-- Ficha 3: Entrar a tu cancha -->
      <div class="card" onclick="abrirEntrarACancha()">
        <h3>Entra a tu cancha</h3>
        <p>Accede directamente al dashboard de tu club si ya estás registrado como administrador o socio.</p>
      </div>
    </div>

    <div class="remember-section">
      <label>
        <input type="checkbox" id="rememberClub">
        Recordar mi club para entrar directamente la próxima vez
      </label>
      <button id="btnDirect" class="btn-direct">Entrar a mi cancha</button>
    </div>
  </div>

  <!-- Modal QR -->
  <div id="qrModal" class="qr-modal">
    <div class="qr-content">
      <h3>📲 Comparte tu club</h3>
      <p>Escanea este código para que otros se inscriban fácilmente</p>
      <div class="qr-code" id="qrCode"></div>
      <div class="qr-url" id="qrUrl"></div>
      <button class="close-qr" onclick="cerrarQR()">Cerrar</button>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    // Verificar si hay club guardado
    document.addEventListener('DOMContentLoaded', () => {
      const savedClub = localStorage.getItem('cancha_club');
      const btnDirect = document.getElementById('btnDirect');
      const rememberCheck = document.getElementById('rememberClub');
      
      if (savedClub) {
        btnDirect.style.display = 'inline-block';
        btnDirect.onclick = () => {
          window.location.href = `dashboard.php?id_club=${savedClub}`;
        };
      }

      function abrirEntrarACancha() {
        const clubId = prompt("Ingresa el ID o slug de tu club:");
        if (clubId) {
          const url = `https://cancha-web.up.railway.app/pages/registro_socio.php?club=${clubId}`;
          mostrarQR(url);
          
          const rememberCheck = document.getElementById('rememberClub');
          const remember = rememberCheck.checked;
          if (remember) {
            localStorage.setItem('cancha_club', clubId);
          }
        }
      }

      rememberCheck.addEventListener('change', () => {
        if (rememberCheck.checked && !localStorage.getItem('cancha_club')) {
          alert('Primero entra a tu cancha para guardar la preferencia');
          rememberCheck.checked = false;
        }
      });
    });

    function mostrarQR(url) {
      const qrModal = document.getElementById('qrModal');
      const qrCode = document.getElementById('qrCode');
      const qrUrl = document.getElementById('qrUrl');
      
      qrCode.innerHTML = '';
      new QRCode(qrCode, {
        text: url,
        width: 180,
        height: 180,
        colorDark: "#003366",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });
      
      qrUrl.textContent = url;
      qrModal.style.display = 'flex';
    }

    function cerrarQR() {
      document.getElementById('qrModal').style.display = 'none';
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') cerrarQR();
    });

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