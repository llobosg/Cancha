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
        linear-gradient(rgba(0,20,10,.40), rgba(0,30,15,.50)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      color: #fff;
      padding-top: 70px; /* Espacio para la barra */
    }

    /* Barra superior fija */
    .top-bar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 60px;
      background: rgba(0, 51, 102, 0.9);
      backdrop-filter: blur(10px);
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 2rem;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .top-bar-left a {
      color: #ffcc00;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .top-bar-left a:hover {
      text-decoration: underline;
    }

    .btn-enter-club {
      background: #00cc66;
      color: white;
      border: none;
      padding: 0.5rem 1.2rem;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-enter-club:hover {
      background: #00aa55;
      transform: translateY(-1px);
    }

    .btn-enter-club.hidden {
      display: none;
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
      margin: 2rem 0 1rem 0; /* 2 filas más abajo de la barra */
      text-shadow: 0 3px 6px rgba(0,0,0,.5);
    }

    .subtitle {
      font-size: 1.25rem;
      margin-bottom: 2.5rem;
      opacity: .95;
    }

    /* CTA principal */
    .main-card {
      background: rgba(255,255,255,.12);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 20px;
      padding: 2.5rem 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,.35);
      margin-bottom: 2rem;
    }

    .main-card h2 {
      font-size: 1.8rem;
      margin-bottom: .8rem;
    }

    .main-card p {
      font-size: 1rem;
      opacity: .95;
      margin-bottom: 1.8rem;
    }

    /* Google Login alineado con botón de la barra */
    .google-container {
      display: flex;
      justify-content: flex-end;
      padding-right: 2rem;
      margin-top: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .top-bar {
        padding: 0 1rem;
        height: 55px;
      }
      
      .top-bar-left a {
        font-size: 0.85rem;
      }
      
      .btn-enter-club {
        padding: 0.4rem 1rem;
        font-size: 0.85rem;
      }
      
      .title-cancha { 
        font-size: 3rem;
        margin-top: 1.5rem;
      }
      
      .google-container {
        padding-right: 1rem;
      }
    }
  </style>
</head>

<body>

<!-- Barra superior -->
<div class="top-bar">
  <div class="top-bar-left">
    <a href="registro_club.php">¿Eres representante? Registrar un club</a>
  </div>
  <button id="btnEnterClub" class="btn-enter-club hidden">
    Entrar a mi club
  </button>
</div>

<!-- Contenido principal -->
<div class="hero">
  <h1 class="title-cancha">CANCHA ⚽</h1>
  <p class="subtitle">Tu club deportivo, sin fricción</p>

  <!-- CTA PRINCIPAL -->
  <div class="main-card">
    <h2>Entrar a tu club</h2>
    <p>
      Accede con tu correo o Google.<br>
      Si aún no perteneces a un club, te guiamos en segundos.
    </p>
  </div>

  <!-- Google Login alineado con botón derecho -->
  <div class="google-container">
    <div id="g_id_onload"
         data-client_id="887808441549-lpgd9gs8t1dqe9r00a5uj7omg8iob8mt.apps.googleusercontent.com"
         data-callback="handleCredentialResponse"
         data-auto_select="true">
    </div>

    <div class="g_id_signin"
         data-type="standard"
         data-size="medium"
         data-theme="outline"
         data-text="continue_with"
         data-shape="pill">
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

  document.addEventListener('DOMContentLoaded', () => {
    const savedClub = localStorage.getItem('cancha_club');
    const btn = document.getElementById('btnEnterClub');

    if (savedClub) {
      btn.classList.remove('hidden');
      btn.onclick = () => {
        window.location.href = `pages/dashboard.php?id_club=${savedClub}`;
      };
    }
  });
</script>

</body>
</html>