<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/config_mercadopago.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_GET['id_cuota'])) {
    header('Location: ../index.php');
    exit;
}

$id_cuota = (int)$_GET['id_cuota'];
$id_socio = $_SESSION['id_socio'];

// Obtener datos completos de la cuota
$stmt = $pdo->prepare("
    SELECT 
        c.id_cuota,
        c.monto,
        c.estado,
        c.tipo_actividad,
        c.id_evento,
        s.nombre AS socio_nombre,
        s.email AS socio_email,
        s.id_club,
        cl.nombre AS club_nombre,
        cl.email_responsable,
        CASE
            WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
            WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
            ELSE 'Sin detalle'
        END as detalle_origen,
        COALESCE(r.fecha, e.fecha) AS fecha_origen
    FROM cuotas c
    INNER JOIN socios s ON c.id_socio = s.id_socio
    INNER JOIN clubs cl ON s.id_club = cl.id_club
    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
    LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
    WHERE c.id_cuota = ? AND c.id_socio = ?
    LIMIT 1
");
$stmt->execute([$id_cuota, $id_socio]);
$cuota = $stmt->fetch();

if (!$cuota || $cuota['estado'] !== 'pendiente') {
    die('<h2 style="color:white;text-align:center;margin-top:50px;">Cuota no encontrada o ya pagada</h2>');
}

$error = '';
$success = '';

// === Procesar pago manual (POST) ===
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
            // Actualizar cuota a "en_revision"
            $pdo->prepare("
                UPDATE cuotas 
                SET estado = 'en_revision', fecha_pago = ?, comentario = ?, adjunto = ?
                WHERE id_cuota = ?
            ")->execute([$fecha_pago, $comentario, $adjunto, $id_cuota]);

            // Si es reserva recaudatoria, sumar fondos
            if ($cuota['tipo_actividad'] === 'reserva') {
                $stmt_check = $pdo->prepare("
                    SELECT r.monto_recaudacion 
                    FROM reservas r 
                    WHERE r.id_reserva = ? AND r.monto_recaudacion IS NOT NULL
                ");
                $stmt_check->execute([$cuota['id_evento']]);
                if ($stmt_check->fetch()) {
                    $pdo->prepare("
                        UPDATE clubs 
                        SET fondos_acumulados = COALESCE(fondos_acumulados, 0) + ?
                        WHERE id_club = ?
                    ")->execute([$cuota['monto'], $cuota['id_club']]);
                }
            }

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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagar Cuota - <?= htmlspecialchars($cuota['club_nombre']) ?></title>
  <link rel="stylesheet" href="../styles.css">
  <!-- SDK de Mercado Pago -->
  <script src="https://sdk.mercadopago.com/js/v2"></script>
  <style>
    body {
      background: linear-gradient(rgba(0,20,10,.65), rgba(0,30,15,.75)),
                  url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
      color: white;
      font-family: 'Segoe UI', sans-serif;
      padding: 2rem 1rem;
      margin: 0;
    }
    .container {
      max-width: 500px;
      margin: 0 auto;
      background: rgba(0, 51, 102, 0.85);
      padding: 2rem;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
      text-align: center;
    }
    h1 {
      color: #FFD700;
      margin-bottom: 1.5rem;
    }
    .info {
      background: rgba(255,255,255,0.15);
      padding: 1.2rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      text-align: left;
    }
    .divider {
      text-align: center;
      margin: 1.2rem 0;
      color: #aaa;
    }
    .btn-action {
      display: block;
      width: 100%;
      padding: 0.8rem;
      background: #E74C3C;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: bold;
      cursor: pointer;
      margin-top: 1rem;
    }
    .btn-action:hover {
      background: #c0392b;
    }
    .readonly {
      background: #eee;
      color: #071289;
      padding: 0.6rem;
      border-radius: 6px;
      margin-bottom: 1rem;
      text-align: left;
    }
    .back-link {
      display: block;
      margin-top: 1rem;
      color: #FFD700;
      text-decoration: none;
    }
    #bricks_container {
      margin: 1.5rem 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>💳 Pagar Cuota</h1>
    
    <div class="info">
      <div class="readonly"><strong>Detalle:</strong> <?= htmlspecialchars($cuota['detalle_origen']) ?></div>
      <div class="readonly"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($cuota['fecha_origen'])) ?></div>
      <div class="readonly"><strong>Monto:</strong> $<?= number_format($cuota['monto'], 0, ',', '.') ?></div>
    </div>

    <!-- === PAGO CON MERCADO PAGO BRICKS === -->
    <div id="bricks_container"></div>

    <div class="divider">— o —</div>

    <!-- === PAGO MANUAL (COMPROBANTE) === -->
    <form method="POST" enctype="multipart/form-data">
      <!-- Toast container -->
      <div id="toast-container" style="position:fixed;bottom:20px;right:20px;z-index:1000;"></div>
      <input type="hidden" name="fecha_pago" value="<?= date('Y-m-d') ?>">
      <label style="display:block;margin-bottom:0.5rem;color:white;">Comprobante (opcional)</label>
      <input type="file" name="adjunto" accept=".jpg,.jpeg,.png,.pdf" style="margin-bottom:1rem;">
      <label style="display:block;margin-bottom:0.5rem;color:white;">Comentario (opcional)</label>
      <textarea name="comentario" rows="2" style="width:100%;padding:0.6rem;border-radius:6px;"></textarea>
      <button type="submit" class="btn-action" style="background:#071289;">Registrar Pago Manual</button>
    </form>

    <a href="dashboard_socio.php?id_club=<?= htmlspecialchars($_SESSION['current_club'] ?? '') ?>" class="back-link">← Volver al Dashboard</a>
  </div>

  <script>
    // === INICIALIZAR BRICKS ===
    const mp = new MercadoPago('<?= MERCADOPAGO_ACCESS_TOKEN ?>');
    const bricksBuilder = mp.bricks();

    bricksBuilder.create('cardPayment', 'bricks_container', {
      initialization: {
        amount: <?= $cuota['monto'] ?>,
        payer: {
          email: '<?= $_SESSION['user_email'] ?>'
        }
      },
      onSubmit: async (formData) => {
        try {
          const response = await fetch('../api/procesar_pago_brick.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              ...formData,
              id_cuota: <?= $cuota['id_cuota'] ?>,
              description: 'Cuota CanchaSport - <?= addslashes($cuota['detalle_origen']) ?>'
            })
          });
          const data = await response.json();
          if (data.status === 'approved') {
            alert('✅ Pago aprobado');
            window.location.href = '../pago_exitoso.php?id_cuota=<?= $cuota['id_cuota'] ?>';
          } else {
            alert('❌ Pago rechazado: ' + (data.message || 'Error desconocido'));
          }
        } catch (err) {
          console.error(err);
          alert('❌ Error al procesar el pago');
        }
      },
      onError: (error) => {
        console.error('Error en Brick:', error);
        alert('❌ Error en el formulario de pago');
      },
      customization: {
        visual: {
          style: {
            theme: 'default',
            texts: {
              formTitle: 'Paga con tu tarjeta',
              submit: 'Pagar ahora'
            }
          }
        }
      }
    });

    function mostrarToast(mensaje) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:1000;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.textContent = mensaje;
        toast.style.cssText = `
            background: #28a745; color: white; padding: 12px 16px;
            border-radius: 8px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s;
        `;
        container.appendChild(toast);
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 3000);
    }
  </script>
</body>
</html>