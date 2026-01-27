<!-- pages/index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cancha - Gestión para clubes deportivos</title>
  <link rel="stylesheet" href="../styles.css">
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

    /* Botón acceso directo */
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

    /* Grid de fichas - 2x2 en web / 1 columna en móvil */
    .cards-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.8rem;
      margin-bottom: 2.5rem;
      width: 100%;
    }

    .card {
      background: rgba(255, 255, 255, 0.1);
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

    /* Google Login en ficha */
    .google-login-section {
      margin-top: 1.2rem;
      padding-top: 1.2rem;
      border-top: 1px solid rgba(255,255,255,0.2);
    }

    /* Responsive */
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

    <!-- Botón acceso directo (aparece solo si hay club guardado) -->
    <div id="accesoDirecto" style="text-align: center; margin-bottom: 2.5rem; display: none;">
      <button id="btnDirect" class="btn-direct">Entrar a mi cancha</button>
    </div>

    <div class="cards-container">
      <!-- Ficha 1: Registrar club -->
      <div class="card" onclick="window.location.href='../pages/registro_club.php'">
        <h3>Registra tu club</h3>
        <p>Crea tu espacio único para gestionar socios, eventos y finanzas de tu club deportivo.</p>
      </div>

      <!-- Ficha 2: Inscribirse -->
      <div class="card" onclick="window.location.href='../pages/buscar_club.php'">
        <h3>Inscríbete a un club</h3>
        <p>Únete a un club existente, confirma tu inscripción y comienza a participar en eventos.</p>
      </div>

      <!-- Ficha 3: Primera vez -->
      <div class="card">
        <h3>Primera vez en Cancha</h3>
        <p>¿Ya te inscribiste pero es tu primera vez aquí? Autentica tu identidad con Google para registrar este dispositivo y acceder a tu cancha.</p>
        
        <div class="google-login-section">
          <div id="g_id_onload"
               data-client_id="887808441549-lpgd9gs8t1dqe9r00a5uj7omg8iob8mt.apps.googleusercontent.com"
               data-callback="handleCredentialResponse"
               data-auto_select="false"
               data-cancel_on_tap_outside="true">
          </div>
          <div class="g_id_signin" 
               data-type="standard"
               data-size="medium"
               data-theme="outline"
               data-text="continue_with"
               data-shape="rectangular"
               data-logo_alignment="left"
               style="display: inline-block;">
          </div>
        </div>
      </div>

      <!-- Ficha 4: Acceso rápido -->
      <div class="card">
        <h3>Acceso rápido</h3>
        <p>¿Ya has estado en Cancha desde este dispositivo? Usa el botón verde de arriba para entrar directamente a tu dashboard.</p>
      </div>
    </div>
  </div>

  <!-- Modal QR -->
  <div id="qrModal" class="qr-modal" style="display:none;">
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

    // === GOOGLE LOGIN ===
    function handleCredentialResponse(response) {
      fetch('../api/login_google.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: response.credential})
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
          localStorage.setItem('cancha_device', deviceId);
          localStorage.setItem('cancha_session', 'active');
          localStorage.setItem('cancha_club', data.club_slug);
          
          window.location.href = `pages/dashboard.php?id_club=${data.club_slug}`;
        } else {
          alert('Error: ' + (data.message || 'No se pudo iniciar sesión'));
          if (data.redirect) {
            window.location.href = data.redirect;
          }
        }
      })
      .catch(err => {
        console.error('Login error:', err);
        alert('Error de conexión');
      });
    }

    // === ACCESO MANUAL (para QR) ===
    function accesoManual() {
      const clubId = prompt("Ingresa el ID o slug de tu club:");
      if (clubId) {
        const url = `https://cancha-web.up.railway.app/pages/registro_socio.php?club=${clubId}`;
        mostrarQR(url);
        
        const rememberCheck = document.getElementById('rememberClub');
        if (rememberCheck?.checked) {
          localStorage.setItem('cancha_club', clubId);
        }
      }
    }

    // === QR ===
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

    // === INICIALIZAR BOTÓN DIRECTO ===
    document.addEventListener('DOMContentLoaded', () => {
      const savedClub = localStorage.getItem('cancha_club');
      const btnDirect = document.getElementById('btnDirect');
      const accesoDirecto = document.getElementById('accesoDirecto');
      
      if (savedClub) {
        accesoDirecto.style.display = 'block';
        btnDirect.onclick = () => {
          window.location.href = `pages/dashboard.php?id_club=${savedClub}`;
        };
      }
    });

    // Cerrar QR con ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') cerrarQR();
    });
  </script>

  <!-- Toast de notificaciones -->
  <div id="toast" class="toast" style="display:none;">
    <span>ℹ️</span>
    <span id="toast-message">Mensaje</span>
  </div>

  <!-- Google Identity Services -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>