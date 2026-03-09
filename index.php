<?php
session_start();
// Procesar login alternativo - ACTUALIZADO PARA SOCIOS INDIVIDUALES Y CLUBES
$error_login = '';
if (isset($_POST['login_alternativo'])) {
    $email = trim($_POST['email_alt'] ?? '');
    $password = $_POST['password_alt'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_login = 'Email y contraseña son requeridos';
    } else {
        require_once __DIR__ . '/includes/config.php';
        
        // Buscar socio por email (incluye individuales y de club)
        $stmt = $pdo->prepare("
            SELECT id_socio, id_club, password_hash, email
            FROM socios 
            WHERE email = ? AND password_hash IS NOT NULL
        ");
        $stmt->execute([$email]);
        $socio = $stmt->fetch();
        
        if ($socio && password_verify($password, $socio['password_hash'])) {
            // Login exitoso
            $_SESSION['id_socio'] = $socio['id_socio'];
            $_SESSION['user_email'] = $email;
            
            if ($socio['id_club']) {
                // Socio de club
                $stmt_club = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
                $stmt_club->execute([$socio['id_club']]);
                $club_data = $stmt_club->fetch();
                
                if ($club_data) {
                    $club_slug = substr(md5($socio['id_club'] . $club_data['email_responsable']), 0, 8);
                    $_SESSION['club_id'] = $socio['id_club'];
                    $_SESSION['current_club'] = $club_slug;
                    header('Location: pages/dashboard_socio.php?id_club=' . $club_slug);
                    exit;
                } else {
                    $error_login = 'Club no encontrado';
                }
            } else {
                // Socio individual
                header('Location: pages/dashboard_socio.php');
                exit;
            }
        } else {
            $error_login = 'Credenciales incorrectas o contraseña no configurada';
        }
    }
}
$show_splash = !isset($_SESSION['visited_index']) || $_SESSION['visited_index'] === false;
$_SESSION['visited_index'] = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CanchaSport - Comunidad deportiva 360º</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚽</text></svg>">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      background:
        linear-gradient(rgba(0,20,10,.40), rgba(0,30,15,.50)),
        url('assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
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
      background: rgba(143, 6, 189, 0.95);
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
      text-shadow: 0 3px 6px rgba(245, 243, 247, 0.84);
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

    qr-section {
      margin-top: 3rem;
      background: rgba(255,255,255,0.1);
      padding: 2rem;
      border-radius: 16px;
    }

    .qr-title {
      font-size: 1.3rem;
      margin-bottom: 1.5rem;
      color: #4ECDC4;
    }

    .qr-container {
      display: inline-block;
      background: white;
      padding: 12px;
      border-radius: 8px;
    }

    .qr-code {
      width: 180px;
      height: 180px;
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
    <div class="loading-text">Estamos abriendo el recinto para que entres a CanchaSport</div>
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
      <button id="recintosBtn" class="btn-register" style="
        background: transparent !important;
        border: 2px solid white !important;
        color: white !important;
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
      ">
        Registrarse
      </button>
      <div id="dropdownContentRecintos" style="
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
        <a href="pages/registro_centro_contacto.php" style="
          display: block;
          padding: 0.8rem 1.5rem;
          color: #071289;
          text-decoration: none;
          font-weight: bold;
        ">🏟️ Centro Deportivo</a>
         <a href="pages/registro_club.php" style="
          display: block;
          padding: 0.8rem 1.5rem;
          color: #071289;
          text-decoration: none;
          font-weight: bold;
          border-bottom: 1px solid #eee;
        ">⚽ Club de amigos</a>
        <a href="pages/registro_socio.php" style="
          display: block;
          padding: 0.8rem 1.5rem;
          color: #9B59B6;
          text-decoration: none;
          font-weight: bold;
        ">🎾 Socio Individual</a>
      </div>
    </div>

    <!-- Menú "Ingreso" (desplegable) -->
    <div id="registroDropdown" style="position: relative; display: inline-block;">
      <button id="registroBtn" class="btn-register" style="
        background: transparent !important;
        border: 2px solid white !important;
        color: white !important;
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
      ">
        Ingreso
      </button>
      <div id="dropdownContentRegistro" style="
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
        ">🔐 Admin Centro Deportivo</a>
        
        <!-- Botón para abrir el modal de login de socio -->
        <a href="#" onclick="toggleLoginAlternativo(); return false;" style="
          display: block;
          padding: 0.8rem 1.5rem;
          color: #9B59B6;
          text-decoration: none;
          font-weight: bold;
        ">⚽🎾 Socio Jugador</a>
      </div>
    </div>
  </div>

  <!-- Botones de login -->
  <div class="google-login-container">
    <!-- Google Login 
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
    </div> -->
  </div>
</div>

<!-- Script para los dropdowns -->
<script>
  const recintosBtn = document.getElementById('recintosBtn');
  const registroBtn = document.getElementById('registroBtn');
  const recintosDropdown = document.getElementById('dropdownContentRecintos');
  const registroDropdown = document.getElementById('dropdownContentRegistro');

  // Función para cerrar todos los dropdowns
  function closeAllDropdowns() {
    recintosDropdown.style.display = 'none';
    registroDropdown.style.display = 'none';
  }

  // Recintos - hover
  recintosBtn.addEventListener('mouseenter', () => {
    closeAllDropdowns();
    recintosDropdown.style.display = 'block';
  });

  // Registro - hover  
  registroBtn.addEventListener('mouseenter', () => {
    closeAllDropdowns();
    registroDropdown.style.display = 'block';
  });

  // Cerrar al salir de cualquier dropdown o botón
  [recintosBtn, recintosDropdown, registroBtn, registroDropdown].forEach(el => {
    el.addEventListener('mouseleave', () => {
      setTimeout(() => {
        if (!recintosDropdown.matches(':hover') && !recintosBtn.matches(':hover') &&
            !registroDropdown.matches(':hover') && !registroBtn.matches(':hover')) {
          closeAllDropdowns();
        }
      }, 100);
    });
  });

  // Cerrar al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#recintosDropdown') && !e.target.closest('#registroDropdown')) {
      closeAllDropdowns();
    }
  });
