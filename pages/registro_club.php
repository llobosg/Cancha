<!-- pages/registro_club.php -->
<?php
require_once __DIR__ . '/../includes/config.php';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registrar Club - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    body {
      background: linear-gradient(135deg, #e0f7fa, #bbdefb);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 2rem;
    }
    .form-container {
      max-width: 600px;
      margin: 2rem auto;
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #3a4f63;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1.2rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: 600;
      color: #444;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.7rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }
    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #009966;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
    }
    .btn-submit:hover {
      background: #007a52;
    }
    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Registra tu club</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="../api/enviar_codigo_club.php" enctype="multipart/form-data">
      <div class="form-group">
        <label for="nombre">Nombre del club *</label>
        <input type="text" id="nombre" name="nombre" required>
      </div>

      <div class="form-group">
        <label for="deporte">Deporte *</label>
        <select id="deporte" name="deporte" required>
          <option value="">Seleccionar</option>
          <option value="futbol">Fútbol</option>
          <option value="futbolito">Futbolito</option>
          <option value="futsal">Futsal</option>
          <option value="futbol11">Fútbol 11</option>
          <option value="tenis">Tenis</option>
          <option value="padel">Pádel</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="form-group">
        <label for="ciudad">Ciudad *</label>
        <input type="text" id="ciudad" name="ciudad" required>
      </div>

      <div class="form-group">
        <label for="comuna">Comuna *</label>
        <input type="text" id="comuna" name="comuna" required>
      </div>

      <div class="form-group">
        <label for="responsable">Nombre del responsable *</label>
        <input type="text" id="responsable" name="responsable" required>
      </div>

      <div class="form-group">
        <label for="email_responsable">Correo del responsable *</label>
        <input type="email" id="email_responsable" name="email_responsable" required>
      </div>

      <div class="form-group">
        <label for="logo">Logo del club (opcional)</label>
        <input type="file" id="logo" name="logo" accept="image/*">
      </div>

      <button type="submit" class="btn-submit">Enviar código de verificación</button>
    </form>
  </div>

  <script>
    // Cambiar fondo según deporte
    document.getElementById('deporte').addEventListener('change', function() {
      const deportes = {
        'futbol': 'fondo-futbol.jpg',
        'futbolito': 'fondo-futbol.jpg',
        'futsal': 'fondo-futsal.jpg',
        'futbol11': 'fondo-futbol11.jpg',
        'tenis': 'fondo-tenis.jpg',
        'padel': 'fondo-padel.jpg',
        'otro': 'fondo-generico.jpg'
      };
      const img = deportes[this.value] || 'fondo-futbol.jpg';
      document.body.style.backgroundImage = `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('../assets/img/${img}')`;
    });
  </script>
</body>
</html>