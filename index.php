<?php
session_start();
$show_splash = !isset($_SESSION['visited_index']) || $_SESSION['visited_index'] === false;
$_SESSION['visited_index'] = true;
?>
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
        linear-gradient(rgba(0,20,10,.40), rgba(0,30,15,.50)),
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
      background: transparent !important;
      border: 2px solid white !important;
      color: white !important;
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
      background: rgba(255,255,255,0.1) !important;
    }
    .btn-enter {
      background: transparent !important;
      border: 2px solid white !important;
      color: white !important;
      padding: 0.4rem 0.8rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-enter:hover {
      background: rgba(255,255,255,0.1) !important;
    }
    .google-login-container {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }
    /* Contenido principal */
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
      color: white;
      text-shadow: 0 3px 6px rgba(0,0,0,.5);
    }
    .subtitle {
      font-size: 1.25rem;
      margin-bottom: 2.5rem;
      opacity: .95;
      color: white;
    }
    /* Sección multimedia principal */
    .media-main {
      position: relative;
      height: 320px;
      margin: 2rem 0;
      overflow: hidden;
    }
    /* Carrusel horizontal */
    .carousel-horizontal {
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    .carousel-track {
      display: flex;
      height: 100%;
      transition: transform 0.5s ease-in-out;
    }
    /* Web: 2 imágenes visibles */
    @media (min-width: 769px) {
      .carousel-item {
        min-width: calc(50% - 20px);
        height: 100%;
        margin: 0 10px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        position: relative;
      }
      .media-main {
        height: 350px;
      }
    }
    /* Móvil: 1 imagen centrada */
    @media (max-width: 768px) {
      .carousel-item {
        min-width: 90%;
        height: 100%;
        margin: 0 auto;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        position: relative;
      }
      .media-main {
        height: 280px;
      }
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
      font-size: 1.2rem;
    }
    /* Controles de navegación */
    .carousel-controls {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin: 1rem 0;
    }
    .nav-btn {
      background: rgba(0, 51, 102, 0.8);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.3);
      width: 50px;
      height: 50px;
      border-radius: 50%;
      font-size: 1.2rem;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .nav-btn:hover {
      background: rgba(0, 51, 102, 1);
      border-color: white;
      transform: scale(1.1);
    }
    .play-pause {
      font-size: 1rem;
    }
    /* Responsive controles */
    @media (max-width: 768px) {
      .carousel-controls {
        margin: 1rem auto;
        width: fit-content;
      }
      .nav-btn {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
      }
    }
    /* Descripción sincronizada */
    .feature-description {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .feature-description h3 {
      color: white;
      margin-bottom: 1rem;
      font-size: 1.4rem;
    }
    .feature-description p {
      color: rgba(255, 255, 255, 0.9);
      line-height: 1.6;
      max-width: 800px;
      margin: 0 auto;
    }
    /* Línea divisoria amarilla */
    .divider-yellow {
      height: 3px;
      background: #FFD700;
      margin: 2rem auto;
      width: 80%;
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
    .submodal {
      position: fixed;
      z-index: 1001;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.6);
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .submodal-content {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      max-width: 500px;
      width: 90%;
      position: relative;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 28px;
      color: #999;
      cursor: pointer;
      font-weight: bold;
    }
    .close-modal:hover {
      color: #333;
    }
    .modal-header h3 {
      color: #003366;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .register-options {
      text-align: center;
    }
    .btn-primary {
      background: #00cc66;
      color: white;
      border: none;
      padding: 0.8rem 2rem;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s;
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
    .btn-primary:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }
    @media (max-width: 768px) {
      .submodal-content {
        padding: 1.5rem;
        margin: 1rem;
      }
      .btn-primary {
        padding: 0.7rem 1.5rem;
        font-size: 1rem;
      }
    }

    /* Splash Screen Animado */
    .splash-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #003366;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.5s ease-out;
    }
    .ball-container {
      animation: bounce 2s infinite;
    }
    .spinning-ball {
      font-size: 4rem;
      animation: spin 2s linear infinite;
      text-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    .loading-text {
      color: white;
      font-size: 1.2rem;
      margin-top: 1.5rem;
      text-align: center;
      max-width: 80%;
      line-height: 1.4;
      opacity: 0.9;
    }
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    @media (max-width: 768px) {
      .spinning-ball {
        font-size: 3rem;
      }
      .loading-text {
        font-size: 1rem;
        max-width: 90%;
      }
    }

    /* Estilos para hover en desktop */
  .dropdown-menu:hover .dropdown-content {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  /* Responsive mobile */
  @media (max-width: 768px) {
    .menu-container {
      flex-direction: column;
      align-items: center;
      gap: 1rem;
    }
    
    .dropdown-menu {
      width: 100%;
      max-width: 300px;
    }
    
    .menu-btn, .menu-option {
      width: 100%;
      justify-content: center;
    }
    
    .dropdown-content {
      position: static;
      opacity: 1;
      visibility: visible;
      transform: none;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
  }
  </style>
</head>
<body>

<?php if ($show_splash): ?>
  <!-- Splash Screen Animado - SOLO EN ENTRADA DIRECTA -->
  <div id="splashScreen" class="splash-screen">
    <div class="ball-container">
      <div class="spinning-ball">⚽</div>
    </div>
    <div class="loading-text">Estamos abriendo el recinto para que entres a Cancha</div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(() => {
        const splash = document.getElementById('splashScreen');
        if (splash) {
          splash.style.opacity = '0';
          setTimeout(() => {
            splash.style.display = 'none';
          }, 500);
        }
      }, 2000);
    });
  </script>
<?php endif; ?>

<!-- Barra superior -->
<div class="top-bar">
  <div style="display: flex; align-items: center; gap: 1rem;">
    <!-- Menú Recintos Deportivos (desplegable) -->
    <div id="recintosDropdown" style="position: relative; display: inline-block;">
      <button id="recintosBtn" class="btn-register" style="background: transparent !important; border: 2px solid white !important; color: white !important; padding: 0.4rem 0.8rem; font-size: 0.9rem;">
        Gestión Recintos
      </button>
      <div id="dropdownContent" style="
        position: absolute; 
        top: 100%; 
        left: 0; 
        background: white; 
        min-width: 200px; 
        box-shadow: 0 8px 16px rgba(0,0,0,0.2); 
        border-radius: 12px; 
        z-index: 1001; 
        display: none;
        margin-top: 5px;
      ">
        <a href="pages/login_recintos.php" style="
          display: block; 
          padding: 0.8rem 1.5rem; 
          color: #071289; 
          text-decoration: none; 
          font-weight: bold;
          border-bottom: 1px solid #eee;
        ">🔐 Entrar a tu Recinto</a>
        <a href="pages/registro_recinto.php" style="
          display: block; 
          padding: 0.8rem 1.5rem; 
          color: #071289; 
          text-decoration: none; 
          font-weight: bold;
        ">Registra tu Recinto</a>
      </div>
    </div>
  </div>

  <div class="google-login-container">
    <!-- Botón Registrar club -->
    <button class="btn-register" onclick="window.location.href='pages/registro_club.php'">
      <span class="flag-icon"></span>
      <span class="register-text">Registrar un club</span>
    </button>
    
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

<script>
// Funcionalidad del menú desplegable
document.addEventListener('DOMContentLoaded', function() {
  const dropdown = document.getElementById('recintosDropdown');
  const btn = document.getElementById('recintosBtn');
  const content = document.getElementById('dropdownContent');
  
  // Abrir/cerrar con clic (funciona en todos los dispositivos)
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (content.style.display === 'block') {
      content.style.display = 'none';
    } else {
      content.style.display = 'block';
    }
  });
  
  // Cerrar al hacer clic fuera
  document.addEventListener('click', function(e) {
    if (!dropdown.contains(e.target)) {
      content.style.display = 'none';
    }
  });
});
</script>

<!-- Contenido principal -->
<div class="hero">
  <h1 class="title-cancha">CANCHA <span onclick="window.location.href='pages/ceo_login.php'" style="cursor:pointer; color:#FFD700;">⚽</span></h1>
  <p class="subtitle">Tu club a un click</p>

  <!-- Sección multimedia principal -->
  <div class="media-main">
    <!-- Carrusel horizontal -->
    <div class="carousel-horizontal">
      <div class="carousel-track" id="carouselTrack">
        <!-- Feature 1 -->
        <div class="carousel-item" data-feature="socios">
          <img src="../assets/img/feature1.jpg" alt="Gestión de socios">
          <div class="item-overlay">
            <h4>👥 Gestión de Socios</h4>
          </div>
        </div>
        <!-- Feature 2 -->
        <div class="carousel-item" data-feature="convocatorias">
          <img src="../assets/img/feature2.jpg" alt="Convocatorias">
          <div class="item-overlay">
            <h4>📢 Convocatorias</h4>
          </div>
        </div>
        <!-- Feature 3 -->
        <div class="carousel-item" data-feature="finanzas">
          <img src="../assets/img/feature3.jpg" alt="Finanzas">
          <div class="item-overlay">
            <h4>💰 Finanzas</h4>
          </div>
        </div>
        <!-- Feature 4 -->
        <div class="carousel-item" data-feature="estadisticas">
          <img src="../assets/img/feature4.jpg" alt="Estadísticas">
          <div class="item-overlay">
            <h4>📊 Estadísticas</h4>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Controles de navegación -->
  <div class="carousel-controls">
    <button class="nav-btn" onclick="moveCarousel(-1)">‹</button>
    <button class="nav-btn play-pause" onclick="toggleAutoPlay()">⏸️</button>
    <button class="nav-btn" onclick="moveCarousel(1)">›</button>
  </div>

  <!-- Línea divisoria amarilla -->
  <div class="divider-yellow"></div>

  <!-- Descripción sincronizada -->
  <div class="feature-description" id="featureDescription">
    <!-- Contenido dinámico -->
  </div>
</div>

<!-- Submodal de inscripción -->
<div id="registerModal" class="submodal" style="display:none;">
  <div class="submodal-content">
    <span class="close-modal" onclick="cerrarRegisterModal()">&times;</span>
    <div class="modal-header">
      <h3>⚽ ¡Hola! Bienvenido a Cancha</h3>
    </div>
    <div class="modal-body">
      <p style="text-align: center; margin-bottom: 1.5rem;">
        <strong>¿Ya perteneces a un club?</strong><br>
        Si es así, pide a tu responsable que te envíe el enlace de invitación.
      </p>
      
      <div class="register-options">
        <button class="btn-primary" onclick="window.location.href='pages/buscar_club.php'">
          🔍 Buscar mi club
        </button>
        
        <p style="margin: 1.2rem 0; color: #666; font-style: italic;">
          ¿Eres responsable de un club?<br>
          <a href="pages/registro_club.php" style="color: #071289; text-decoration: underline;">Registra tu club aquí</a>
        </p>
      </div>
    </div>
  </div>
</div>

<!-- GOOGLE LOGIN SCRIPT -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
  // === CARRUSEL MEJORADO CON TOUCH Y CONTROLES ===
  let currentIndex = 0;
  const track = document.getElementById('carouselTrack');
  const items = document.querySelectorAll('.carousel-item');
  const totalItems = items.length;
  let autoSlideInterval;
  let isAutoPlaying = true;

  // Textos descriptivos para cada feature
  const featureTexts = {
    socios: {
      title: "Gestión de Socios",
      content: "En Cancha, cada socio es parte fundamental de la familia. Desde tu inscripción a un Club tendrás acceso inmediato a todas las actividades y eventos, podrás confirmar asistencia a partidos, pagar las cuotas, recibir notificaciones de quienes se anotan o bajan y participar en la vida comunitaria. ¡Tu club te espera!"
    },
    convocatorias: {
      title: "Convocatorias Inteligentes",
      content: "¿Cansado de los grupos de WhatsApp infinitos y llenos de porno que suenan toda la noche mientras quieres dormir? Con Cancha, las convocatorias son claras, organizadas y eficientes. Recibe invitaciones personalizadas, confirma tu asistencia con un clic y mantén todo tu historial de participación. La organización nunca fue tan fácil."
    },
    finanzas: {
      title: "Finanzas Transparentes",
      content: "La transparencia es clave en cualquier club. En Cancha, puedes ver el estado de tus cuotas, el uso de los fondos colectivos y contribuir al crecimiento sostenible de tu equipo. Todo claro, justo y accesible desde tu celular."
    },
    estadisticas: {
      title: "Estadísticas que Inspiran",
      content: "Sigue el crecimiento de tu club en tiempo real. Número de socios, eventos realizados, participación en actividades... Todos estos datos no solo muestran números, sino la historia viva de una comunidad que juega junta y crece juntos. Y más adelante podremos ver otros Clubes inscritos en nuestar ciudad, comuna y organizar partidos con ellos, campeonatos entre la comunidad cancha, disponibilidad de canchas, y mucho más."
    }
  };

  // Actualizar descripción
  function updateDescription() {
    if (!track || items.length === 0) return;
    
    const currentItem = items[currentIndex];
    const feature = currentItem.dataset.feature;
    const description = document.getElementById('featureDescription');
    
    if (description && featureTexts[feature]) {
      description.innerHTML = `
        <h3>${featureTexts[feature].title}</h3>
        <p>${featureTexts[feature].content}</p>
      `;
    }
  }

  // Mover carrusel
  function moveCarousel(direction = 1) {
    if (!track || items.length === 0) return;
    
    currentIndex = (currentIndex + direction + totalItems) % totalItems;
    
    if (window.innerWidth > 768) {
      // Web: 2 imágenes visibles
      const itemWidth = (track.offsetWidth / 2);
      const offset = -currentIndex * itemWidth;
      track.style.transform = `translateX(${offset}px)`;
    } else {
      // Móvil: 1 imagen centrada
      const offset = -currentIndex * track.offsetWidth;
      track.style.transform = `translateX(${offset}px)`;
    }
    
    updateDescription();
    resetAutoPlay();
  }

  // Toggle autoplay
  function toggleAutoPlay() {
    const playPauseBtn = document.querySelector('.play-pause');
    if (isAutoPlaying) {
      clearInterval(autoSlideInterval);
      isAutoPlaying = false;
      playPauseBtn.textContent = '▶️';
    } else {
      startAutoSlide();
      isAutoPlaying = true;
      playPauseBtn.textContent = '⏸️';
    }
  }

  // Reset autoplay timer
  function resetAutoPlay() {
    if (isAutoPlaying) {
      clearInterval(autoSlideInterval);
      startAutoSlide();
    }
  }

  // Iniciar autoplay
  function startAutoSlide() {
    autoSlideInterval = setInterval(() => {
      moveCarousel(1);
    }, 3000);
  }

  // === TOUCH SWIPE MEJORADO ===
  let touchStartX = 0;
  let touchEndX = 0;

  function handleTouchStart(e) {
    if (window.innerWidth > 768) return;
    touchStartX = e.touches[0].clientX;
  }

  function handleTouchMove(e) {
    if (window.innerWidth > 768) return;
    touchEndX = e.touches[0].clientX;
  }

  function handleTouchEnd() {
    if (window.innerWidth > 768) return;
    
    const diff = touchStartX - touchEndX;
    const threshold = 30; // Umbral más sensible
    
    if (Math.abs(diff) > threshold) {
      if (diff > 0) {
        moveCarousel(1); // Siguiente
      } else {
        moveCarousel(-1); // Anterior
      }
    }
  }

  // Ajustes para móvil
  function adjustForMobile() {
    const registerText = document.querySelector('.register-text');
    if (window.innerWidth <= 768 && registerText) {
      registerText.textContent = 'Registrar Club';
    }
  }

  // Google Login con manejo de flujos diferenciados
  function handleCredentialResponse(response) {
      fetch('../api/login_google.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({token: response.credential})
      })
      .then(r => r.json())
      .then(data => {
          $_SESSION['google_email'] = $google_user_email;
          $_SESSION['logged_in'] = true;
          if (data.success && data.action === 'redirect_existing') {
              // Usuario existente - ir directo al dashboard
              const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
              localStorage.setItem('cancha_device', deviceId);
              localStorage.setItem('cancha_session', 'active');
              localStorage.setItem('cancha_club', data.club_slug);
              
              window.location.href = data.redirect;
              
          } else if (!data.success && data.action === 'welcome_new') {
              // Usuario nuevo - mostrar submodal de bienvenida
              mostrarWelcomeModal(data.email);
              
          } else {
              alert('Error: ' + (data.message || 'No se pudo iniciar sesión'));
          }
      })
      .catch(err => {
          console.error('Login error:', err);
          alert('Error de conexión');
      });
  }

  // Submodal de bienvenida para usuarios nuevos
  function mostrarWelcomeModal(email = '') {
      // Crear el submodal si no existe
      let modal = document.getElementById('welcomeModal');
      if (!modal) {
          modal = document.createElement('div');
          modal.id = 'welcomeModal';
          modal.className = 'submodal';
          modal.innerHTML = `
              <div class="submodal-content">
                  <span class="close-modal" onclick="cerrarWelcomeModal()">&times;</span>
                  <div class="modal-header">
                      <h3>⚽ ¡Hola! Bienvenido a Cancha</h3>
                  </div>
                  <div class="modal-body">
                      <p style="text-align: center; margin-bottom: 1.5rem;">
                          <strong>¿Ya perteneces a un club?</strong><br>
                          Si es así, pide a tu responsable que te envíe el enlace de invitación.
                      </p>
                      
                      <div class="register-options">
                          <button class="btn-primary" onclick="window.location.href='pages/buscar_club.php'">
                              🔍 Buscar mi club
                          </button>
                          
                          <p style="margin: 1.2rem 0; color: #666; font-style: italic;">
                              ¿Eres responsable de un club?<br>
                              <a href="pages/registro_club.php" style="color: #071289; text-decoration: underline;">Registra tu club aquí</a>
                          </p>
                      </div>
                  </div>
              </div>
          `;
          document.body.appendChild(modal);
      }
      
      modal.style.display = 'flex';
      
      if (email) {
          localStorage.setItem('google_email', email);
      }
  }

  function cerrarWelcomeModal() {
      const modal = document.getElementById('welcomeModal');
      if (modal) {
          modal.style.display = 'none';
      }
  }

  // Manejar clic fuera del modal
  document.addEventListener('click', function(event) {
      const modal = document.getElementById('welcomeModal');
      if (modal && event.target === modal) {
          cerrarWelcomeModal();
      }
  });

  function mostrarRegisterModal(email = '') {
    document.getElementById('registerModal').style.display = 'flex';
    if (email) {
      localStorage.setItem('google_email', email);
    }
  }

  function cerrarRegisterModal() {
    document.getElementById('registerModal').style.display = 'none';
  }

  window.onclick = function(event) {
    const modal = document.getElementById('registerModal');
    if (event.target === modal) {
      cerrarRegisterModal();
    }
  }

  // Función mejorada para detectar sesión
  function checkUserSession() {
      return new Promise((resolve) => {
          // Esperar un momento para asegurar que localStorage esté listo
          setTimeout(() => {
              try {
                  const savedClub = localStorage.getItem('cancha_club');
                  const hasSession = localStorage.getItem('cancha_session') === 'active';
                  
                  // Validar que el club sea válido
                  const isValidClub = savedClub && 
                                    savedClub !== 'null' && 
                                    savedClub !== 'undefined' && 
                                    savedClub.trim() !== '' && 
                                    savedClub.length === 8;
                  
                  resolve({
                      hasValidSession: hasSession && isValidClub,
                      clubSlug: isValidClub ? savedClub : null
                  });
              } catch (error) {
                  console.error('Error checking session:', error);
                  resolve({ hasValidSession: false, clubSlug: null });
              }
          }, 100);
      });
  }

  // Inicialización mejorada
  document.addEventListener('DOMContentLoaded', async () => {
    adjustForMobile();
    window.addEventListener('resize', adjustForMobile);
      
    // Verificar sesión con la función mejorada
    const session = await checkUserSession();
    const btnEnter = document.getElementById('btnEnterClub');
    const googleContainer = document.getElementById('googleLoginContainer');
      
    if (session.hasValidSession) {
      btnEnter.style.display = 'block';
      googleContainer.style.display = 'none';
          
      btnEnter.onclick = () => {
        window.location.href = `pages/dashboard_socio.php?id_club=${session.clubSlug}`;
      };
    } else {
      // Limpiar sesión inválida
      localStorage.removeItem('cancha_club');
      localStorage.removeItem('cancha_session');
      localStorage.removeItem('cancha_device');
          
      btnEnter.style.display = 'none';
      googleContainer.style.display = 'block';
    }
    
    // Carrusel
    if (track && items.length > 0) {
      updateDescription();
      startAutoSlide();
      
      // Eventos touch
      const carousel = document.querySelector('.carousel-horizontal');
      if (carousel) {
        carousel.addEventListener('touchstart', handleTouchStart, { passive: true });
        carousel.addEventListener('touchmove', handleTouchMove, { passive: true });
        carousel.addEventListener('touchend', handleTouchEnd, { passive: true });
      }
    }
    
    // Ajustar en resize
    window.addEventListener('resize', () => {
      setTimeout(() => {
        if (track && items.length > 0) {
          moveCarousel(0);
        }
      }, 100);
    });
  });

  // Registrar PWA
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
          console.log('SW registered: ', registration);
        })
        .catch(registrationError => {
          console.log('SW registration failed: ', registrationError);
        });
    });
  }

  // Solicitar permiso para notificaciones
  function requestNotificationPermission() {
    if (!('Notification' in window)) {
      return;
    }
    
    if (Notification.permission === 'granted') {
      subscribeToPush();
    } else if (Notification.permission !== 'denied') {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          subscribeToPush();
        }
      });
    }
  }

  // Suscribir al servicio de push
  function subscribeToPush() {
    // Aquí integrarías con Firebase Cloud Messaging o similar
    console.log('Usuario suscrito a notificaciones');
  }
</script>
</body>
</html>