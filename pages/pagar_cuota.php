<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_GET['id_cuota'])) {
    header('Location: ../index.php');
    exit;
}

$id_cuota = (int)$_GET['id_cuota'];
$id_socio = $_SESSION['id_socio'];

// === Paso 1: Obtener tipo_actividad ===
$stmt_check = $pdo->prepare("SELECT tipo_actividad FROM cuotas WHERE id_cuota = ? AND id_socio = ?");
$stmt_check->execute([$id_cuota, $id_socio]);
$tipo_actividad = $stmt_check->fetchColumn();

if (!$tipo_actividad) {
    die('<h2 style="color:white;text-align:center;margin-top:50px;">Cuota no encontrada</h2>');
}

// === Paso 2: Ejecutar consulta específica ===
if ($tipo_actividad === 'reserva') {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_cuota,
            c.monto,
            c.fecha_vencimiento,
            c.estado,
            s.nombre AS socio_nombre,
            s.email AS socio_email,
            cl.nombre AS club_nombre,
            cl.email_responsable,
            rd.nombre AS detalle_origen,
            r.fecha AS fecha_origen
        FROM cuotas c
        INNER JOIN socios s ON c.id_socio = s.id_socio
        INNER JOIN clubs cl ON s.id_club = cl.id_club
        INNER JOIN reservas r ON c.id_evento = r.id_reserva
        INNER JOIN canchas ca ON r.id_cancha = ca.id_cancha
        INNER JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
        WHERE c.id_cuota = ? AND c.id_socio = ?
        LIMIT 1
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_cuota,
            c.monto,
            c.fecha_vencimiento,
            c.estado,
            s.nombre AS socio_nombre,
            s.email AS socio_email,
            cl.nombre AS club_nombre,
            cl.email_responsable,
            te.tipoevento AS detalle_origen,
            e.fecha AS fecha_origen
        FROM cuotas c
        INNER JOIN socios s ON c.id_socio = s.id_socio
        INNER JOIN clubs cl ON s.id_club = cl.id_club
        INNER JOIN eventos e ON c.id_evento = e.id_evento
        INNER JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
        WHERE c.id_cuota = ? AND c.id_socio = ?
        LIMIT 1
    ");
}

$stmt->execute([$id_cuota, $id_socio]);
$cuota = $stmt->fetch();

