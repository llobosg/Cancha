<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

$slug = $_GET['slug'] ?? '';
$code = $_GET['code'] ?? '';

if (!$slug || strlen($slug) !== 8 || !$code || strlen($code) !== 8) {
    http_response_code(400);
    die('Parámetros inválidos');
}

// Verificar que el torneo y la invitación existen
$stmt = $pdo->prepare("
    SELECT t.nombre, pt.codigo_pareja
    FROM torneos t
    JOIN parejas_torneo pt ON t.id_torneo = pt.id_torneo
    WHERE t.slug = ? AND pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
");
$stmt->execute([$slug, $code]);
$registro = $stmt->fetch();

if (!$registro) {
    http_response_code(404);
    die('Invitación no válida o ya usada');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title style="color: #071289;">🤝 Invita a tu pareja - <?= htmlspecialchars($registro['nombre']) ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #bc7dcf;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      background: 
      linear-gradient(rgba(0, 20, 40, 0.85), rgba(0, 30, 60, 0.9)),
      url('/../uploads/logos/fondoamericano.png') center/cover no-repeat fixed;
    color: white;
    }
    .container {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 500px;
    }
    .qr-code {
      width: 180px;
      height: 180px;
      background: white;
      margin: 1.5rem auto;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    .share-link {
      background: #d473f1;
      padding: 0.8rem;
      border-radius: 6px;
      margin: 1rem 0;
      word-break: break-all;
      font-family: monospace;
      font-size: 0.9rem;
    }
    .btn {
      background: #071289;
      color: white;
      border: none;
      padding: 0.8rem 1.5rem;
      border-radius: 8px;
      font-size: 1.1rem;
      cursor: pointer;
      margin-top: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 style="color: #071289;">🤝 ¡Casi listos!</h1>
    <p style="color: #071289;">Comparte este código o QR con tu pareja para completar la inscripción:</p>
    
    <div style="font-size: 1.8rem; font-weight: bold; margin: 1rem 0; color: #071289;">
      <?= htmlspecialchars($code) ?>
    </div>

    <div class="qr-code" id="qrcode"></div>

    <p style="color: #071289;">O envía este enlace:</p>
    <div class="share-link" id="shareLink">
      https://canchasport.com/pages/torneo_invite.php?code=<?= urlencode($code) ?>
    </div>
    <p style="color: #071289;">Recibirás un correo de confirmación una vez que tu pareja se una al torneo.</p>

    <button class="btn" onclick="copiarEnlace()">📋 Copiar enlace</button>
    <button class="btn" style="background: #6c757d;margin-top:0.5rem;" onclick="window.location.href='/'">Volver al inicio</button>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    const link = 'https://canchasport.com/pages/torneo_invite.php?code=<?= urlencode($code) ?>';
    new QRCode(document.getElementById("qrcode"), {
      text: link,
      width: 160,
      height: 160,
      colorDark: "#071289",
      colorLight: "#ffffff"
    });

    function copiarEnlace() {
      navigator.clipboard.writeText(link).then(() => {
        alert('✅ Enlace copiado al portapapeles');
      });
    }
  </script>
</body>
</html>