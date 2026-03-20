<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Verificar que sea admin de recinto
if (!isset($_SESSION['id_recinto'])) {
    header('Location: ../index.php');
    exit;
}

// Obtener datos actuales
$stmt = $pdo->prepare("
    SELECT nombre_completo AS nombre, email, telefono, direccion 
    FROM admin_recintos 
    WHERE id_recinto = ?
");
$stmt->execute([$_SESSION['id_recinto']]);
$admin = $stmt->fetch();

if (!$admin) {
    die('❌ No se encontró el perfil de administrador.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>👤 Perfil Admin - CanchaSport</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: linear-gradient(rgba(0,20,40,0.85), rgba(0,30,60,0.9)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0; padding: 0;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      color: white;
    }
    .form-container {
      max-width: 600px;
      margin: 2rem auto;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }
    .form-container h2 {
      text-align: center;
      color: #071289;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1.2rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.4rem;
      font-weight: bold;
      color: #333;
    }
    .form-group input {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 1rem;
    }
    .btn-submit {
      width: 100%;
      padding: 0.7rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      margin-top: 1rem;
    }
    .btn-submit:hover {
      background: #050d6b;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 1.5rem;
      color: #FFD700;
      text-decoration: none;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>👤 Perfil Admin Recinto Deportivo</h2>

    <?php if (!empty($_GET['error'])): ?>
      <div style="background:#ffebee;color:#c62828;padding:0.8rem;border-radius:6px;margin-bottom:1.5rem;text-align:center;">
        <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <form id="perfilForm" method="POST" action="../api/guardar_perfil_admin_recinto.php">
      <input type="hidden" name="id_recinto" value="<?= $_SESSION['id_recinto'] ?>">

      <div class="form-group">
        <label for="nombre">Nombre Completo</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($admin['nombre']) ?>" required>
      </div>

      <div class="form-group">
        <label for="email">Correo Electrónico</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
      </div>

      <div class="form-group">
        <label for="telefono">Teléfono</label>
        <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($admin['telefono']) ?>">
      </div>

      <div class="form-group">
        <label for="direccion">Dirección del Recinto</label>
        <input type="text" id="direccion" name="direccion" value="<?= htmlspecialchars($admin['direccion']) ?>">
      </div>

      <button type="submit" class="btn-submit">Guardar Cambios</button>
    </form>

    <a href="recinto_dashboard.php" class="back-link">← Volver al Dashboard</a>
  </div>

  <script>
    document.getElementById('perfilForm').addEventListener('submit', function(e) {
      const email = this.email.value;
      if (!email.includes('@')) {
        alert('Por favor ingresa un correo válido');
        e.preventDefault();
      }
    });
  </script>
</body>
</html>