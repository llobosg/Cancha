<!-- pages/club_confirmado.php -->
<?php
require_once __DIR__ . '/../includes/config.php';
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    die('Club no encontrado');
}
// Opcional: validar slug contra base de datos
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>¡Club registrado! - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#003366">
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <style>
    body {
      background: #e8f5e9;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 2rem;
    }
    .container {
      max-width: 600px;
      margin: 4rem auto;
      background: white;
      padding: 2.5rem;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      text-align: center;
    }
    h1 {
      color: #009966;
      margin-bottom: 1.5rem;
    }
    .success-icon {
      font-size: 3rem;
      margin-bottom: 1.5rem;
      color: #009966;
    }
    .url-box {
      background: #f1f8e9;
      padding: 1.2rem;
      border-radius: 8px;
      margin: 1.5rem 0;
      word-break: break-all;
    }
    .btn-copy {
      background: #3a4f63;
      color: white;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      cursor: pointer;
      margin-top: 0.5rem;
    }
    .btn-share {
      display: inline-block;
      margin-top: 1.5rem;
      padding: 0.8rem 1.5rem;
      background: #009966;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="success-icon">✅</div>
    <h1>¡Tu club está listo!</h1>
    <p>Comparte este enlace con tus compañeros para que se inscriban:</p>

    <div class="url-box" id="urlBox">
      https://cancha-web.up.railway.app/pages/registro_socio.php?club=<?= htmlspecialchars($slug) ?>
    </div>

    <button class="btn-copy" onclick="copiarEnlace()">Copiar enlace</button>

    <a href="registro_socio.php?club=<?= htmlspecialchars($slug) ?>" class="btn-share">
      Ir a inscribirme
    </a>
  </div>

  <script>
    function copiarEnlace() {
      const url = document.getElementById('urlBox').innerText;
      navigator.clipboard.writeText(url).then(() => {
        alert('¡Enlace copiado!');
      });
    }
  </script>
  <script>
    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW registrado:', reg.scope))
          .catch(err => console.log('Error SW:', err));
      });
    }
    </script>
</body>
</html>