<!-- pages/verificar_club.php -->
<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$id_club = $_GET['id'] ?? null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    $id_club = $_POST['id_club'] ?? '';

    if (!$codigo || !$id_club) {
        $error = 'Datos incompletos';
    } else {
        // Verificar código y que no haya expirado (10 min)
        $stmt = $pdo->prepare("
            SELECT * FROM clubs 
            WHERE id_club = ? 
            AND verification_code = ? 
            AND created_at > NOW() - INTERVAL 10 MINUTE
        ");
        $stmt->execute([$id_club, $codigo]);
        $club = $stmt->fetch();

        if ($club) {
            // Confirmar registro
            $pdo->prepare("
                UPDATE clubs 
                SET email_verified = 1, verification_code = NULL 
                WHERE id_club = ?
            ")->execute([$id_club]);

            // Crear slug único
            $slug = substr(md5($club['id_club'] . $club['email_responsable']), 0, 8);

            // Guardar slug en la base de datos (opcional, o generarlo dinámicamente)
            // Por ahora lo generamos on-the-fly

            header("Location: club_confirmado.php?slug=$slug");
            exit;
        } else {
            $error = 'Código incorrecto o ha expirado';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verificar Club - Cancha</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    body {
      background: #f5f7fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 2rem;
    }
    .form-container {
      max-width: 500px;
      margin: 3rem auto;
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
      margin-bottom: 0.5rem;
      font-weight: 600;
    }
    .form-group input {
      width: 100%;
      padding: 0.8rem;
      font-size: 1.5rem;
      text-align: center;
      border: 2px solid #ccc;
      border-radius: 8px;
      letter-spacing: 8px;
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
    .error {
      background: #ffebee;
      color: #c62828;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
      text-align: center;
    }
    .info {
      background: #e3f2fd;
      color: #0d47a1;
      padding: 0.8rem;
      border-radius: 6px;
      margin-bottom: 1.2rem;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>🔐 Verifica tu club</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info">
      Ingresa el código de 4 dígitos que enviamos a tu correo.
    </div>

    <form method="POST">
      <input type="hidden" name="id_club" value="<?= htmlspecialchars($id_club) ?>">
      <div class="form-group">
        <label for="codigo">Código de verificación</label>
        <input type="text" id="codigo" name="codigo" maxlength="4" pattern="[0-9]{4}" required autofocus>
      </div>
      <button type="submit" class="btn-submit">Confirmar club</button>
    </form>
  </div>

  <script>
    // Solo números
    document.getElementById('codigo').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });
  </script>
</body>
</html>