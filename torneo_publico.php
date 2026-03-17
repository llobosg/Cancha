<?php
require_once __DIR__ . '/includes/config.php';

$slug = $_GET['slug'] ?? '';
if (!$slug || strlen($slug) !== 8) {
    http_response_code(400);
    die('Torneo no válido');
}

// Verificar que el torneo existe y es público
$stmt = $pdo->prepare("SELECT id_torneo, nombre FROM torneos WHERE slug = ? AND publico = 1 AND estado IN ('abierto', 'cerrado')");
$stmt->execute([$slug]);
$torneo = $stmt->fetch();

if (!$torneo) {
    http_response_code(404);
    die('Torneo no encontrado o no público');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($torneo['nombre']) ?> - CanchaSport</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 500px;
    }
    .btn {
      background: #071289;
      color: white;
      border: none;
      padding: 0.8rem 1.5rem;
      border-radius: 8px;
      font-size: 1.1rem;
      cursor: pointer;
      margin-top: 1.5rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🏆 <?= htmlspecialchars($torneo['nombre']) ?></h1>
    <p>¡Únete a este torneo americano!</p>
    <?php if (isset($_SESSION['id_socio'])): ?>
      <button class="btn" onclick="inscribirme()">Inscribirme al torneo</button>
    <?php else: ?>
      <form id="registroForm">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
        <input type="text" name="nombre" placeholder="Tu nombre completo" required style="display:block;width:100%;padding:0.6rem;margin:0.5rem 0;border:1px solid #ccc;border-radius:4px;">
        <input type="email" name="email" placeholder="Tu email" required style="display:block;width:100%;padding:0.6rem;margin:0.5rem 0;border:1px solid #ccc;border-radius:4px;">
        <button class="btn" type="submit">Inscribirme</button>
      </form>
    <?php endif; ?>
  </div>

  <script>
    function inscribirme() {
      fetch('api/inscribir_al_torneo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({slug: '<?= $slug ?>'})
      })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          alert('Error: ' + (data.message || 'No se pudo inscribir'));
        }
      });
    }

    document.getElementById('registroForm')?.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch('api/inscribir_al_torneo.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          alert('Error: ' + (data.message || 'No se pudo inscribir'));
        }
      });
    });
  </script>
</body>
</html>