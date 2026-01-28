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
    body {
      background:
        linear-gradient(rgba(0,20,10,.55), rgba(0,30,15,.65)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      margin: 0;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
    }

    .hero {
      text-align: center;
      max-width: 820px;
      padding: 2rem;
    }

    .title-cancha {
      font-family: 'Dancing Script', cursive;
      font-size: 3.8rem;
      margin-bottom: .5rem;
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

    .btn-primary {
      background: #00cc66;
      border: none;
      color: #fff;
      padding: .9rem 2.4rem;
      border-radius: 50px;
      font-size: 1.15rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
    }

    .btn-primary:hover {
      background: #00aa55;
      transform: translateY(-2px);
    }

    /* Acceso directo */
    .btn-direct {
      margin-top: 1.2rem;
      display: none;
    }

    /* Google */
    .google-box {
      margin-top: 1.4rem;
    }

    /* Footer opciones */
    .secondary-actions {
      margin-top: 2.5rem;
      font-size: .95rem;
      opacity: .9;
    }

    .secondary-actions a {
      color: #fff;
      text-decoration: underline;
      font-weight: 500;
    }

    @media (max-width: 768px) {
      .title-cancha { font-size: 3rem; }
      .main-card h2 { font-size: 1.5rem; }
    }
  </style>
</head>

<body>

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

    <!-- Botón acceso directo -->
    <button id="btnDirect" class="btn-primary btn-direct">
      Entrar a mi club
    </button>

    <!-- Google Login -->
    <div class="google-box">
      <div id="g_id_onload"
           data-client_id="887808441549-lpgd9gs8t1dqe9r00a5uj7omg8iob8mt.apps.googleusercontent.com"
           data-callback="handleCredentialResponse"
           data-auto_select="true">
      </div>

      <div class="g_id_signin"
           data-type="standard"
           data-size="large"
           data-theme="outline"
           data-text="continue_with"
           data-shape="pill">
      </div>
    </div>

  </div>

  <!-- ACCIÓN SECUNDARIA -->
  <div class="secondary-actions">
    ¿Eres representante?
    <a href="../pages/registro_club.php">Registrar un club</a>
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
        // Usuario autenticado pero sin club
        window.location.href = `pages/buscar_club.php`;
      }
    })
    .catch(() => alert('Error de conexión'));
  }

  document.addEventListener('DOMContentLoaded', () => {
    const savedClub = localStorage.getItem('cancha_club');
    const btn = document.getElementById('btnDirect');

    if (savedClub) {
      btn.style.display = 'inline-block';
      btn.onclick = () => {
        window.location.href = `pages/dashboard.php?id_club=${savedClub}`;
      };
    }
  });
</script>

</body>
</html>
