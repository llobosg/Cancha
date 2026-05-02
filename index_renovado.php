<?php
// pages/mockups/index_renovado.php
// ⚠️ ARCHIVO DE PRUEBA - NO TOCAR index.php PRODUCTIVO
require_once __DIR__ . '/includes/config.php';

// === MANTENEMOS LA LÓGICA DE LOGIN EXACTAMENTE IGUAL ===
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$error_login = '';
if (isset($_POST['login_alternativo'])) {
    $email = trim($_POST['email_alt'] ?? '');
    $password = $_POST['password_alt'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_login = 'Email y contraseña son requeridos';
    } else {        
        $stmt = $pdo->prepare("SELECT id_socio, password_hash, email FROM socios WHERE email = ? AND password_hash IS NOT NULL");
        $stmt->execute([$email]);
        $socio = $stmt->fetch();

        if ($socio && password_verify($password, $socio['password_hash'])) {
            $_SESSION['id_socio'] = $socio['id_socio'];
            $_SESSION['user_email'] = $email;
            setcookie('cancha_session_id', session_id(), [
                'expires' => time() + 86400, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true, 'samesite' => 'Lax'
            ]);

            $stmt_clubes = $pdo->prepare("SELECT c.id_club, c.email_responsable, c.nombre FROM socio_club sc JOIN clubs c ON sc.id_club = c.id_club WHERE sc.id_socio = ? AND sc.estado = 'activo' ORDER BY c.nombre ASC LIMIT 1");
            $stmt_clubes->execute([$socio['id_socio']]);
            $primer_club = $stmt_clubes->fetch();

            if ($primer_club) {
                $club_slug = substr(md5($primer_club['id_club'] . $primer_club['email_responsable']), 0, 8);
                $_SESSION['club_id'] = $primer_club['id_club'];
                $_SESSION['current_club'] = $club_slug;
                header('Location: ../pages/dashboard_socio.php?id_club=' . $club_slug);
                exit;
            } else {
                header('Location: ../pages/dashboard_socio.php');
                exit;
            }
        } else {
            $error_login = 'Credenciales incorrectas o contraseña no configurada';
        }
    }
}

