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
      font-size: 1.2rem;
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

    /* Responsive - mantener controles en móvil */
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
      <!-- Feature 1 -->
      <div class="carousel-item" data-feature="socios">
        <img src="../assets/img/feature1.jpg" alt="Gestión de socios">
        <div class="item-overlay">
          <h4>Gestión de Socios</h4>
        </div>
      </div>
      <!-- Feature 2 -->
      <div class="carousel-item" data-feature="convocatorias">
        <img src="../assets/img/feature2.jpg" alt="Convocatorias">
        <div class="item-overlay">
          <h4>Convocatorias</h4>
        </div>
      </div>
      <!-- Feature 3 -->
      <div class="carousel-item" data-feature="finanzas">
        <img src="../assets/img/feature3.jpg" alt="Finanzas">
        <div class="item-overlay">
          <h4>Pago de cuotas</h4>
        </div>
      </div>
      <!-- Feature 4 -->
      <div class="carousel-item" data-feature="estadisticas">
        <img src="../assets/img/feature4.jpg" alt="Estadísticas">
        <div class="item-overlay">
          <h4>Estadísticas</h4>
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

<!-- Línea divisoria amarilla -->
<!-- <div class="divider-yellow"></div> -->

<!-- Métricas inferiores -->
<!-- <div class="metrics-section">
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
</div> -->

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
      content: "En Cancha, cada socio es parte fundamental de la familia. Desde tu inscripción a un Club tendrás acceso inmediato a todas las actividades y eventos, podrás confirmar asistencia a partidos, recibir notificaciones de quienes se anotan o bajan y participar en la vida comunitaria. ¡Tu club te espera!"
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
      content: "Sigue el crecimiento de tu club en tiempo real. Número de socios, eventos realizados, participación en actividades... Todos estos datos no solo muestran números, sino la historia viva de una comunidad que juega junta y crece juntos. Y más adelante podremos ver otros Clubes inscritos en nuestar ciudad y comuna, organizar partidos con ellos, campeonatos entre la comunidad cancha, disponibilidad de canchas, y mucho más."
    }
  };

  // Actualizar descripción
  function updateDescription() {
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

  // Inicialización
  document.addEventListener('DOMContentLoaded', () => {
    if (!track || items.length === 0) return;
    
    // Mostrar primera descripción
    updateDescription();
    
    // Iniciar autoplay
    startAutoSlide();
    
    // Eventos touch
    const carousel = document.querySelector('.carousel-horizontal');
    if (carousel) {
      carousel.addEventListener('touchstart', handleTouchStart, { passive: true });
      carousel.addEventListener('touchmove', handleTouchMove, { passive: true });
      carousel.addEventListener('touchend', handleTouchEnd, { passive: true });
    }
    
    // Ajustar en resize
    window.addEventListener('resize', () => {
      setTimeout(() => moveCarousel(0), 100);
    });
  });

  // Iniciar carrusel automático
  function startAutoSlide() {
    autoSlideInterval = setInterval(() => {
      moveCarousel(1);
    }, 3000);
  }

  // Detener/reanudar en hover
  function setupHoverPause() {
    const carousel = document.querySelector('.carousel-horizontal');
    if (carousel) {
      carousel.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
      carousel.addEventListener('mouseleave', startAutoSlide);
    }
  }

  // Inicialización
  document.addEventListener('DOMContentLoaded', () => {
    // Mostrar primera descripción
    updateDescription();
    
    // Iniciar carrusel automático
    startAutoSlide();
    
    // Configurar pausa en hover
    setupHoverPause();
    
    // Ajustar en resize
    window.addEventListener('resize', () => {
      moveCarousel(0); // Reajustar posición
    });
  });

  // === TOUCH SWIPE PARA MÓVIL ===
  let touchStartX = 0;
  let touchEndX = 0;
  let isSwiping = false;

  function handleTouchStart(e) {
    if (window.innerWidth > 768) return; // Solo en móvil
    
    touchStartX = e.touches[0].clientX;
    isSwiping = true;
  }

  function handleTouchMove(e) {
    if (!isSwiping || window.innerWidth > 768) return;
    
    touchEndX = e.touches[0].clientX;
  }

  function handleTouchEnd() {
    if (!isSwiping || window.innerWidth > 768) return;
    
    const diff = touchStartX - touchEndX;
    const threshold = 50; // Minimo desplazamiento para considerar swipe
    
    if (Math.abs(diff) > threshold) {
      if (diff > 0) {
        // Swipe izquierda → siguiente imagen
        moveCarousel(1);
      } else {
        // Swipe derecha → imagen anterior
        moveCarousel(-1);
      }
    }
    
    isSwiping = false;
  }

  // Agregar eventos touch al carrusel
  document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.querySelector('.carousel-horizontal');
    if (carousel) {
      carousel.addEventListener('touchstart', handleTouchStart, { passive: true });
      carousel.addEventListener('touchmove', handleTouchMove, { passive: true });
      carousel.addEventListener('touchend', handleTouchEnd, { passive: true });
    }
  });

  // Métricas (simuladas - reemplazar con API real)
  //function loadMetrics() {
    // Simular datos reales
  //  document.getElementById('metric-clubes').textContent = '127';
  //  document.getElementById('metric-socios').textContent = '2.4K';
  //  document.getElementById('metric-eventos').textContent = '89';
  //  document.getElementById('metric-visitas').textContent = '15K';
  //}

  //function showMetric(type) {
  //  const values = {
  //    clubes: '127 clubes activos',
  //    socios: '2.4K socios registrados',
  //    eventos: '89 eventos este mes',
  //    visitas: '15K visitas mensuales'
  //  };
  //  alert(values[type]);
  //}

  // Cargar métricas al inicio
  //document.addEventListener('DOMContentLoaded', () => {
  //  loadMetrics();
    
    // También ajustar botones de barra superior
  //  const registerBtn = document.querySelector('.btn-register');
  //  const enterBtn = document.getElementById('btnEnterClub');
  //  if (registerBtn) registerBtn.textContent = 'Registrar un club';
  //});
</script>

</body>
</html>