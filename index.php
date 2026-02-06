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
  <!-- Barra superior -->
<div class="top-bar">
  <div class="menu-container" style="display: flex; align-items: center; gap: 1.5rem;">
    <!-- Menú Recintos Deportivos (desplegable) -->
    <div class="dropdown-menu" style="position: relative; display: inline-block;">
      <button class="menu-btn" style="
        background: rgba(255,255,255,0.9); 
        color: #071289; 
        border: none; 
        padding: 0.5rem 1rem; 
        border-radius: 8px; 
        font-size: 0.9rem; 
        font-weight: bold; 
        cursor: pointer; 
        display: flex; 
        align-items: center; 
        gap: 0.3rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
      ">
        🏟️ Gestión Recintos
      </button>
      <div class="dropdown-content" style="
        position: absolute; 
        top: 100%; 
        left: 0; 
        background: white; 
        min-width: 200px; 
        box-shadow: 0 8px 16px rgba(0,0,0,0.2); 
        border-radius: 8px; 
        z-index: 1000; 
        opacity: 0; 
        visibility: hidden; 
        transform: translateY(-5px); 
        transition: all 0.3s ease;
        margin-top: 5px;
      ">
        <a href="login_recinto.php" style="
          display: block; 
          padding: 0.6rem 1.2rem; 
          color: #071289; 
          text-decoration: none; 
          font-weight: bold;
          border-bottom: 1px solid #eee;
        ">🔐 Entrar a tu Recinto</a>
        <a href="registro_recinto.php" style="
          display: block; 
          padding: 0.6rem 1.2rem; 
          color: #071289; 
          text-decoration: none; 
          font-weight: bold;
        ">➕ Registra tu Recinto</a>
      </div>
    </div>

    <!-- Opciones principales -->
    <a href="registro_club.php" class="menu-option" style="
      background: rgba(255,255,255,0.9); 
      color: #071289; 
      text-decoration: none; 
      padding: 0.5rem 1rem; 
      border-radius: 8px; 
      font-size: 0.9rem; 
      font-weight: bold; 
      display: flex; 
      align-items: center; 
      gap: 0.3rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    ">⚽ Registra tu Club</a>
    
    <a href="registro_socio.php" class="menu-option" style="
      background: rgba(255,255,255,0.9); 
      color: #071289; 
      text-decoration: none; 
      padding: 0.5rem 1rem; 
      border-radius: 8px; 
      font-size: 0.9rem; 
      font-weight: bold; 
      display: flex; 
      align-items: center; 
      gap: 0.3rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    ">👥 Inscripción a un Club</a>
  </div>

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

<style>
/* Estilos para hover en desktop */
.dropdown-menu:hover .dropdown-content {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

/* Responsive mobile */
@media (max-width: 768px) {
  .menu-container {
    gap: 0.8rem;
  }
  
  .menu-btn, .menu-option {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
  }
  
  .dropdown-content {
    min-width: 180px;
    right: 0;
    left: auto;
  }
  
  /* Para mobile, hacer clic para abrir/cerrar */
  .dropdown-menu-click .dropdown-content {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) !important;
  }
}
</style>

<script>
// Para mobile, hacer clic para abrir/cerrar
document.addEventListener('DOMContentLoaded', function() {
  const menuBtn = document.querySelector('.menu-btn');
  const dropdownContent = document.querySelector('.dropdown-content');
  const dropdownMenu = document.querySelector('.dropdown-menu');
  
  if (window.innerWidth <= 768) {
    menuBtn.addEventListener('click', function(e) {
      e.preventDefault();
      dropdownMenu.classList.toggle('dropdown-menu-click');
    });
  }
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