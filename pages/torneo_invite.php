<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

$code = $_GET['code'] ?? '';
if (!$code || strlen($code) !== 8) {
    http_response_code(400);
    die('Código de invitación no válido');
}

// Verificar que la invitación existe y está disponible
$stmt = $pdo->prepare("
    SELECT 
        pt.id_pareja,
        pt.id_torneo,
        t.nombre AS torneo_nombre,
        t.slug,
        t.valor,
        COALESCE(s1.alias, jt1.nombre) AS nombre_invitador
    FROM parejas_torneo pt
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    WHERE pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
");
$stmt->execute([$code]);
$invitacion = $stmt->fetch();

if (!$invitacion) {
    http_response_code(404);
    die('Invitación no válida o ya usada');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>🤝 Únete a la pareja - <?= htmlspecialchars($invitacion['torneo_nombre']) ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      background: 
      linear-gradient(rgba(0, 20, 40, 0.85), rgba(0, 30, 60, 0.9)),
      url('/../uploads/logos/fondoamericano.png') center/cover no-repeat fixed;
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
    .form-group {
      margin: 1rem 0;
    }
    input {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🤝 ¡Te invitaron a jugar!</h1>
    <p><strong><?= htmlspecialchars($invitacion['nombre_invitador']) ?></strong> te invitó a participar en:</p>
    <h3>🎾 <?= htmlspecialchars($invitacion['torneo_nombre']) ?></h3>
    <p>Organiza: PASCO CLUB</p>
    <p>Auspicia: PALLAP</p>
    <?php if (!empty($invitacion['valor'])): ?>
        <p><strong>Valor:</strong> $<?= number_format((int)$invitacion['valor'], 0, ',', '.') ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['id_socio'])): ?>
      <p>✅ Estás logueado como socio.</p>
      <button class="btn" onclick="aceptarInvitacion()">Aceptar y unirme</button>
    <?php else: ?>
      <p>Completa tus datos para unirte:</p>
      <form id="registroForm">
        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
        <div class="form-group">
          <input type="text" name="nombre" placeholder="Tu nombre completo" required>
        </div>
        <div class="form-group">
          <input type="email" name="email" placeholder="Tu email" required>
        </div>
        <button class="btn" type="submit">Unirme al torneo</button>
        <p style="color: #071289;">Recibirás un correo de confirmación una vez que te inscribas.</p>
      </form>
    <?php endif; ?>
  </div>

  <script>
    function aceptarInvitacion() {
      fetch('../api/completar_pareja.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({code: '<?= $code ?>'})
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('✅ ¡Pareja formada!');
          window.location.href = '../';
        } else {
          alert('❌ ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Error de conexión');
      });
    }

    document.getElementById('registroForm')?.addEventListener('submit', e => {
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch('../api/completar_pareja_temporal.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('✅ ' + data.message);
          window.location.href = '../';
        } else {
          alert('❌ ' + data.message);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('❌ Error de conexión');
      });
    });
  </script>
</body>
</html>