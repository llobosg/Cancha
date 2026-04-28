<?php
// pages/mockups/index_renovado_v2.php
// ⚠️ ARCHIVO DE PRUEBA - NO TOCAR index.php PRODUCTIVO
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// === LOGIN LOGIC (MANTENIDA IGUAL) ===
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
                header('Location: ../pages/dashboard_socio.php?id_club=' . $club_slug); exit;
            } else {
                header('Location: ../pages/dashboard_socio.php'); exit;
            }
        } else { $error_login = 'Credenciales incorrectas o contraseña no configurada'; }
    }
}

// Query defensiva para ranking de parejas en torneos Pádel de Club Pasco
try {
    $stmt = $pdo->prepare("
        SELECT 
            pt.nombre_pareja,
            SUM(pt.juegos_ganados) as puntaje,  -- Ajusta según tu lógica de puntaje real
            COUNT(DISTINCT pt.id_torneo) as torneos_jugados
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE t.estado = 'cerrado' 
          AND t.nombre_club = 'Club Pasco'  -- O t.id_club = X según tu estructura
          AND t.id_deporte = 'padel'        -- Si existe la columna, sino quitar
        GROUP BY pt.id_pareja, pt.nombre_pareja
        ORDER BY puntaje DESC, torneos_jugados DESC
        LIMIT 10
    ");
    $stmt->execute();
    $ranking_club_pasco = [];
    $pos = 1;
    while($row = $stmt->fetch()) {
        $ranking_club_pasco[] = [
            'pos' => $pos++,
            'pareja' => $row['nombre_pareja'],
            'puntaje' => $row['puntaje'] ?? 0,
            'torneos' => $row['torneos_jugados'] ?? 0
        ];
    }
} catch(PDOException $e) {
    error_log("Error ranking: " . $e->getMessage());
    // Fallback a mock data si hay error
}

// === CAROUSEL ITEMS ===
$carousel_items = [
    [
        'img' => 'assets/img/feature1.jpg',
        'title' => '👥 Gestión de Socios',
        'desc' => 'Cada socio es parte fundamental. Confirma asistencia, paga cuotas y recibe notificaciones en tiempo real.'
    ],
    [
        'img' => 'assets/img/feature2.jpg', 
        'title' => '📢 Convocatorias Inteligentes',
        'desc' => 'Olvídate de los grupos infinitos. Invitaciones claras, confirmación en un clic y historial de participación.'
    ],
    [
        'img' => 'assets/img/padel_club_pasco.jpg', // ← Nueva imagen del torneo Pádel Club Pasco
        'title' => '🏆 Torneos Club Pasco',
        'desc' => 'Participa en los torneos de Pádel organizados por Club Pasco. Rankings en vivo, premios y mucha competencia sana.'
    ],
    [
        'img' => 'assets/img/feature4.jpg',
        'title' => '💰 Finanzas Transparentes',
        'desc' => 'Ve el estado de tus cuotas, el uso de fondos colectivos y contribuye al crecimiento sostenible de tu equipo.'
    ],
];

$show_splash = !isset($_SESSION['visited_index']) || $_SESSION['visited_index'] === false;
$_SESSION['visited_index'] = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>CanchaSport - Tu comunidad deportiva</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-start: #CE93D8; --primary-end: #AB47BC;
      --pasco-accent: #7B1FA2;
      --text-dark: #2D3748; --text-light: #718096;
      --bg-transparent: transparent;
      --card-glass: rgba(255,255,255,0.94);
      --shadow-soft: 0 4px 20px rgba(171,71,188,0.12);
      --shadow-float: 0 12px 35px rgba(0,0,0,0.1);
      --border-thin: 3px solid var(--primary-end);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    
    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-transparent);
      /* Background oficial visible */
      background-image: url('../../assets/img/cancha_pasto2.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      color: var(--text-dark);
      min-height: 100vh;
      padding-bottom: 2rem;
    }
    /* Overlay sutil para legibilidad del texto */
    body::before {
      content: ''; position: fixed; inset: 0;
      background: linear-gradient(180deg, 
        rgba(247,250,252,0.92) 0%, 
        rgba(247,250,252,0.88) 40%,
        rgba(247,250,252,0.95) 100%);
      z-index: -1;
    }

    /* === HEADER CON PÍLDORAS (SE QUEDA) === */
    .app-header {
      position: sticky; top: 0; z-index: 1000;
      background: linear-gradient(90deg, var(--primary-start), var(--primary-end));
      padding: 0.75rem 1.25rem;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 12px rgba(171,71,188,0.25);
    }
    .brand { display: flex; align-items: center; gap: 0.6rem; color: white; text-decoration: none; }
    .brand-logo {
      width: 36px; height: 36px; border-radius: 12px;
      background: rgba(255,255,255,0.2);
      display: grid; place-items: center; font-size: 1.2rem;
    }
    .brand-name { font-weight: 700; font-size: 1.25rem; letter-spacing: -0.3px; }
    
    .header-actions { display: flex; gap: 0.6rem; }
    .btn-pill {
      padding: 0.5rem 1.1rem; border-radius: 50px;
      font-weight: 600; font-size: 0.9rem; cursor: pointer;
      border: 2px solid white; background: transparent; color: white;
      transition: all 0.2s; white-space: nowrap;
    }
    .btn-pill.primary { background: white; color: var(--primary-end); }
    .btn-pill:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

    /* === TAGLINE CENTRADO === */
    .tagline {
      text-align: center; padding: 1.25rem 1.5rem 0.5rem;
      font-size: 1.15rem; font-weight: 600; color: var(--pasco-accent);
      text-shadow: 0 1px 2px rgba(255,255,255,0.8);
    }
    @media (max-width: 480px) {
      .tagline { font-size: 1.05rem; padding: 1rem 1rem 0.25rem; }
    }

    /* === CAROUSEL CON ESPACIOS FINOS === */
    .carousel-wrapper {
      max-width: 680px; margin: 0.5rem auto 1.5rem; padding: 0 1rem;
    }
    .carousel {
      position: relative; border-radius: 20px; overflow: visible;
      background: transparent;
    }
    .carousel-track {
      display: flex; gap: 8px; /* Espacio fino entre imágenes */
      transition: transform 0.4s ease;
      padding: 4px 0; /* Espacio para shadow */
    }
    .carousel-slide {
      min-width: calc(100% - 4px); border-radius: 16px; overflow: hidden;
      position: relative; height: 200px; flex-shrink: 0;
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(255,255,255,0.6);
    }
    .carousel-slide img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform 0.3s;
    }
    .carousel-slide:hover img { transform: scale(1.02); }
    
    /* Descripción estilo badge (misma fuente que sport-badges) */
    .carousel-desc {
      background: var(--card-glass);
      border-radius: 14px; padding: 0.9rem 1.25rem;
      margin-top: 0.75rem; margin-bottom: 1.5rem;
      box-shadow: var(--shadow-soft);
      border-left: 3px solid var(--primary-end); /* Detalle fino izquierdo */
    }
    .carousel-desc-title {
      font-weight: 600; font-size: 0.95rem; color: var(--pasco-accent);
      margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.4rem;
    }
    .carousel-desc-text {
      font-size: 0.85rem; color: var(--text-light); line-height: 1.45;
      font-weight: 400;
    }
    
    .carousel-dots {
      display: flex; justify-content: center; gap: 0.5rem; margin-top: 0.5rem;
    }
    .dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: #CBD5E0; cursor: pointer; transition: all 0.2s;
    }
    .dot.active { background: var(--primary-end); transform: scale(1.3); }

    /* === SECCIÓN RANKING CLUB PASCO === */
    .ranking-section {
      max-width: 680px; margin: 0 auto 2.5rem; padding: 0 1.5rem;
    }
    .ranking-header {
      text-align: center; margin-bottom: 1.25rem;
    }
    .ranking-title {
      font-size: 1.25rem; font-weight: 700; color: var(--pasco-accent);
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .ranking-subtitle {
      font-size: 0.9rem; color: var(--text-light); margin-top: 0.25rem;
    }
    
    /* Cuadro simple con borde izquierdo fino (estilo mockup_B) */
    .ranking-box {
      background: var(--card-glass);
      border-radius: 16px; padding: 1.25rem 1rem;
      border-left: 4px solid var(--primary-end);
      box-shadow: var(--shadow-float);
    }
    .ranking-table {
      width: 100%; border-collapse: collapse;
    }
    .ranking-table th {
      text-align: left; padding: 0.6rem 0.75rem;
      font-size: 0.75rem; font-weight: 600; color: var(--text-light);
      text-transform: uppercase; letter-spacing: 0.5px;
      border-bottom: 2px solid #EDF2F7;
    }
    .ranking-table td {
      padding: 0.85rem 0.75rem; font-size: 0.9rem;
      border-bottom: 1px solid #F7FAFC;
    }
    .ranking-table tr:last-child td { border-bottom: none; }
    .ranking-pos {
      font-weight: 700; color: var(--pasco-accent);
      width: 32px; text-align: center;
    }
    .ranking-pareja { font-weight: 500; color: var(--text-dark); }
    .ranking-score {
      font-weight: 600; color: #2E7D32; text-align: right;
      background: rgba(76,175,80,0.08); padding: 0.3rem 0.6rem;
      border-radius: 10px; font-size: 0.85rem;
    }
    .ranking-tournaments {
      font-size: 0.75rem; color: var(--text-light); text-align: right;
    }

    /* Sport Badges (para referencia de estilo) */
    .sport-badges {
      display: flex; justify-content: center; gap: 0.6rem;
      margin: 0.5rem auto 1.5rem; max-width: 680px; padding: 0 1rem; flex-wrap: wrap;
    }
    .sport-badge {
      display: flex; align-items: center; gap: 0.4rem;
      padding: 0.4rem 0.85rem; border-radius: 20px;
      background: var(--card-glass); font-size: 0.82rem; font-weight: 500;
      box-shadow: var(--shadow-soft); border: 1px solid rgba(255,255,255,0.7);
      color: var(--text-dark);
    }
    .sport-badge .icon { font-size: 1rem; }

    /* === MODAL UNIFICADO (PÍLDORAS) === */
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
      color: var(--text-light);
    }
    .modal-tabs {
      display: flex; gap: 0.5rem; margin-bottom: 1.5rem;
      background: #F7FAFC; padding: 0.3rem; border-radius: 14px;
    }
    .modal-tab {
      flex: 1; padding: 0.65rem; border-radius: 10px;
      font-weight: 600; font-size: 0.9rem; cursor: pointer;
      text-align: center; color: var(--text-light); transition: all 0.2s;
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
    }
    .form-group input:focus { outline: none; border-color: var(--primary-end); }
    .btn-modal {
      width: 100%; padding: 0.9rem; border-radius: 14px;
      background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
      color: white; border: none; font-weight: 600; font-size: 1rem;
      cursor: pointer;
    }
    .modal-footer {
      text-align: center; margin-top: 1rem; font-size: 0.9rem; color: var(--text-light);
    }
    .modal-footer a { color: var(--primary-end); text-decoration: none; font-weight: 500; }
    .register-option {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.9rem; border-radius: 14px; background: #F7FAFC;
      text-decoration: none; color: var(--text-dark); font-weight: 500;
      transition: all 0.2s; margin-bottom: 0.75rem;
    }
    .register-option:hover { background: #EDF2F7; transform: translateX(2px); }
    .register-option .icon { font-size: 1.3rem; }

    /* === TOAST & SPLASH === */
    .toast {
      position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: #2D3748; color: white; padding: 0.85rem 1.5rem;
      border-radius: 14px; font-size: 0.9rem; font-weight: 500;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2); opacity: 0; visibility: hidden;
      transition: all 0.3s; z-index: 3000; max-width: 90%; text-align: center;
    }
    .toast.show { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
    
    .splash {
      position: fixed; inset: 0; background: linear-gradient(135deg, var(--primary-start), var(--primary-end));
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      z-index: 9999; transition: opacity 0.4s ease;
    }
    .splash-ball { font-size: 3.5rem; animation: spin 2s linear infinite, bounce 1.5s ease-in-out infinite; }
    .splash-text { color: white; margin-top: 1.5rem; font-weight: 500; opacity: 0.95; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

    /* === RESPONSIVE MOBILE === */
    @media (max-width: 480px) {
      .app-header { padding: 0.6rem 1rem; }
      .brand-name { font-size: 1.15rem; }
      .btn-pill { padding: 0.45rem 0.95rem; font-size: 0.85rem; }
      .carousel-slide { height: 175px; }
      .carousel-desc { padding: 0.8rem 1rem; margin-top: 0.5rem; }
      .carousel-desc-title { font-size: 0.9rem; }
      .carousel-desc-text { font-size: 0.8rem; }
      .ranking-box { padding: 1rem 0.75rem; }
      .ranking-table td, .ranking-table th { padding: 0.7rem 0.5rem; font-size: 0.85rem; }
      .ranking-score { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
      .modal-card { padding: 1.5rem; margin: 0.5rem; }
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
    setTimeout(() => { const s = document.getElementById('splash'); if(s) { s.style.opacity='0'; setTimeout(()=>s.remove(), 400); }}, 1800);
  </script>
<?php endif; ?>

<!-- HEADER CON PÍLDORAS ✅ -->
<header class="app-header">
  <a href="#" class="brand">
    <div class="brand-logo">🏟️</div>
    <span class="brand-name">CanchaSport</span>
  </a>
  <div class="header-actions">
    <button class="btn-pill" onclick="openModal('register')">Registrarse</button>
    <button class="btn-pill primary" onclick="openModal('login')">Ingresar</button>
  </div>
</header>

<!-- TAGLINE CENTRADO -->
<div class="tagline">Tu deporte, tu club, tu comunidad</div>

<!-- SPORT BADGES (estilo de referencia para descripciones) -->
<div class="sport-badges">
  <span class="sport-badge"><span class="icon">⚽</span>Fútbol</span>
  <span class="sport-badge"><span class="icon">🎾</span>Pádel</span>
  <span class="sport-badge"><span class="icon">🏐</span>Vóley</span>
  <span class="sport-badge"><span class="icon">🎾</span>Tenis</span>
</div>

<!-- CAROUSEL CON DESCRIPCIÓN ESTILO BADGE -->
<div class="carousel-wrapper">
  <div class="carousel">
    <div class="carousel-track" id="carouselTrack">
      <?php foreach($carousel_items as $i => $item): ?>
        <div class="carousel-slide">
          <img src="<?= $item['img'] ?>" alt="<?= $item['title'] ?>" onerror="this.src='https://via.placeholder.com/400x200/AB47BC/ffffff?text=CanchaSport'">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="carousel-dots" id="carouselDots"></div>
  
  <!-- Descripción dinámica (misma fuente que sport-badges) -->
  <div class="carousel-desc">
    <div class="carousel-desc-title" id="descTitle">👥 Gestión de Socios</div>
    <div class="carousel-desc-text" id="descText">Cada socio es parte fundamental. Confirma asistencia, paga cuotas y recibe notificaciones en tiempo real.</div>
  </div>
</div>

<!-- === RANKING CLUB PASCO === -->
<section class="ranking-section">
  <div class="ranking-header">
    <h2 class="ranking-title">🏆 Ranking Club Pasco</h2>
    <div class="ranking-subtitle">Pádel • Torneos 2024</div>
  </div>
  
  <div class="ranking-box">
    <table class="ranking-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Pareja</th>
          <th style="text-align:right">Puntaje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($ranking_club_pasco as $r): ?>
        <tr>
          <td class="ranking-pos"><?= $r['pos'] ?></td>
          <td class="ranking-pareja"><?= htmlspecialchars($r['pareja']) ?></td>
          <td>
            <div class="ranking-score"><?= $r['puntaje'] ?></div>
            <div class="ranking-tournaments"><?= $r['torneos'] ?> torneos</div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- === MODAL UNIFICADO (PÍLDORAS) === -->
<div id="authModal" class="modal-backdrop" onclick="closeModal(event)">
  <div class="modal-card">
    <button class="modal-close" onclick="forceCloseModal()">&times;</button>
    <div class="modal-tabs">
      <div class="modal-tab active" data-tab="login" onclick="switchTab('login')">🔐 Ingresar</div>
      <div class="modal-tab" data-tab="register" onclick="switchTab('register')">✍️ Registrarse</div>
    </div>
    <form id="loginForm" class="modal-form active" method="POST">
      <?php if($error_login): ?>
        <div style="background:#FEE2E2; color:#991B1B; padding:0.75rem; border-radius:10px; font-size:0.85rem; text-align:center;"><?= htmlspecialchars($error_login) ?></div>
      <?php endif; ?>
      <div class="form-group"><label for="email_alt">Email</label><input type="email" id="email_alt" name="email_alt" required placeholder="tu@email.com"></div>
      <div class="form-group"><label for="password_alt">Contraseña</label><input type="password" id="password_alt" name="password_alt" required placeholder="••••••••"></div>
      <button type="submit" name="login_alternativo" class="btn-modal">Iniciar Sesión</button>
      <div class="modal-footer"><a href="#" onclick="showToast('🔜 Recuperación próximamente'); return false;">¿Olvidaste tu contraseña?</a></div>
    </form>
    <div id="registerForm" class="modal-form">
      <a href="../pages/registro_socio.php" class="register-option"><span class="icon">🎾</span><div><div style="font-weight:600;">Socio Individual</div><div style="font-size:0.8rem; color:var(--text-light);">Para jugadores sin club</div></div></a>
      <a href="../pages/registro_club.php" class="register-option"><span class="icon">⚽</span><div><div style="font-weight:600;">Club de Amigos</div><div style="font-size:0.8rem; color:var(--text-light);">Para equipos organizados</div></div></a>
      <a href="../pages/registro_centro_contacto.php" class="register-option"><span class="icon">🏟️</span><div><div style="font-weight:600;">Centro Deportivo</div><div style="font-size:0.8rem; color:var(--text-light);">Para administradores</div></div></a>
    </div>
  </div>
</div>

<div id="toast" class="toast">✅ Acción realizada</div>

<script>
// === MODALS ===
function openModal(type) { document.getElementById('authModal').style.display='flex'; switchTab(type); }
function closeModal(e) { if(e.target.id==='authModal') forceCloseModal(); }
function forceCloseModal() { document.getElementById('authModal').style.display='none'; }
function switchTab(tab) {
  document.querySelectorAll('.modal-tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.modal-form').forEach(f=>f.classList.remove('active'));
  document.querySelector(`.modal-tab[data-tab="${tab}"]`).classList.add('active');
  document.getElementById(tab+'Form').classList.add('active');
}

// === CAROUSEL ===
let currentSlide = 0;
const track = document.getElementById('carouselTrack');
const slides = track?.children || [];
const dotsContainer = document.getElementById('carouselDots');
const descTitle = document.getElementById('descTitle');
const descText = document.getElementById('descText');

const carouselData = <?= json_encode($carousel_items) ?>;

function initCarousel() {
  if(!track || slides.length===0) return;
  slides.forEach((_,i)=>{
    const dot = document.createElement('div');
    dot.className='dot'+(i===0?' active':'');
    dot.onclick=()=>goToSlide(i);
    dotsContainer?.appendChild(dot);
  });
  updateDescription(0);
  setInterval(()=>goToSlide((currentSlide+1)%slides.length), 5000);
}
function goToSlide(index) {
  currentSlide = index;
  track.style.transform = `translateX(calc(-${index*100}% - ${index*8}px))`; // 8px = gap
  document.querySelectorAll('.dot').forEach((d,i)=>d.classList.toggle('active',i===index));
  updateDescription(index);
}
function updateDescription(index) {
  if(carouselData[index]) {
    descTitle.textContent = carouselData[index].title;
    descText.textContent = carouselData[index].desc;
  }
}

// === TOAST ===
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 3000);
}

// === INIT ===
document.addEventListener('DOMContentLoaded', () => {
  initCarousel();
  const modal = document.getElementById('authModal');
  if(modal) new MutationObserver(()=>{ document.body.style.overflow = modal.style.display==='flex'?'hidden':''; })
    .observe(modal, {attributes:true, attributeFilter:['style']});
});
</script>
</body>
</html>