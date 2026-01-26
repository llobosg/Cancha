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
      animation: fadeInDown 0.8s ease;
    }

    .subtitle {
      font-size: 1.3rem;
      margin-bottom: 2.5rem;
      opacity: 0.95;
      text-shadow: 0 1px 2px rgba(0,0,0,0.5);
      animation: fadeIn 1s ease;
    }

    /* Grid de fichas - 3 en fila */
    .cards-container {
      display: grid;
      grid-template-columns: repeat(3, minmax(250px, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
      width: 100%;
      justify-items: center;
    }
    …  overflow: hidden;
      opacity: 0;
      transform: translateY(20px);
      animation: slideUp 0.6s forwards;
    }

    /* Animación escalonada */
    .card:nth-child(1) { animation-delay: 0.2s; }
    .card:nth-child(2) { animation-delay: 0.4s; }
    .card:nth-child(3) { animation-delay: 0.6s; }

    .card:hover {
      background: rgba(255, 255, 255, 0.25);
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

    /* Recordar club */
    .remember-section {
      background: rgba(0, 30, 60, 0.4);
      padding: 1.5rem;
      border-radius: 12px;
      margin-top: 1.5rem;
    }

    .remember-section label {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      cursor: pointer;
      font-size: 0.95rem;
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

    /* Animaciones */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideUp {
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
      h1 { font-size: 2.5rem; }
      .subtitle { font-size: 1.1rem; }
      .cards-container { gap: 1.2rem; }
    }
  </style>
</head>
<body>
  <div class="hero">
    <h1>🏟️ <span style="color: #58c20cff;">Cancha</span></h1>
    <p class="subtitle">Gestiona tu club a un click.</p>

    <div class="cards-container">
      <div class="card" onclick="window.location.href='registro_club.php'">
        <h3>Registra tu club</h3>
        <p>Crea tu espacio único para gestionar socios, eventos y finanzas de tu club deportivo.</p>
      </div>

      <div class="card" onclick="window.location.href='buscar_club.php'">
        <h3>Inscripción socio</h3>
        <p>Únete a un club existente, confirma tu inscripción y comienza a participar de partidos y 3er tiempo.</p>
      </div>

      <div class="card" id="enterClubCard">
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

      // Entrar a club y mostrar QR
      document.getElementById('enterClubCard').onclick = () => {
        const clubId = prompt("Ingresa el ID o slug de tu club:");
        if (clubId) {
          const url = `https://cancha-web.up.railway.app/pages/registro_socio.php?club=${clubId}`;
          mostrarQR(url);
          
          const remember = rememberCheck.checked;
          if (remember) {
            localStorage.setItem('cancha_club', clubId);
          }
        }
      };

      rememberCheck.addEventListener('change', () => {
        if (rememberCheck.checked && !localStorage.getItem('cancha_club')) {
          alert('Primero entra a tu cancha para guardar la preferencia');
          rememberCheck.checked = false;
        }
      });
    });

    // Mostrar QR
    function mostrarQR(url) {
      const qrModal = document.getElementById('qrModal');
      const qrCode = document.getElementById('qrCode');
      const qrUrl = document.getElementById('qrUrl');
      
      // Limpiar QR anterior
      qrCode.innerHTML = '';
      
      // Generar nuevo QR
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

    // Cerrar QR con ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') cerrarQR();
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