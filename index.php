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

    /* Botones de la barra superior - transparentes con borde blanco */
    .btn-register,
    .btn-enter {
      background: transparent !important;
      border: 2px solid white !important;
      color: white !important;
    }

    /* Sección multimedia principal */
    .media-main {
      position: relative;
      height: 280px;
      margin: 2rem 0;
      overflow: hidden;
    }

    /* Carrusel horizontal */
    .carousel-horizontal {
      position: relative;
      width: 100%;
      height: 100%;
    }

    .carousel-track {
      display: flex;
      height: 100%;
      transition: transform 0.5s ease-in-out;
    }

    .carousel-item {
      min-width: 300px;
      height: 100%;
      position: relative;
      margin: 0 10px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }

    .carousel-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .item-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 51, 102, 0.85);
      padding: 1rem;
      color: white;
      text-align: center;
    }

    .item-overlay h4 {
      margin: 0;
      font-size: 1.1rem;
    }

    /* Controles del carrusel */
    .carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(0, 0, 0, 0.5);
      color: white;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      font-size: 1.2rem;
      cursor: pointer;
      z-index: 10;
      transition: background 0.2s;
    }

    .carousel-btn:hover {
      background: rgba(0, 0, 0, 0.8);
    }

    .carousel-btn.prev { left: 10px; }
    .carousel-btn.next { right: 10px; }

    /* Línea divisoria amarilla */
    .divider-yellow {
      height: 3px;
      background: #FFD700;
      margin: 2rem 0;
      width: 80%;
      margin-left: auto;
      margin-right: auto;
    }

    /* Métricas inferiores */
    .metrics-section {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .metric-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 16px;
      padding: 1.2rem 0.5rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
    }

    .metric-card:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateY(-3px);
    }

    .metric-icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    .metric-value {
      font-size: 1.8rem;
      font-weight: bold;
      color: white;
      margin-bottom: 0.3rem;
    }

    .metric-label {
      font-size: 0.9rem;
      opacity: 0.9;
    }

    /* Responsive móvil */
    @media (max-width: 768px) {
      .media-main {
        height: 220px;
      }
      
      .carousel-item {
        min-width: 250px;
      }
      
      .metrics-section {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.8rem;
      }
      
      .metric-value {
        font-size: 1.4rem;
      }
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

  <!-- Sección multimedia principal -->
<div class="media-main">
  <!-- Carrusel horizontal -->
  <div class="carousel-horizontal">
    <div class="carousel-track" id="carouselTrack">
      <div class="carousel-item">
        <img src="../assets/img/feature1.jpg" alt="Gestión de socios">
        <div class="item-overlay">
          <h4>👥 Gestión de Socios</h4>
        </div>
      </div>
      <div class="carousel-item">
        <img src="../assets/img/feature2.jpg" alt="Convocatorias">
        <div class="item-overlay">
          <h4>📢 Convocatorias</h4>
        </div>
      </div>
      <div class="carousel-item">
        <img src="../assets/img/feature3.jpg" alt="Finanzas">
        <div class="item-overlay">
          <h4>💰 Finanzas</h4>
        </div>
      </div>
      <div class="carousel-item">
        <img src="../assets/img/feature4.jpg" alt="Estadísticas">
        <div class="item-overlay">
          <h4>📊 Estadísticas</h4>
        </div>
      </div>
    </div>
    
    <!-- Controles -->
    <button class="carousel-btn prev" onclick="moveCarousel(-1)">‹</button>
    <button class="carousel-btn next" onclick="moveCarousel(1)">›</button>
  </div>
</div>

<!-- Línea divisoria amarilla -->
<div class="divider-yellow"></div>

<!-- Métricas inferiores -->
<div class="metrics-section">
  <div class="metric-card" onclick="showMetric('clubes')">
    <div class="metric-icon">🏟️</div>
    <div class="metric-value" id="metric-clubes">0</div>
    <div class="metric-label">Clubes</div>
  </div>
  <div class="metric-card" onclick="showMetric('socios')">
    <div class="metric-icon">👥</div>
    <div class="metric-value" id="metric-socios">0</div>
    <div class="metric-label">Socios</div>
  </div>
  <div class="metric-card" onclick="showMetric('eventos')">
    <div class="metric-icon">📅</div>
    <div class="metric-value" id="metric-eventos">0</div>
    <div class="metric-label">Eventos</div>
  </div>
  <div class="metric-card" onclick="showMetric('visitas')">
    <div class="metric-icon">👁️</div>
    <div class="metric-value" id="metric-visitas">0</div>
    <div class="metric-label">Visitas</div>
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

  // Carrusel horizontal
  let currentIndex = 0;
  const track = document.getElementById('carouselTrack');
  const items = document.querySelectorAll('.carousel-item');
  const totalItems = items.length;

  function moveCarousel(direction) {
    currentIndex += direction;
    
    // Loop infinito
    if (currentIndex >= totalItems) {
      currentIndex = 0;
    } else if (currentIndex < 0) {
      currentIndex = totalItems - 1;
    }
    
    const offset = -currentIndex * (300 + 20); // ancho + margen
    track.style.transform = `translateX(${offset}px)`;
  }

  // Métricas (simuladas - reemplazar con API real)
  function loadMetrics() {
    // Simular datos reales
    document.getElementById('metric-clubes').textContent = '127';
    document.getElementById('metric-socios').textContent = '2.4K';
    document.getElementById('metric-eventos').textContent = '89';
    document.getElementById('metric-visitas').textContent = '15K';
  }

  function showMetric(type) {
    const values = {
      clubes: '127 clubes activos',
      socios: '2.4K socios registrados',
      eventos: '89 eventos este mes',
      visitas: '15K visitas mensuales'
    };
    alert(values[type]);
  }

  // Cargar métricas al inicio
  document.addEventListener('DOMContentLoaded', () => {
    loadMetrics();
    
    // También ajustar botones de barra superior
    const registerBtn = document.querySelector('.btn-register');
    const enterBtn = document.getElementById('btnEnterClub');
    if (registerBtn) registerBtn.textContent = 'Registrar un club';
  });
</script>

</body>
</html>