// === MOCK DATA PARA RANKINGS PÁDEL (Reemplazar con query real cuando conectes) ===
$rankings_padel = [];
try {
    // Primero verificamos si existe la columna id_deporte
    $checkCol = $pdo->query("SHOW COLUMNS FROM torneos LIKE 'id_deporte'")->fetch();
    
    if ($checkCol) {
        // Si existe, usamos el filtro por deporte
        $stmt = $pdo->prepare("
            SELECT t.nombre as torneo, DATE_FORMAT(t.fecha_fin, '%b %Y') as fecha,
                   t.num_parejas_max as participantes
            FROM torneos t
            WHERE t.id_deporte = 'padel' AND t.estado = 'cerrado'
            ORDER BY t.fecha_fin DESC LIMIT 5
        ");
        $stmt->execute();
        $rankings_padel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Si NO existe, mostramos torneos cerrados sin filtro de deporte
        // (luego puedes ajustar según tu estructura real)
        $stmt = $pdo->prepare("
            SELECT t.nombre as torneo, DATE_FORMAT(t.fecha_fin, '%b %Y') as fecha,
                   t.num_parejas_max as participantes,
                   'Pádel' as deporte_mock
            FROM torneos t
            WHERE t.estado = 'cerrado'
            ORDER BY t.fecha_fin DESC LIMIT 5
        ");
        $stmt->execute();
        $rankings_padel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Fallback total: datos mock si hay cualquier error
    error_log("Error rankings: " . $e->getMessage());
    $rankings_padel = [
        ['torneo'=>'Copa Verano 2024', 'fecha'=>'Mar 2024', 'participantes'=>16, 'deporte_mock'=>'Pádel'],
        ['torneo'=>'Torneo Aniversario', 'fecha'=>'Feb 2024', 'participantes'=>12, 'deporte_mock'=>'Pádel'],
    ];
}

$show_splash = !isset($_SESSION['visited_index']) || $_SESSION['visited_index'] === false;
$_SESSION['visited_index'] = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>CanchaSport - Tu comunidad deportiva 360º</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Evitar error 404 de favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg'/>" type="image/svg+xml">
  <style>
    :root {
      --primary-start: #CE93D8; --primary-end: #AB47BC;
      --accent-green: #4CAF50; --accent-blue: #2196F3;
      --text-dark: #2D3748; --text-light: #718096;
      --bg-light: #F7FAFC; --card-glass: rgba(255,255,255,0.92);
      --shadow-soft: 0 4px 20px rgba(171,71,188,0.15);
      --shadow-float: 0 12px 35px rgba(0,0,0,0.12);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-light);
      color: var(--text-dark);
      min-height: 100vh;
      background-image: 
        radial-gradient(circle at 10% 90%, rgba(206,147,216,0.08) 0%, transparent 40%),
        radial-gradient(circle at 90% 10%, rgba(171,71,188,0.06) 0%, transparent 40%);
    }

    /* === HEADER SIMPLIFICADO === */
    .app-header {
      position: sticky; top: 0; z-index: 1000;
      background: linear-gradient(90deg, var(--primary-start), var(--primary-end));
      padding: 0.75rem 1.25rem;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 12px rgba(171,71,188,0.25);
    }
    .brand {
      display: flex; align-items: center; gap: 0.6rem;
      color: white; text-decoration: none;
    }
    .brand-logo {
      width: 36px; height: 36px; border-radius: 12px;
      background: rgba(255,255,255,0.2);
      display: grid; place-items: center; font-size: 1.2rem;
    }
    .brand-name { font-weight: 700; font-size: 1.25rem; letter-spacing: -0.3px; }
    
    .header-actions { display: flex; gap: 0.6rem; }
    .btn-header {
      padding: 0.5rem 1rem; border-radius: 14px;
      font-weight: 500; font-size: 0.9rem; cursor: pointer;
      border: 2px solid white; background: transparent; color: white;
      transition: all 0.2s;
    }
    .btn-header.primary { background: white; color: var(--primary-end); }
    .btn-header:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

    /* === HERO SECTION === */
    .hero {
      text-align: center; padding: 2.5rem 1.5rem 1.5rem;
      max-width: 720px; margin: 0 auto;
    }
    .hero-title {
      font-size: 2.2rem; font-weight: 700; margin-bottom: 0.5rem;
      background: linear-gradient(135deg, var(--primary-end), #7B1FA2);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .hero-subtitle {
      font-size: 1.1rem; color: var(--text-light); margin-bottom: 1.5rem;
      line-height: 1.5;
    }
    
    /* Sport Badges */
    .sport-badges {
      display: flex; justify-content: center; gap: 0.75rem;
      margin-bottom: 2rem; flex-wrap: wrap;
    }
    .sport-badge {
      display: flex; align-items: center; gap: 0.4rem;
      padding: 0.4rem 0.9rem; border-radius: 20px;
      background: var(--card-glass); font-size: 0.85rem; font-weight: 500;
      box-shadow: var(--shadow-soft); border: 1px solid rgba(255,255,255,0.7);
    }
    .sport-badge .icon { font-size: 1.1rem; }

    /* === CAROUSEL SIMPLIFICADO === */
    .carousel-container {
      max-width: 680px; margin: 0 auto 2rem; padding: 0 1rem;
    }
    .carousel {
      position: relative; border-radius: 24px; overflow: hidden;
      box-shadow: var(--shadow-float); background: white;
    }
    .carousel-track { display: flex; transition: transform 0.4s ease; }
    .carousel-slide {
      min-width: 100%; height: 220px; position: relative;
    }
    .carousel-slide img {
      width: 100%; height: 100%; object-fit: cover;
    }
    .carousel-caption {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(transparent, rgba(0,0,0,0.7));
      padding: 1.5rem 1rem 1rem; color: white;
    }
    .carousel-caption h4 { font-size: 1.1rem; font-weight: 600; }
    .carousel-dots {
      display: flex; justify-content: center; gap: 0.5rem;
      margin-top: 0.75rem;
    }
    .dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: #CBD5E0; cursor: pointer; transition: all 0.2s;
    }
    .dot.active { background: var(--primary-end); transform: scale(1.2); }

    /* === NUEVA SECCIÓN: RANKINGS PÁDEL === */
    .rankings-section {
      max-width: 720px; margin: 0 auto 3rem; padding: 0 1.5rem;
    }
    .section-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.25rem;
    }
    .section-title {
      font-size: 1.3rem; font-weight: 600; color: var(--text-dark);
      display: flex; align-items: center; gap: 0.5rem;
    }
    .view-all {
      font-size: 0.9rem; color: var(--primary-end); font-weight: 500;
      text-decoration: none; display: flex; align-items: center; gap: 0.3rem;
    }
    
    .ranking-grid {
      display: grid; gap: 0.85rem;
    }
    .ranking-card {
      background: var(--card-glass); border-radius: 18px;
      padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;
      box-shadow: var(--shadow-soft); border: 1px solid rgba(255,255,255,0.8);
      transition: transform 0.2s;
    }
    .ranking-card:hover { transform: translateY(-2px); }
    .ranking-badge {
      width: 42px; height: 42px; border-radius: 12px;
      background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
      color: white; display: grid; place-items: center;
      font-weight: 600; font-size: 0.9rem; flex-shrink: 0;
    }
    .ranking-info { flex: 1; min-width: 0; }
    .ranking-tournament { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.2rem; }
    .ranking-date { font-size: 0.8rem; color: var(--text-light); }
    .ranking-winners {
      font-size: 0.85rem; margin-top: 0.3rem;
      display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
    }
    .winner-tag {
      padding: 0.2rem 0.6rem; border-radius: 10px;
      background: rgba(76,175,80,0.12); color: #2E7D32;
      font-size: 0.75rem; font-weight: 500;
    }
    .runnerup-tag {
      padding: 0.2rem 0.6rem; border-radius: 10px;
      background: rgba(33,150,243,0.12); color: #1565C0;
      font-size: 0.75rem; font-weight: 500;
    }
    .ranking-participants {
      font-size: 0.8rem; color: var(--text-light);
      background: #EDF2F7; padding: 0.25rem 0.6rem;
      border-radius: 8px; flex-shrink: 0;
    }

    /* === MODAL UNIFICADO (Login/Registro) === */
    .modal-backdrop {
      position: fixed; inset: 0; background: rgba(0,0,0,0.55);
      backdrop-filter: blur(4px); z-index: 2000;
      display: none; justify-content: center; align-items: center;
      padding: 1rem;
    }
    .modal-card {
      background: white; border-radius: 24px; padding: 1.75rem;
      max-width: 420px; width: 100%; max-height: 90vh; overflow-y: auto;
      box-shadow: var(--shadow-float); position: relative;
    }
    .modal-close {
      position: absolute; top: 1rem; right: 1rem;
      width: 32px; height: 32px; border-radius: 50%;
      background: #F7FAFC; border: none; font-size: 1.2rem;
      cursor: pointer; display: grid; place-items: center;
      color: var(--text-light); transition: all 0.2s;
    }
    .modal-close:hover { background: #EDF2F7; color: var(--text-dark); }
    
    .modal-tabs {
      display: flex; gap: 0.5rem; margin-bottom: 1.5rem;
      background: #F7FAFC; padding: 0.3rem; border-radius: 14px;
    }
    .modal-tab {
      flex: 1; padding: 0.6rem; border-radius: 10px;
      font-weight: 500; font-size: 0.9rem; cursor: pointer;
      text-align: center; transition: all 0.2s; color: var(--text-light);
    }
    .modal-tab.active {
      background: white; color: var(--primary-end);
      box-shadow: 0 2px 8px rgba(171,71,188,0.15);
    }
    
    .modal-form { display: none; flex-direction: column; gap: 1.25rem; }
    .modal-form.active { display: flex; }
    
    .form-group label {
      display: block; font-weight: 500; font-size: 0.9rem;
      margin-bottom: 0.5rem; color: var(--text-dark);
    }
    .form-group input {
      width: 100%; padding: 0.85rem 1rem; border-radius: 12px;
      border: 2px solid #E2E8F0; font-size: 1rem;
      transition: border-color 0.2s;
    }
    .form-group input:focus {
      outline: none; border-color: var(--primary-end);
    }
    
    .btn-modal {
      width: 100%; padding: 0.9rem; border-radius: 14px;
      background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
      color: white; border: none; font-weight: 600; font-size: 1rem;
      cursor: pointer; transition: transform 0.2s;
    }
    .btn-modal:active { transform: scale(0.98); }
    
    .modal-footer {
      text-align: center; margin-top: 1rem; font-size: 0.9rem; color: var(--text-light);
    }
    .modal-footer a { color: var(--primary-end); text-decoration: none; font-weight: 500; }

    /* === TOAST === */
    .toast {
      position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: #2D3748; color: white; padding: 0.85rem 1.5rem;
      border-radius: 14px; font-size: 0.9rem; font-weight: 500;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2); opacity: 0; visibility: hidden;
      transition: all 0.3s; z-index: 3000; max-width: 90%; text-align: center;
    }
    .toast.show { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }

    /* === SPLASH SCREEN === */
    .splash {
      position: fixed; inset: 0; background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      z-index: 9999; transition: opacity 0.4s ease;
    }
    .splash-ball {
      font-size: 3.5rem; animation: spin 2s linear infinite, bounce 1.5s ease-in-out infinite;
    }
    .splash-text { color: white; margin-top: 1.5rem; font-weight: 500; opacity: 0.95; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes bounce { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-12px) rotate(180deg); } }

    /* === RESPONSIVE === */
    @media (max-width: 480px) {
      .app-header { padding: 0.6rem 1rem; }
      .brand-name { font-size: 1.15rem; }
      .btn-header { padding: 0.45rem 0.85rem; font-size: 0.85rem; }
      .hero { padding: 2rem 1.25rem 1rem; }
      .hero-title { font-size: 1.9rem; }
      .carousel-slide { height: 190px; }
      .ranking-card { padding: 0.9rem 1rem; }
      .modal-card { padding: 1.5rem; margin: 0.5rem; }
    }
    /* === CARRUSEL MEJORADO === */
    .carousel-slide {
      min-width: 100%; 
      height: 240px; /* Un poco más alto para los convenios */
      position: relative;
    }

    .carousel-caption p {
      margin: 0.3rem 0 0 0;
      font-size: 0.9rem;
      opacity: 0.95;
      line-height: 1.4;
    }

    /* Slide placeholder (futuros convenios) */
    .carousel-slide[style*="linear-gradient"] {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Animación suave para el contador */
    #slideCounter, #slideTotal {
      font-weight: 600;
      color: var(--primary-end);
      transition: color 0.3s;
    }

    /* Responsive mejorado */
    @media (max-width: 480px) {
      .carousel-slide { 
        height: 200px; 
      }
      .carousel-caption h4 { 
        font-size: 1rem; 
      }
      .carousel-caption p { 
        font-size: 0.8rem; 
      }
    }
  </style>
