<?php
require_once __DIR__ . '/../includes/config.php';

$id_socio = $_GET['id'] ?? null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    $id_socio = $_POST['id_socio'] ?? '';

    if (!$codigo || !$id_socio) {
        $error = 'Datos incompletos';
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id_socio, s.id_club, s.email_verified
            FROM socios s
            WHERE s.id_socio = ? 
            AND s.verification_code = ? 
            AND s.created_at > NOW() - INTERVAL 10 MINUTE
        ");
        $stmt->execute([$id_socio, $codigo]);
        $socio = $stmt->fetch();

        if ($socio && !$socio['email_verified']) {
            $pdo->prepare("
                UPDATE socios 
                SET email_verified = 1, verification_code = NULL 
                WHERE id_socio = ?
            ")->execute([$id_socio]);

            $stmt = $pdo->prepare("
                SELECT c.id_club, c.email_responsable 
                FROM clubs c 
                JOIN socios s ON c.id_club = s.id_club 
                WHERE s.id_socio = ?
            ");
            $stmt->execute([$id_socio]);
            $club_data = $stmt->fetch();

            if ($club_data) {
                $club_slug = substr(md5($club_data['id_club'] . $club_data['email_responsable']), 0, 8);
                header("Location: dashboard.php?id_club=" . $club_slug);
                exit;
            }
        }

        $error = 'Código incorrecto o ha expirado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verificar Socio - Cancha</title>
  <link rel="stylesheet" href="../styles.css">
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
    <h2>🔐 Verifica tu inscripción</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info">
      Ingresa el código de 4 dígitos que enviamos a tu correo.
    </div>

    <form method="POST">
      <input type="hidden" name="id_socio" value="<?= htmlspecialchars($id_socio) ?>">
      <div class="form-group">
        <label for="codigo">Código de verificación</label>
        <input type="text" id="codigo" name="codigo" maxlength="4" pattern="[0-9]{4}" required autofocus>
      </div>
      <button type="submit" class="btn-submit">Confirmar inscripción</button>
    </form>
  </div>

  <script>
    document.getElementById('codigo').addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });
  </script>
</body>
</html>