</script>

<!-- Contenido principal -->
<div class="hero">
  <h1 class="title-cancha">CanchaSport <span onclick="window.location.href='pages/ceo_login.php'" style="cursor:pointer; color:#FFD700;">⚽</span></h1>
  <p class="subtitle">Tu deporte o Gestión de un Centro Deportivo a un click</p>

  <!-- Sección multimedia principal -->
  <div class="media-main">
    <!-- Carrusel horizontal -->
    <div class="carousel-horizontal">
      <div class="carousel-track" id="carouselTrack">
        <!-- Feature 1 -->
        <div class="carousel-item" data-feature="socios">
          <img src="assets/img/feature1.jpg" alt="Gestión de socios">
          <div class="item-overlay">
            <h4>👥 Gestión de Socios</h4>
          </div>
        </div>
        <!-- Feature 2 -->
        <div class="carousel-item" data-feature="convocatorias">
          <img src="assets/img/feature2.jpg" alt="Convocatorias">
          <div class="item-overlay">
            <h4>📢 Convocatorias</h4>
          </div>
        </div>
        <!-- Feature 3 -->
        <div class="carousel-item" data-feature="finanzas">
          <img src="assets/img/feature3.jpg" alt="Finanzas">
          <div class="item-overlay">
            <h4>💰 Finanzas club de amigos o Centro Deportivo</h4>
          </div>
        </div>
        <!-- Feature 4 -->
        <div class="carousel-item" data-feature="estadisticas">
          <img src="assets/img/feature4.jpg" alt="Estadísticas">
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