</head>
<body>

<?php if ($show_splash): ?>
  <div id="splash" class="splash">
    <div class="splash-ball">⚽🎾🏐</div>
    <div class="splash-text">Preparando tu experiencia CanchaSport...</div>
  </div>
  <script>
    setTimeout(() => {
      const s = document.getElementById('splash');
      if(s) { s.style.opacity='0'; setTimeout(()=>s.remove(), 400); }
    }, 1800);
  </script>
<?php endif; ?>

<!-- HEADER SIMPLIFICADO -->
<header class="app-header">
  <a href="#" class="brand">
    <div class="brand-logo">🏟️</div>
    <span class="brand-name">CanchaSport</span>
  </a>
  <div class="header-actions">
    <button class="btn-header" onclick="openModal('register')">Registrarse</button>
    <button class="btn-header primary" onclick="openModal('login')">Ingresar</button>
  </div>
</header>

<!-- HERO -->
<main class="hero">
  <h1 class="hero-title">Tu deporte, tu comunidad</h1>
  <p class="hero-subtitle">Gestiona tu club, reserva canchas, únete a torneos y conecta con otros deportistas. Todo en un solo lugar.</p>
  
  <!-- Sport Badges -->
  <div class="sport-badges">
    <span class="sport-badge"><span class="icon">⚽</span>Fútbol</span>
    <span class="sport-badge"><span class="icon">🎾</span>Pádel</span>
    <span class="sport-badge"><span class="icon">🏐</span>Vóley</span>
    <span class="sport-badge"><span class="icon">🎾</span>Tenis</span>
  </div>

  <!-- Carousel Simplificado -->
  <!-- === CARRUSEL MEJORADO (Features + Convenios) === -->
  <div class="carousel-container">
    <div class="carousel">
      <div class="carousel-track" id="carouselTrack">
        <!-- Slide 1: Feature - Gestión -->
        <div class="carousel-slide">
          <img src="assets/img/feature1.jpg" alt="Gestión de socios">
          <div class="carousel-caption">
            <h4>👥 Gestión de Socios Simplificada</h4>
            <p style="font-size:0.9rem; margin-top:0.3rem; opacity:0.95;">Administra tu club sin complicaciones</p>
          </div>
        </div>
        
        <!-- Slide 2: Convenio - Club Pasco Providencia -->
        <div class="carousel-slide">
          <img src="assets/img/convenio_club_pasco.jpeg" alt="Club Pasco Providencia">
          <div class="carousel-caption" style="background: linear-gradient(transparent, rgba(107,33,168,0.85));">
            <h4>🏟️ Club Pasco Providencia</h4>
            <p style="font-size:0.85rem; margin-top:0.3rem; opacity:0.95;">🏆 El primer club en Chile en incorporar Pádel</p>
            <span style="display:inline-block; margin-top:0.5rem; padding:0.25rem 0.75rem; background:rgba(255,255,255,0.2); border-radius:12px; font-size:0.75rem; font-weight:500;">
              ⭐ Convenio Exclusivo
            </span>
            <div class="carousel-slide" onclick="window.open('club_pasco.php')" style="cursor:pointer;">
          </div>
        </div>
        
        <!-- Slide 3: Feature - Reservas -->
        <div class="carousel-slide">
          <img src="assets/img/feature2.jpg" alt="Reservas">
          <div class="carousel-caption">
            <h4>🎾 Reservas en 3 Clicks</h4>
            <p style="font-size:0.9rem; margin-top:0.3rem; opacity:0.95;">Rápido, fácil y sin esperas</p>
          </div>
        </div>
        
        <!-- Slide 4: Convenio - Mi Pádel San Joaquín -->
        <div class="carousel-slide">
          <img src="assets/img/convenio_mi_padel.jpeg" alt="Mi Pádel San Joaquín">
          <div class="carousel-caption" style="background: linear-gradient(transparent, rgba(107,33,168,0.85));">
            <h4>🏟️ Mi Pádel San Joaquín</h4>
            <p style="font-size:0.85rem; margin-top:0.3rem; opacity:0.95;"> Nuevas y modernas instalaciones</p>
            <span style="display:inline-block; margin-top:0.5rem; padding:0.25rem 0.75rem; background:rgba(255,255,255,0.2); border-radius:12px; font-size:0.75rem; font-weight:500;">
              ⭐ Convenio Exclusivo
            </span>
            <div class="carousel-slide" onclick="window.open('mi_padel.php')" style="cursor:pointer;">
          </div>
        </div>
        
        <!-- Slide 5: Feature - Torneos -->
        <div class="carousel-slide">
          <img src="assets/img/feature3.jpg" alt="Torneos">
          <div class="carousel-caption">
            <h4>🏆 Torneos con Rankings en Vivo</h4>
            <p style="font-size:0.9rem; margin-top:0.3rem; opacity:0.95;">Compite y sube en el ranking</p>
          </div>
        </div>
        
        <!-- Slide 6: Placeholder - Futuro Convenio -->
        <div class="carousel-slide" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
          <div style="position:absolute; inset:0; display:grid; place-items:center; text-align:center; padding:2rem;">
            <div>
              <div style="font-size:3rem; margin-bottom:1rem; opacity:0.9;">🏟️</div>
              <h4 style="font-size:1.3rem; margin-bottom:0.5rem;">Próximamente</h4>
              <p style="font-size:0.95rem; opacity:0.9; margin-bottom:1rem;">MundoSport</p>
              <span style="display:inline-block; padding:0.3rem 0.8rem; background:rgba(255,255,255,0.2); border-radius:12px; font-size:0.75rem; font-weight:500; border:2px solid rgba(255,255,255,0.4);">
                🔜 Nuevo Convenio
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Dots mejorados -->
    <div class="carousel-dots" id="carouselDots"></div>
    
    <!-- Indicador de slide actual -->
    <div style="text-align:center; margin-top:0.75rem; font-size:0.85rem; color:var(--text-light);">
      <span id="slideCounter">1</span> / <span id="slideTotal">6</span>
    </div>
  </div>
