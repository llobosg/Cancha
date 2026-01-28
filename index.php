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
      color: #fff;
    }

    /* ===== BARRA SUPERIOR ===== */
    .topbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 64px;
      background: rgba(0, 40, 20, 0.85);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      z-index: 100;
      box-shadow: 0 4px 15px rgba(0,0,0,.35);
    }

    .topbar a {
      color: #fff;
      text-decoration: none;
      font-size: .95rem;
      font-weight: 500;
    }

    .topbar a:hover {
      text-decoration: underline;
    }

    .btn-direct {
      background: #00cc66;
      border: none;
      color: #fff;
      padding: .45rem 1.4rem;
      border-radius: 30px;
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
      display: none;
    }

    .btn-direct:hover {
      background: #00aa55;
      transform: translateY(-1px);
    }

    /* ===== CONTENIDO ===== */
    .content {
      padding-top: 110px;
      text-align: center;
      max-width: 800px;
      margin: 0 auto;
    }

    .google-box {
      margin-bottom: 3.2rem;
    }

    .title-cancha {
      font-family: 'Dancing Script', cursive;
      font-size: 3.8rem;
      margin-bottom: .5rem;
      text-shadow: 0 3px 6px rgba(0,0,0,.5);
    }

    .subtitle {
      font-size: 1.25rem;
      opacity: .95;
    }

    @media (max-width: 768px) {
      .title-cancha { font-size: 3rem; }
      .subtitle { font-size: 1.1rem; }
      .topbar { padding: 0 1rem; }
    }
  </style>
</head>

<body>

<!-- ===== BARRA SUPERIOR ===== -->
<div class="topbar">
  <div>
    <a href="../pages/registro_club.php">¿Eres representante? Registrar un club</a>
  </div>

  <div>
    <button id="btnDirect" class="btn-direct">Entrar a mi Club</button>
  </div>
</div>

<!-- ===== CONTENIDO ===== -->
<div class="content">

  <!-- GOOGLE LOGIN -->
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

  <!-- HERO -->
  <h1 class="title-cancha">CANCHA ⚽</h1>
  <p class="subtitle">Tu club deportivo, sin fricción</p>

</div>

<!-- GOOGLE SDK -->
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