<!-- Login alternativo modal - SIEMPRE PRESENTE -->
<div id="loginOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1001;"></div>
<div id="loginAlternativo" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1002; background: rgba(255,255,255,0.15); padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%;">
  <h3 style="color: #FFD700; margin-bottom: 1.5rem; text-align: center; font-size: 1.3rem;">🔐 Iniciar Sesión</h3>
  
  <?php if (isset($error_login)): ?>
    <div style="background: #ffebee; color: #c62828; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; border: 1px solid #ffcdd2;">
      <?= htmlspecialchars($error_login) ?>
    </div>
  <?php endif; ?>
  
  <form method="POST" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div>
      <label for="email_alt" style="display: block; font-weight: bold; color: white; margin-bottom: 0.6rem; text-align: left; font-size: 0.95rem;">Email *</label>
      <input type="email" id="email_alt" name="email_alt" required 
            style="width: 100%; padding: 0.9rem; border: 2px solid #ccc; border-radius: 8px; color: #071289; font-size: 1rem; background: white;">
    </div>
    
    <div>
      <label for="password_alt" style="display: block; font-weight: bold; color: white; margin-bottom: 0.6rem; text-align: left; font-size: 0.95rem;">Contraseña *</label>
      <input type="password" id="password_alt" name="password_alt" required 
            style="width: 100%; padding: 0.9rem; border: 2px solid #ccc; border-radius: 8px; color: #071289; font-size: 1rem; background: white;">
    </div>
    
    <button type="submit" name="login_alternativo" 
            style="padding: 1rem; background: #071289; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1rem; margin-top: 0.5rem; transition: background 0.3s;">
      Iniciar Sesión
    </button>
    
    <div style="text-align: center; margin-top: 1rem;">
      <a href="#" onclick="mostrarRecuperarPassword(); return false;" 
         style="color: #FFD700; text-decoration: underline; font-size: 0.9rem;">
        ¿Olvidaste tu contraseña?
      </a>
    </div>
    
    <button type="button" onclick="toggleLoginAlternativo()" 
            style="padding: 0.5rem; background: #666; color: white; border: none; border-radius: 4px; font-size: 0.9rem;">
      Cerrar
    </button>
  </form>
</div>

<!-- Modal Recuperar Contraseña - SIEMPRE PRESENTE -->
<div id="recuperarPasswordOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1002;"></div>
<div id="recuperarPasswordModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1003; background: rgba(255,255,255,0.15); padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%;">
  <h3 style="color: #FFD700; margin-bottom: 1.5rem; text-align: center; font-size: 1.3rem;">🔐 Recuperar Contraseña</h3>
  
  <form id="recuperarPasswordForm" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div>
      <label for="email_recuperar" style="display: block; font-weight: bold; color: white; margin-bottom: 0.6rem; text-align: left; font-size: 0.95rem;">Email *</label>
      <input type="email" id="email_recuperar" name="email_recuperar" required 
             style="width: 100%; padding: 0.9rem; border: 2px solid #ccc; border-radius: 8px; color: #071289; font-size: 1rem; background: white;">
    </div>
    
    <button type="submit" 
            style="padding: 1rem; background: #071289; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1rem; margin-top: 0.5rem; transition: background 0.3s;">
      Enviar enlace de recuperación
    </button>
    
    <button type="button" onclick="cerrarRecuperarPassword()" 
            style="padding: 0.5rem; background: #666; color: white; border: none; border-radius: 4px; font-size: 0.9rem;">
      Cancelar
    </button>
  </form>
</div>

<script>
// Funciones robustas para modales
function toggleLoginAlternativo() {
    const loginDiv = document.getElementById('loginAlternativo');
    const overlay = document.getElementById('loginOverlay');
    
    if (!loginDiv || !overlay) {
        console.error('❌ Modales de login no encontrados en el DOM');
        alert('Error: No se pudo cargar el formulario de login. Por favor, recarga la página.');
        return;
    }
    
    if (loginDiv.style.display === 'none' || loginDiv.style.display === '') {
        loginDiv.style.display = 'block';
        overlay.style.display = 'block';
    } else {
        loginDiv.style.display = 'none';
        overlay.style.display = 'none';
    }
}

function toggleModal(loginDiv, overlay) {
    if (loginDiv.style.display === 'none' || loginDiv.style.display === '') {
        loginDiv.style.display = 'block';
        overlay.style.display = 'block';
    } else {
        loginDiv.style.display = 'none';
        overlay.style.display = 'none';
    }
}

// Hacer accesible globalmente
window.toggleLoginAlternativo = toggleLoginAlternativo;

