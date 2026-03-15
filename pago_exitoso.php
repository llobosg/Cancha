<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$id_cuota = (int)($_GET['id_cuota'] ?? 0);
if ($id_cuota > 0) {
    // Mostrar mensaje personalizado
    $stmt = $pdo->prepare("
        SELECT c.monto, COALESCE(r.fecha, e.fecha) as fecha_evento
        FROM cuotas c
        LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
        LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
        WHERE c.id_cuota = ? AND c.id_socio = ?
    ");
    $stmt->execute([$id_cuota, $_SESSION['id_socio'] ?? 0]);
    $cuota = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago Exitoso | CanchaSport</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body { background: #f8f9fa; font-family: Arial, sans-serif; padding: 2rem; text-align: center; }
    .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-size: 3rem; margin-bottom: 1rem; }
    h1 { color: #071289; }
    .btn { display: inline-block; margin-top: 1.5rem; padding: 0.6rem 1.2rem; background: #071289; color: white; text-decoration: none; border-radius: 6px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="success">✅</div>
    <h1>¡Pago realizado con éxito!</h1>
    <?php if ($cuota): ?>
      <p>Tu cuota de <strong>$<?= number_format($cuota['monto'], 0, ',', '.') ?></strong> ha sido registrada.</p>
      <p>Gracias por confiar en CanchaSport.</p>
    <?php endif; ?>
    <a href="pages/dashboard_socio.php" class="btn">Volver al Dashboard</a>
  </div>
</body>
</html>