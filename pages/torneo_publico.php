<?php
require_once __DIR__ . '/../includes/config.php';

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
  <title>CanchaSport - <?= htmlspecialchars($torneo['nombre']) ?></title>
  <style>
  body {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: Arial, sans-serif;
    background: 
      linear-gradient(rgba(0, 20, 40, 0.85), rgba(0, 30, 60, 0.9)),
      url('/../uploads/logos/fondoamericano.png') center/cover no-repeat fixed;
    color: white;
  }
  .container {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    text-align: center;
    max-width: 520px;
    width: 90%;
  }
  .btn {
    background: #071289;
    color: white;
    border: none;
    padding: 0.9rem 1.8rem;
    border-radius: 10px;
    font-size: 1.15rem;
    cursor: pointer;
    margin-top: 1.5rem;
    transition: transform 0.2s, background 0.2s;
  }
  .btn:hover {
    background: #050d6b;
    transform: translateY(-2px);
  }
  input {
    display: block;
    width: 100%;
    padding: 0.75rem;
    margin: 0.6rem 0;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    background: rgba(255,255,255,0.9);
    color: #071289;
    font-size: 1rem;
  }
  h1 {
    margin: 0 0 1.2rem 0;
    font-size: 1.8rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
  }
</style>
</head>
<body>
  <div class="container">
    <h1>🏆 <?= htmlspecialchars($torneo['nombre']) ?></h1>
    <p>¡Únete a este Americano!</p>
    <p> 🎾 </p>
    <p>Organiza: Pasco Club</p>
    <p>Auspiciado por Pallap</p>
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