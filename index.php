<!-- pages/index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cancha - Gestión para clubes deportivos</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('../assets/img/fondo-futbol.jpg') center/cover no-repeat fixed;
      color: white;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 2rem;
    }
    .hero {
      max-width: 800px;
      padding: 2rem;
    }
    h1 {
      font-size: 3rem;
      margin-bottom: 1rem;
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    .subtitle {
      font-size: 1.2rem;
      margin-bottom: 2.5rem;
      opacity: 0.9;
    }
    .cta-buttons {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      justify-content: center;
    }
    .btn {
      padding: 0.9rem 2rem;
      border: none;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.3);
    }
    .btn-primary {
      background: #00cc66;
      color: white;
    }
    .btn-secondary {
      background: #3a4f63;
      color: white;
    }
  </style>
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
</body>
</html>