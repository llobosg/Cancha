<!-- pages/index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cancha - Gestión para clubes deportivos</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003366">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>
  <div class="hero">
    <h1>Cancha ⚽</h1>
    <p class="subtitle">Tu club a un click, deja el WhatsApp para memes y familia</p>
    <div class="cta-buttons">
      <a href="pages/registro_club.php">Registra tu club</a>
      <a href="pages/buscar_club.php">Inscríbete en un club</a>
    </div>
  </div>
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