// Funcionalidad del menú desplegable
document.addEventListener('DOMContentLoaded', function() {
  const dropdown = document.getElementById('recintosDropdown');
  const btn = document.getElementById('recintosBtn');
  const content = document.getElementById('dropdownContent');
  
  if (btn && content) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      if (content.style.display === 'block') {
        content.style.display = 'none';
      } else {
        content.style.display = 'block';
      }
    });
    
    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target)) {
        content.style.display = 'none';
      }
    });
  }

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
      content: "En CanchaSport, cada socio es parte fundamental de la familia. Desde tu inscripción a un Club o Jugador Individual tendrás acceso inmediato a todas las actividades y eventos, podrás confirmar asistencia a partidos, pagar las cuotas, recibir notificaciones de quienes se anotan o bajan, participar de campeonatos y en definitiva, disfrutar tu pasión deportiva en vida comunitaria. ¡Tu club te espera!"
    },
    convocatorias: {
      title: "Convocatorias Inteligentes",
      content: "¿Cansado de los grupos de WhatsApp infinitos y llenos de porno que suenan toda la noche mientras quieres dormir? Con CanchaSport, las convocatorias son claras, organizadas y eficientes. Recibe invitaciones personalizadas, confirma tu asistencia con un clic y mantén todo tu historial de participación. La organización nunca fue tan fácil."
    },
    finanzas: {
      title: "Finanzas Transparentes",
      content: "La transparencia es clave en cualquier club. En CanchaSport, puedes ver el estado de tus cuotas, el uso de los fondos colectivos y contribuir al crecimiento sostenible de tu equipo. Todo claro, justo y accesible desde tu celular."
    },
    estadisticas: {
      title: "Estadísticas que Inspiran",
      content: "Sigue el crecimiento de tu club o el tuyo propio en tiempo real. Número de socios, eventos realizados, participación en actividades, estadísiticas de tus Americanos. Todos estos datos no solo muestran números, sino la historia viva de una comunidad que juega junta y crece juntos. Y más adelante podremos ver otros Clubes inscritos en nuestar ciudad, comuna y organizar partidos con ellos, campeonatos entre la comunidad CanchaSport, disponibilidad de canchas, y mucho más."
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
    }, 6000);
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
    const threshold = 30;
    
    if (Math.abs(diff) > threshold) {
      if (diff > 0) {
        moveCarousel(1);
      } else {
        moveCarousel(-1);
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

  // Inicialización mejorada
  adjustForMobile();
  window.addEventListener('resize', adjustForMobile);
    
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
});

