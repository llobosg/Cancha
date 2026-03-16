<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago Fallido | CanchaSport</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body { background: #f8f9fa; font-family: Arial, sans-serif; padding: 2rem; text-align: center; }
    .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .error { color: #dc3545; font-size: 3rem; margin-bottom: 1rem; }
    h1 { color: #071289; }
    .btn { display: inline-block; margin-top: 1.5rem; padding: 0.6rem 1.2rem; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="error">❌</div>
    <h1>El pago no pudo ser procesado</h1>
    <p>Por favor, inténtalo nuevamente con una tarjeta de prueba o contacta al administrador.</p>
    
    <div class="btn-group" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
      <a href="javascript:history.back()" class="btn" style="background: #6c757d; color: white; padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 6px; font-weight: bold;">Volver e intentar de nuevo</a>
      
      <?php
      if (!empty($_SESSION['current_club'])) {
          $dashboard_url = 'pages/dashboard_socio.php?id_club=' . htmlspecialchars($_SESSION['current_club']);
      } else {
          $dashboard_url = 'pages/dashboard_socio.php';
      }
      ?>
      <a href="<?= $dashboard_url ?>" class="btn" style="background: #071289; color: white; padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 6px; font-weight: bold;">Volver al Dashboard</a>
    </div>
  </div>
</body>
</html>