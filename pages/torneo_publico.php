<?php
require_once __DIR__ . '/../includes/config.php';
session_start(); 

$slug = $_GET['slug'] ?? '';
if (!$slug || strlen($slug) !== 8) {
    http_response_code(400);
    die('Torneo no válido');
}

$stmt = $pdo->prepare("
    SELECT 
        id_torneo, 
        nombre, 
        valor,
        estado,
        num_parejas_max
    FROM torneos 
    WHERE slug = ? AND publico = 1
");
$stmt->execute([$slug]);
$torneo = $stmt->fetch();

if (!$torneo) {
    http_response_code(404);
    die('Torneo no encontrado');
}

// Verificar si el torneo está cerrado o lleno
$torneo_cerrado = false;
$mensaje_amigable = '';

if ($torneo['estado'] !== 'abierto') {
    $torneo_cerrado = true;
    $mensaje_amigable = 'Las inscripciones para este torneo ya han finalizado.';
} else {
    // Contar parejas inscritas
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) 
        FROM parejas_torneo 
        WHERE id_torneo = ? AND estado = 'completa'
    ");
    $stmt_count->execute([$torneo['id_torneo']]);
    $inscritos = (int)$stmt_count->fetchColumn();
    
    if ($inscritos >= $torneo['num_parejas_max']) {
        $torneo_cerrado = true;
        $mensaje_amigable = '¡Este torneo ya alcanzó el límite de parejas inscritas!';
    }
}

if ($torneo_cerrado) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>🔒 Inscripciones cerradas</title>
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
        <h2>🎾🎾🎾🎾🎾🎾</h2>
        <h2>🔒 ¡Inscripciones cerradas!</h2>
        <p><?= htmlspecialchars($mensaje_amigable) ?></p>
        <p>¡Pero no te preocupes! Regístrate en canchasport.com y sé el primero en enterarte de nuevos torneos y eventos.</p>
        <a href="/pages/registro_socio.php?modo=individual" class="btn">👉 Únete ahora 🎾</a>
      </div>
    </body>
    </html>
    <?php
    exit;
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
    <p><strong>💰 Valor:</strong> $<?= number_format($torneo['valor'], 0, ',', '.') ?></p>
    <p>Auspiciado por PALLAP</p>
    <p>Estadísticas canchasport.com</p>

    <?php if (isset($_SESSION['id_socio'])): ?>
        <button class="btn" onclick="inscribirme()">Inscribirme al torneo</button>
    <?php else: ?>
        <form id="registroForm">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
            <input type="text" name="nombre" placeholder="Tu nombre completo" required 
                   style="display:block;width:100%;padding:0.6rem;margin:0.3rem 0;border:1px solid #ccc;border-radius:4px;background:rgba(255,255,255,0.9);color:#071289;">
            <input type="email" name="email" placeholder="Tu email" required 
                   style="display:block;width:100%;padding:0.6rem;margin:0.3rem 0;border:1px solid #ccc;border-radius:4px;background:rgba(255,255,255,0.9);color:#071289;">
            <button class="btn" type="submit">Inscribirme</button>
            <p style="color: #071289;">Recibirás un correo de confirmación una vez que te inscribas.</p>
        </form>
    <?php endif; ?>

    <!-- Sección de recuperación (siempre visible como enlace) -->
    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.3);">
        <p>
            ¿Ya te inscribiste pero perdiste el link de invitación? 
            <a href="#" id="toggleRecuperar" style="color:#FFD700;text-decoration:underline;">Recupéralo aquí</a>
        </p>

        <div id="recuperarFormContainer" style="display:none;margin-top:1rem;">
            <form id="recuperarForm">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <input type="text" name="nombre" placeholder="Tu nombre" 
                       style="display:block;width:100%;padding:0.6rem;margin:0.3rem 0;border:1px solid #ccc;border-radius:4px;background:rgba(255,255,255,0.9);color:#071289;">
                <input type="email" name="email" placeholder="Tu email" 
                       style="display:block;width:100%;padding:0.6rem;margin:0.3rem 0;border:1px solid #ccc;border-radius:4px;background:rgba(255,255,255,0.9);color:#071289;">
                <button type="submit" class="btn" style="background:#FFD700;color:#071289;margin-top:0.5rem;">Recuperar link</button>
            </form>
        </div>
    </div>
  </div>

  <script>
    function inscribirme() {
      fetch('../api/verificar_sesion.php', {
        credentials: 'include' // ← Incluir cookies
      })
      .then(r => r.json())
      .then(data => {
        if (data.socio) {
          fetch('../api/inscribir_al_torneo.php', {
            method: 'POST',
            credentials: 'include', // ← Incluir cookies
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
        } else {
          alert('⚠️ Tu sesión expiró. Por favor, inicia sesión nuevamente.');
          window.location.href = '../';
        }
      });
    }

    document.getElementById('toggleRecuperar')?.addEventListener('click', e => {
      e.preventDefault();
      const container = document.getElementById('recuperarFormContainer');
      container.style.display = container.style.display === 'none' ? 'block' : 'none';
    });

    document.getElementById('recuperarForm')?.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch('../api/recuperar_link_pareja.php', {
        method: 'POST',
        credentials: 'include', // ← Incluir cookies
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          alert('❌ ' + (data.message || 'No se encontró tu inscripción'));
        }
      });
    });

    document.getElementById('registroForm')?.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch('../api/inscribir_al_torneo.php', {
        method: 'POST',
        credentials: 'include', // ← Incluir cookies
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          alert('❌ ' + (data.message || 'No se pudo inscribir'));
        }
      });
    });
  </script>
</body>
</html>