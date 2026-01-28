<!-- pages/index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cancha - Gestión para clubes deportivos</title>

  <link rel="stylesheet" href="../styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background:
        linear-gradient(rgba(0,20,10,.55), rgba(0,30,15,.65)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      color: #fff;
      padding-top: 70px;
    }

    /* Barra superior fija */
    .top-bar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 60px;
      background: rgba(0, 51, 102, 0.95);
      backdrop-filter: blur(10px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 1.5rem;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .btn-register {
      background: #FF6B35; /* Naranja vibrante */
      border: none;
      color: white;
      padding: 0.4rem 0.8rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .btn-register:hover {
      background: #E55A2B;
      transform: translateY(-1px);
    }

    .btn-enter {
      background: #00CC66; /* Verde Cancha */
      border: none;
      color: white;
      padding: 0.4rem 0.8rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-enter:hover {
      background: #00AA55;
      transform: translateY(-1px);
    }

    .google-login-container {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    /* Contenido principal - solo título y subtítulo */
    .hero {
      text-align: center;
      max-width: 820px;
      margin: 0 auto;
      padding: 0 2rem;
    }

    .title-cancha {
      font-family: 'Dancing Script', cursive;
      font-size: 3.8rem;
      margin: 2rem 0 1rem 0;
      color: white; /* Blanco explícito */
      text-shadow: 0 3px 6px rgba(0,0,0,.5);
    }

    .subtitle {
      font-size: 1.25rem;
      margin-bottom: 2.5rem;
      opacity: .95;
      color: white; /* Blanco explícito */
    }

    /* Espacio para anuncios multimedia */
    .media-section {
      min-height: 300px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 2rem;
    }

    .media-placeholder {
      background: rgba(255,255,255,0.1);
      border: 1px dashed rgba(255,255,255,0.3);
      border-radius: 16px;
      padding: 2rem;
      text-align: center;
      color: rgba(255,255,255,0.7);
      font-style: italic;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .top-bar {
        padding: 0 1rem;
        height: 55px;
      }
      
      .btn-register,
      .btn-enter {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
      }
      
      .title-cancha { 
        font-size: 2.8rem;
        margin-top: 1.5rem;
      }
      
      .subtitle {
        font-size: 1.1rem;
      }
    }
    
    /* Ícono banderín */
    .flag-icon::before {
      content: "🚩";
    }
  </style>
</head>

<body>

<!-- Barra superior -->
<div class="top-bar">
  <button class="btn-register" onclick="window.location.href='registro_club.php'">
    <span class="flag-icon"></span>
    <span class="register-text">Registrar un club</span>
  </button>
  
  <div class="google-login-container">
    <!-- Botón Entrar a mi club (aparece si hay sesión) -->
    <button id="btnEnterClub" class="btn-enter" style="display:none;">
      👤 Entrar a mi club
    </button>
    
    <!-- Google Login (aparece si NO hay sesión) -->
    <div id="googleLoginContainer">
      <div id="g_id_onload"
           data-client_id="887808441549-lpgd9gs8t1dqe9r00a5uj7omg8iob8mt.apps.googleusercontent.com"
           data-callback="handleCredentialResponse"
           data-auto_select="false">
      </div>
      <div class="g_id_signin"
           data-type="standard"
           data-size="medium"
           data-theme="outline"
           data-text="continue_with"
           data-shape="rectangular"
           data-logo_alignment="left">
      </div>
    </div>
  </div>
</div>

<!-- Contenido principal -->
<div class="hero">
  <h1 style="color: white;" class="title-cancha">CANCHA ⚽</h1>
  <p class="subtitle">Tu club a un click</p>

  <!-- Anuncios multimedia -->
  <div class="media-section">
    <div class="carousel-container">
      <div class="carousel-slide active">
        <img src="../assets/img/feature1.jpg" alt="Gestión de socios">
        <div class="carousel-text">
          <h3>👥 Gestión de Socios</h3>
          <p>Registra, organiza y comunica con todos los miembros de tu club en un solo lugar.</p>
        </div>
      </div>
      <div class="carousel-slide">
        <img src="../assets/img/feature2.jpg" alt="Convocatorias inteligentes">
        <div class="carousel-text">
          <h3>📢 Convocatorias Inteligentes</h3>
          <p>Crea convocatorias automáticas y recibe confirmaciones en tiempo real.</p>
        </div>
      </div>
      <div class="carousel-slide">
        <img src="../assets/img/feature3.jpg" alt="Finanzas transparentes">
        <div class="carousel-text">
          <h3>💰 Finanzas Transparentes</h3>
          <p>Lleva control de cuotas, gastos y balance financiero de tu club.</p>
        </div>
      </div>
      
      <!-- Indicadores -->
      <div class="carousel-indicators">
        <span class="indicator active" data-slide="0"></span>
        <span class="indicator" data-slide="1"></span>
        <span class="indicator" data-slide="2"></span>
      </div>
    </div>
  </div>
</div>

<!-- GOOGLE LOGIN SCRIPT -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<script>
  function handleCredentialResponse(response) {
    fetch('../api/login_google.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ token: response.credential })
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        alert(data.message || 'Error al iniciar sesión');
        if (data.redirect) window.location.href = data.redirect;
        return;
      }

      const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
      localStorage.setItem('cancha_device', deviceId);
      localStorage.setItem('cancha_session', 'active');

      if (data.club_slug) {
        localStorage.setItem('cancha_club', data.club_slug);
        window.location.href = `pages/dashboard.php?id_club=${data.club_slug}`;
      } else {
        window.location.href = `pages/buscar_club.php`;
      }
    })
    .catch(() => alert('Error de conexión'));
  }

  // Ajustes para móvil
  function adjustForMobile() {
    const registerText = document.querySelector('.register-text');
    if (window.innerWidth <= 768 && registerText) {
      registerText.textContent = 'Regista tu club';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    adjustForMobile();
    window.addEventListener('resize', adjustForMobile);
    
    const savedClub = localStorage.getItem('cancha_club');
    const btnEnter = document.getElementById('btnEnterClub');
    const googleContainer = document.getElementById('googleLoginContainer');
    
    if (savedClub) {
      // Usuario reconocido: mostrar botón Entrar, ocultar Google
      btnEnter.style.display = 'block';
      googleContainer.style.display = 'none';
      
      btnEnter.onclick = () => {
        window.location.href = `pages/dashboard.php?id_club=${savedClub}`;
      };
    } else {
      // Usuario nuevo: mostrar Google, ocultar botón Entrar
      btnEnter.style.display = 'none';
      googleContainer.style.display = 'block';
    }
  });
</script>

</body>
</html>