if (!$cuota) {
    die('<h2 style="color:white;text-align:center;margin-top:50px;">Cuota no encontrada</h2>');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_pago = $_POST['fecha_pago'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    $adjunto = null;

    if (empty($fecha_pago)) {
        $error = 'La fecha de pago es obligatoria';
    } else {
        // Subir adjunto si existe
        if (!empty($_FILES['adjunto']['name'])) {
            $target_dir = __DIR__ . '/../uploads/comprobantes/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            $file_name = 'comprobante_' . $id_cuota . '_' . time() . '_' . basename($_FILES['adjunto']['name']);
            $target_file = $target_dir . $file_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_types)) {
                $error = 'Solo se permiten JPG, PNG o PDF';
            } elseif (!move_uploaded_file($_FILES['adjunto']['tmp_name'], $target_file)) {
                $error = 'Error al subir el comprobante';
            } else {
                $adjunto = $file_name;
            }
        }

        if (!$error) {
            // Actualizar cuota
            $stmt_update = $pdo->prepare("
                UPDATE cuotas 
                SET fecha_pago = ?, comentario = ?, adjunto = ?, estado = 'en_revision'
                WHERE id_cuota = ?
            ");
            $stmt_update->execute([$fecha_pago, $comentario, $adjunto, $id_cuota]);

            // Enviar correo al socio
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo($cuota['socio_email'], $cuota['socio_nombre']);
            $mail->setSubject('✅ Pago registrado - ' . $cuota['club_nombre']);
            $mail->setHtmlBody("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                    <div style='text-align: center; background: #2ECC71; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2>✅ Pago Registrado</h2>
                    </div>
                    <p>¡Hola {$cuota['socio_nombre']}!</p>
                    <p>Tu pago ha sido registrado y está en revisión:</p>
                    <p>
                        <strong>Detalle:</strong> {$cuota['detalle_origen']}<br>
                        <strong>Fecha:</strong> " . date('d/m/Y', strtotime($cuota['fecha_origen'])) . "<br>
                        <strong>Monto:</strong> $" . number_format($cuota['monto'], 0, ',', '.') . "<br>
                        <strong>Fecha de pago:</strong> " . date('d/m/Y', strtotime($fecha_pago)) . "
                    </p>
                    <p>El responsable del club revisará tu comprobante y confirmará el pago.</p>
                </div>
            ");
            $mail->send();

            // Enviar copia al responsable
            if ($cuota['email_responsable']) {
                $mail2 = new BrevoMailer();
                $mail2->setTo($cuota['email_responsable'], 'Responsable ' . $cuota['club_nombre']);
                $mail2->setSubject('📋 Nuevo pago en revisión - ' . $cuota['socio_nombre']);
                $mail2->setHtmlBody("
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                        <div style='text-align: center; background: #3498DB; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                            <h2>📋 Pago en Revisión</h2>
                        </div>
                        <p><strong>{$cuota['socio_nombre']}</strong> ha registrado un pago:</p>
                        <p>
                            <strong>Club:</strong> {$cuota['club_nombre']}<br>
                            <strong>Detalle:</strong> {$cuota['detalle_origen']}<br>
                            <strong>Monto:</strong> $" . number_format($cuota['monto'], 0, ',', '.') . "<br>
                            <strong>Fecha pago:</strong> " . date('d/m/Y', strtotime($fecha_pago)) . "
                        </p>
                        <p>Revisa el comprobante en el dashboard y confirma el pago.</p>
                    </div>
                ");
                $mail2->send();
            }

            $success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pagar Cuota - <?= htmlspecialchars($cuota['club_nombre']) ?></title>
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
      max-width: 500px;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    }

    .form-title {
      color: #FFD700;
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.5rem;
    }

    .error { background: #ffebee; color: #c62828; padding: 0.7rem; border-radius: 6px; margin-bottom: 1.5rem; text-align: center; font-size: 0.85rem; }
    .success { background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; text-align: center; font-size: 0.9rem; }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: white;
      margin-bottom: 0.5rem;
      text-align: left;
    }

    .form-group input, .form-group textarea {
      width: 100%;
      padding: 0.9rem;
      border: 2px solid #ccc;
      border-radius: 8px;
      color: #071289;
      font-size: 1rem;
      background: white;
    }

    .readonly {
      background: #eee;
      cursor: not-allowed;
    }

    .btn-submit {
      width: 100%;
      padding: 1rem;
      background: #071289;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }

    .close-btn {
      display: block;
      text-align: center;
      margin-top: 1rem;
      color: #FFD700;
      text-decoration: underline;
      font-size: 0.9rem;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2 class="form-title">💳 Pagar Cuota</h2>
    
    <?php if ($success): ?>
      <div class="success">
        ¡Pago registrado! Se ha enviado una confirmación a tu correo.<br>
        El responsable del club revisará tu comprobante.
      </div>
      <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="close-btn">Volver al dashboard</a>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <!-- Datos no editables -->
        <div class="form-group">
          <label>Detalle</label>
          <input type="text" value="<?= htmlspecialchars($cuota['detalle_origen']) ?>" class="readonly" readonly>
        </div>
        
        <div class="form-group">
          <label>Fecha</label>
          <input type="text" value="<?= date('d/m/Y', strtotime($cuota['fecha_origen'])) ?>" class="readonly" readonly>
        </div>
        
        <div class="form-group">
          <label>Monto</label>
          <input type="text" value="$<?= number_format($cuota['monto'], 0, ',', '.') ?>" class="readonly" readonly>
        </div>

        <!-- Campos editables -->
        <div class="form-group">
          <label for="fecha_pago">Fecha de pago *</label>
          <input type="date" id="fecha_pago" name="fecha_pago" required>
        </div>
        
        <div class="form-group">
          <label for="adjunto">Comprobante (opcional)</label>
          <input type="file" id="adjunto" name="adjunto" accept=".jpg,.jpeg,.png,.pdf">
        </div>
        
        <div class="form-group">
          <label for="comentario">Comentario (opcional)</label>
          <textarea id="comentario" name="comentario" rows="2"></textarea>
        </div>
        
        <button type="submit" class="btn-submit">Registrar Pago</button>
      </form>
      
      <a href="javascript:history.back()" class="close-btn">Cancelar</a>
    <?php endif; ?>
  </div>
</body>
</html>