// Google Login con manejo de flujos diferenciados
function handleCredentialResponse(response) {
    fetch('api/login_google.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: response.credential})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.action === 'redirect_existing') {
            const deviceId = localStorage.getItem('cancha_device') || crypto.randomUUID();
            localStorage.setItem('cancha_device', deviceId);
            localStorage.setItem('cancha_session', 'active');
            localStorage.setItem('cancha_club', data.club_slug);
            window.location.href = data.redirect;
        } else if (!data.success && data.action === 'welcome_new') {
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

function mostrarRecuperarPassword() {
    const loginDiv = document.getElementById('loginAlternativo');
    const loginOverlay = document.getElementById('loginOverlay');
    const recoverDiv = document.getElementById('recuperarPasswordModal');
    const recoverOverlay = document.getElementById('recuperarPasswordOverlay');
    
    if (loginDiv && loginOverlay) {
        loginDiv.style.display = 'none';
        loginOverlay.style.display = 'none';
    }
    
    if (recoverDiv && recoverOverlay) {
        recoverDiv.style.display = 'block';
        recoverOverlay.style.display = 'block';
    } else {
        alert('Error: No se pudo cargar el formulario de recuperación.');
    }
}

function cerrarRecuperarPassword() {
    const recoverDiv = document.getElementById('recuperarPasswordModal');
    const recoverOverlay = document.getElementById('recuperarPasswordOverlay');
    
    if (recoverDiv && recoverOverlay) {
        recoverDiv.style.display = 'none';
        recoverOverlay.style.display = 'none';
    }
}

// Hacer funciones accesibles globalmente
window.mostrarRecuperarPassword = mostrarRecuperarPassword;
window.cerrarRecuperarPassword = cerrarRecuperarPassword;

// Manejar el formulario de recuperación
document.addEventListener('DOMContentLoaded', function() {
    const recoverForm = document.getElementById('recuperarPasswordForm');
    if (recoverForm) {
        recoverForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email_recuperar').value;
            
            if (!email) {
                alert('Completa el campo de email');
                return;
            }
            
            fetch('api/recuperar_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarRecuperarPassword();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        });
    }
});
</script>

<!-- GOOGLE LOGIN SCRIPT -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<!-- === BANNER DE INSTALACIÓN PWA (solo móvil) === -->
<div id="installBanner" style="display:none; position:fixed; bottom:0; left:0; width:100%; background:#071289; color:white; padding:12px; text-align:center; z-index:10000; box-shadow:0 -2px 10px rgba(0,0,0,0.2);">
  <div style="max-width:600px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
    <span>📲 ¿Quieres usar CanchaSport como app?</span>
    <button id="installBtn" style="background:#FFD700; color:#071289; border:none; padding:6px 12px; border-radius:6px; font-weight:bold; cursor:pointer;">Instalar</button>
    <button id="closeBanner" style="background:transparent; color:white; border:none; font-size:1.2rem; cursor:pointer;">×</button>
  </div>
</div>

<!-- Footer de descarga con íconos oficiales -->
<style>
.download-app-footer {
  position: fixed;
  bottom: 20px;
  left: 20px;
  background: rgba(255, 255, 255, 0.85); /* Fondo gris claro semi-transparente */
  backdrop-filter: blur(10px);
  padding: 12px 16px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  font-family: 'Segoe UI', system-ui, sans-serif;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 12px;
  max-width: 320px;
}
.download-app-text {
  font-size: 0.9rem;
  font-weight: 600;
  color: #333;
  white-space: nowrap;
}
.app-store-btns {
  display: flex;
  gap: 8px;
}
.store-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f8f9fa; /* Gris muy claro */
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.2s;
  border: 1px solid #e9ecef;
}
.store-btn:hover {
  background: #e9ecef;
  transform: translateY(-1px);
}
/* Íconos oficiales en SVG */
.apple-icon {
  width: 20px;
  height: 24px;
}
.google-icon {
  width: 20px;
  height: 24px;
}
@media (max-width: 480px) {
  .download-app-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 10px;
  }
  .app-store-btns {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>

<div class="download-app-footer">
  <div class="download-app-text">Descarga nuestra app</div>
  <div class="app-store-btns">
    <a href="https://apps.apple.com/app/canchasport/id123456789" class="store-btn" target="_blank" title="Apple Store">
      <svg class="apple-icon" viewBox="0 0 128 152" xmlns="http://www.w3.org/2000/svg">
        <path d="M83.2 112c-1.2 3.6-4.8 6-8.4 4.8-19.2-6.4-30.4-22.4-37.6-41.6-2-5.2-1.6-11.2 1.2-16 4.8-8.4 13.6-11.2 21.6-6.8 5.2 2.8 7.6 8.8 5.6 14-5.6 14.4-4 29.6 4.8 43.2 2.4 3.6 1.2 8.4-2.4 10.8zM64 32c4.8 0 8.8-4 8.8-8.8s-4-8.8-8.8-8.8c-4.8 0-8.8 4-8.8 8.8S59.2 32 64 32z" fill="#000"/>
      </svg>
    </a>
    <a href="https://play.google.com/store/apps/details?id=com.canchasport.app" class="store-btn" target="_blank" title="Google Play">
      <svg class="google-icon" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg">
        <path d="M109.2 64c0-3.2-.4-6.4-1.2-9.6l-32 12.8 12.8 32c2.8-1.2 5.6-2.8 8-4.8 4.8-4.8 8-11.2 8-18.4z" fill="#34A853"/>
        <path d="M64 109.2c-12.8 0-24-4.4-32-12.8l16-16c4.8 4.8 11.2 8 16 8s11.2-3.2 16-8l16 16c-8 8.4-19.2 12.8-32 12.8z" fill="#4285F4"/>
        <path d="M64 109.2c12.8 0 24-4.4 32-12.8l-16-16c-4.8 4.8-11.2 8-16 8s-11.2-3.2-16-8l-16 16c8 8.4 19.2 12.8 32 12.8z" fill="#FBBC05"/>
        <path d="M32 64c0-3.2.4-6.4 1.2-9.6L12 41.6C4.8 56 4.8 72 12 86.4l20-22.4z" fill="#EA4335"/>
      </svg>
    </a>
  </div>
</div>

<script>
// === ACTIVAR PROMPT DE INSTALACIÓN PWA ===
if ('serviceWorker' in navigator && /Android|iPhone|iPad/i.test(navigator.userAgent)) {
  let deferredPrompt;
  
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('installBanner').style.display = 'block';
  });

  document.getElementById('installBtn').addEventListener('click', () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          console.log('App instalada');
        }
        deferredPrompt = null;
        document.getElementById('installBanner').style.display = 'none';
      });
    }
  });

  document.getElementById('closeBanner').addEventListener('click', () => {
    document.getElementById('installBanner').style.display = 'none';
  });
}
</script>
</body>
</html>