</main>

<!-- === NUEVA SECCIÓN: RANKINGS PÁDEL === -->
<section class="rankings-section">
  <div class="section-header">
    <h2 class="section-title">🏆 Rankings Pádel</h2>
    <a href="#" class="view-all" onclick="showToast('🔜 Próximamente: historial completo'); return false;">Ver todos →</a>
  </div>
  
  <div class="ranking-grid">
    <?php foreach($rankings_padel as $r): ?>
    <div class="ranking-card">
      <div class="ranking-badge">🥇</div>
      <div class="ranking-info">
        <div class="ranking-tournament"><?= htmlspecialchars($r['torneo']) ?></div>
        <div class="ranking-date">📅 <?= htmlspecialchars($r['fecha']) ?></div>
        <div class="ranking-winners">
          <span class="winner-tag">🏆 <?= htmlspecialchars($r['ganadores']) ?></span>
          <span class="runnerup-tag">🥈 <?= htmlspecialchars($r['subcampeones']) ?></span>
        </div>
      </div>
      <div class="ranking-participants"><?= $r['participantes'] ?> pares</div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- === MODAL UNIFICADO === -->
<div id="authModal" class="modal-backdrop" onclick="closeModal(event)">
  <div class="modal-card">
    <button class="modal-close" onclick="forceCloseModal()">&times;</button>
    
    <div class="modal-tabs">
      <div class="modal-tab active" data-tab="login" onclick="switchTab('login')">🔐 Ingresar</div>
      <div class="modal-tab" data-tab="register" onclick="switchTab('register')">✍️ Registrarse</div>
    </div>

    <!-- Login Form -->
    <form id="loginForm" class="modal-form active" method="POST">
      <?php if($error_login): ?>
        <div style="background:#FEE2E2; color:#991B1B; padding:0.75rem; border-radius:10px; font-size:0.85rem; text-align:center;">
          <?= htmlspecialchars($error_login) ?>
        </div>
      <?php endif; ?>
      
      <div class="form-group">
        <label for="email_alt">Email</label>
        <input type="email" id="email_alt" name="email_alt" required placeholder="tu@email.com">
      </div>
      <div class="form-group">
        <label for="password_alt">Contraseña</label>
        <input type="password" id="password_alt" name="password_alt" required placeholder="••••••••">
      </div>
      <button type="submit" name="login_alternativo" class="btn-modal">Iniciar Sesión</button>
      <div class="modal-footer">
        <a href="#" onclick="showToast('🔜 Recuperación de contraseña próximamente'); return false;">¿Olvidaste tu contraseña?</a>
      </div>
    </form>

    <!-- Register Options -->
    <div id="registerForm" class="modal-form">
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <a href="../pages/registro_socio.php" style="display:flex; align-items:center; gap:0.75rem; padding:0.9rem; border-radius:14px; background:#F7FAFC; text-decoration:none; color:var(--text-dark); font-weight:500; transition:all 0.2s;">
          <span style="font-size:1.3rem;">🎾</span>
          <div>
            <div style="font-weight:600;">Socio Individual</div>
            <div style="font-size:0.8rem; color:var(--text-light);">Para jugadores que no pertenecen a un club</div>
          </div>
        </a>
        <a href="../pages/registro_club.php" style="display:flex; align-items:center; gap:0.75rem; padding:0.9rem; border-radius:14px; background:#F7FAFC; text-decoration:none; color:var(--text-dark); font-weight:500; transition:all 0.2s;">
          <span style="font-size:1.3rem;">⚽</span>
          <div>
            <div style="font-weight:600;">Club de Amigos</div>
            <div style="font-size:0.8rem; color:var(--text-light);">Para equipos que quieren organizarse</div>
          </div>
        </a>
        <a href="../pages/registro_centro_contacto.php" style="display:flex; align-items:center; gap:0.75rem; padding:0.9rem; border-radius:14px; background:#F7FAFC; text-decoration:none; color:var(--text-dark); font-weight:500; transition:all 0.2s;">
          <span style="font-size:1.3rem;">🏟️</span>
          <div>
            <div style="font-weight:600;">Centro Deportivo</div>
            <div style="font-size:0.8rem; color:var(--text-light);">Para administradores de recintos</div>
          </div>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast">✅ Acción realizada</div>

