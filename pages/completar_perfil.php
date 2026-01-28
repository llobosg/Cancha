<!-- pages/completar_perfil.php -->
<?php
require_once __DIR__ . '/../includes/config.php';

$club_slug = $_GET['club'] ?? '';
if (!$club_slug) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos
        if (empty($_POST['telefono']) || empty($_POST['direccion'])) {
            throw new Exception('Teléfono y dirección son obligatorios');
        }

        // Aquí actualizarías la base de datos
        // $stmt = $pdo->prepare("UPDATE socios SET telefono = ?, direccion = ?, genero = ?, puesto = ?, datos_completos = 1 WHERE id_socio = ?");
        // $stmt->execute([$_POST['telefono'], $_POST['direccion'], $_POST['genero'], $_POST['puesto'], $_SESSION['id_socio']]);

        $success = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Completar Perfil - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    body {
      background: 
        linear-gradient(rgba(0, 20, 10, 0.65), rgba(0, 30, 15, 0.75)),
        url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      background-blend-mode: multiply;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
    }

    .form-container {
      width: 95%;
      max-width: 600px;
      background: white;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      position: relative;
      margin: 0 auto;
    }

    @media (max-width: 768px) {
      body {
        background: white !important;
        color: #333 !important;
      }
      
      .form-container {
        width: 100%;
        max-width: none;
        height: auto;
        min-height: 100vh;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        padding: 1.5rem;
        background: white !important;
      }
    }

    .close-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 2.2rem;
      color: #003366;
      text-decoration: none;
      opacity: 0.7;
      transition: opacity 0.2s;
      z-index: 10;
    }

    h2 {
      text-align: center;
      color: #003366;
      margin-bottom: 1.8rem;
      font-weight: 700;
      font-size: 1.6rem;
    }

    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .success {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 0.7rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
    }

    .form-group {
      margin-bottom: 1.2rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: #333;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.8rem;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
      color: #071289;
    }

    .btn-submit {
      width: 100%;
      padding: 0.9rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-submit:hover {
      background: #050d66;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #071289;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <a href="index.php" class="close-btn" title="Volver al inicio">×</a>

    <?php if ($success): ?>
      <h2>✅ ¡Perfil completado!</h2>
      <div class="success">
        Tu perfil ha sido actualizado correctamente. Ahora tienes acceso a todas las funcionalidades de Cancha.
      </div>
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>" class="btn-submit" style="text-decoration: none; text-align: center;">
        Ir al dashboard
      </a>
    <?php else: ?>
      <h2>📝 Completa tu perfil</h2>
      
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label for="telefono">Teléfono de contacto *</label>
          <input type="tel" id="telefono" name="telefono" required>
        </div>
        
        <div class="form-group">
          <label for="direccion">Dirección completa *</label>
          <textarea id="direccion" name="direccion" rows="3" required></textarea>
        </div>
        
        <div class="form-group">
          <label for="genero">Género</label>
          <select id="genero" name="genero">
            <option value="">Seleccionar</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="puesto">Puesto en el club</label>
          <input type="text" id="puesto" name="puesto" placeholder="Ej: Jugador, Entrenador, etc.">
        </div>
        
        <button type="submit" class="btn-submit">Guardar perfil</button>
      </form>
      
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($club_slug) ?>" class="back-link">
        ← Volver al dashboard
      </a>
    <?php endif; ?>
  </div>
</body>
</html>