<script>
// === SPLASH & MODALS ===
function openModal(type) {
  const modal = document.getElementById('authModal');
  if(modal) {
    modal.style.display = 'flex';
    switchTab(type);
  }
}
function closeModal(e) {
  if(e.target.id === 'authModal') forceCloseModal();
}
function forceCloseModal() {
  document.getElementById('authModal').style.display = 'none';
}
function switchTab(tab) {
  document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.modal-form').forEach(f => f.classList.remove('active'));
  document.querySelector(`.modal-tab[data-tab="${tab}"]`).classList.add('active');
  document.getElementById(tab + 'Form').classList.add('active');
}
// === VARIABLES GLOBALES ===
let currentSlide = 0;
const track = document.getElementById('carouselTrack');
const slides = track ? Array.from(track.children) : [];
const dotsContainer = document.getElementById('carouselDots');

// === TOAST (DEFINIR AQUÍ, ANTES DE CUALQUIER ONCLICK) ===
function showToast(msg, type = 'success') {
    // Remover toast anterior si existe
    const existing = document.getElementById('toastNotification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'toastNotification';
    toast.textContent = msg;
    toast.style.cssText = `
        position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
        background: ${type === 'error' ? '#C62828' : '#2E7D32'}; color: white;
        padding: 0.85rem 1.5rem; border-radius: 14px; font-size: 0.9rem; font-weight: 500;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2); z-index: 3000; max-width: 90%; text-align: center;
        opacity: 0; transition: all 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    // Animar entrada
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
    });
    
    // Auto-ocultar
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// === CAROUSEL (CORREGIDO) ===
function initCarousel() {
    console.log('?? Carousel: slides.length =', slides.length);
    console.log('?? Carousel: track =', track);

    if(!track || slides.length === 0) {
        console.warn('⚠️ Carousel: track o slides no encontrados');
        return;
    }
    
    // Actualizar total de slides
    const totalEl = document.getElementById('slideTotal');
    if(totalEl) totalEl.textContent = slides.length;
    
    // Crear dots
    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.className = 'dot' + (i===0 ? ' active' : '');
        dot.onclick = () => goToSlide(i);
        if(dotsContainer) dotsContainer.appendChild(dot);
    });
    
    // Auto-play (6 segundos)
    setInterval(() => goToSlide((currentSlide+1) % slides.length), 6000);
}

function goToSlide(index) {
    if(!track || slides.length === 0) return;
    
    currentSlide = index;
    track.style.transform = `translateX(-${index*100}%)`;
    
    // Actualizar dots
    document.querySelectorAll('.dot').forEach((d,i) => {
        d.classList.toggle('active', i===index);
    });
    
    // Actualizar contador
    const counterEl = document.getElementById('slideCounter');
    if(counterEl) counterEl.textContent = index + 1;
}

function goToSlide(index) {
  currentSlide = index;
  track.style.transform = `translateX(-${index*100}%)`;
  document.querySelectorAll('.dot').forEach((d,i) => d.classList.toggle('active', i===index));
  
  // Actualizar contador
  document.getElementById('slideCounter').textContent = index + 1;
}
function goToSlide(index) {
  currentSlide = index;
  track.style.transform = `translateX(-${index*100}%)`;
  document.querySelectorAll('.dot').forEach((d,i) => d.classList.toggle('active', i===index));
}

// === INIT ===
document.addEventListener('DOMContentLoaded', () => {
  initCarousel();
  // Prevenir scroll en modal abierto
  const modal = document.getElementById('authModal');
  if(modal) {
    new MutationObserver(() => {
      document.body.style.overflow = modal.style.display === 'flex' ? 'hidden' : '';
    }).observe(modal, {attributes:true, attributeFilter:['style']});
  }
});
</script>
</body>
</html>