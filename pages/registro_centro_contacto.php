<?php
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nombre_recinto = trim($_POST['nombre_recinto'] ?? '');

    if (empty($nombre) || empty($telefono) || empty($email) || empty($nombre_recinto)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } else {
        // Enviar correo a CanchaSport
        try {
            // Generar token único
            $token = bin2hex(random_bytes(32));

            // Guardar en base de datos
            $stmt = $pdo->prepare("
                INSERT INTO invitaciones_recintos 
                (nombre_completo, telefono, email, nombre_recinto, token) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $telefono, $email, $nombre_recinto, $token]);

            // Enviar notificación a CanchaSport
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo('luis.lobos.g@gmail.com', 'CanchaSport');
            $mail->setSubject('Nueva solicitud de Centro Deportivo - ' . $nombre_recinto);
            $mail->setHtmlBody("
                <h2>Nueva solicitud de registro</h2>
                <p><strong>Nombre:</strong> $nombre</p>
                <p><strong>Teléfono:</strong> $telefono</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Recinto:</strong> $nombre_recinto</p>
                <p><strong>Token:</strong> <code>$token</code></p>
                <p>Para habilitar el registro, envía este enlace al interesado:</p>
                <p><a href='https://canchasport.com/pages/registro_recinto.php?invitacion=$token'>https://canchasport.com/pages/registro_recinto.php?invitacion=$token</a></p>
            ");

            if ($mail->send()) {
                $success = true;
            } else {
                $error = 'Error al enviar notificación';
            }
        } catch (Exception $e) {
            error_log("Error envío centro deportivo: " . $e->getMessage());
            $error = 'Error interno al procesar la solicitud';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registro Centros Deportivos - CanchaSport ⚽🎾🏐</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: 
                linear-gradient(rgba(0, 20, 10, 0.40), rgba(0, 30, 15, 0.50)),
                url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            background-blend-mode: multiply;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            color: white;
        }

        .form-container {
            width: 95%;
            max-width: 600px;
            background: white;
            padding: 2rem;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            margin: 2rem auto;
        }

        .form-container h2 {
            text-align: center;
            color: #003366;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            line-height: 1.5;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            color: #071289;
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: #071289;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #050d66;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>🏟️ Registro Centros Deportivos</h2>
        
        <?php if ($success): ?>
            <div class="success">
                Tu correo se ha enviado a CanchaSport y en unos minutos más te devolveremos el llamado.<br>
                Si por algún motivo quieres apurar este registro, favor nos puedes llamar al celular <strong>+569 3656 0392</strong>.<br>
                Gracias por tu confianza. ⚽🎾🏐
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="../index.php" style="color: #071289; text-decoration: underline;">Volver al inicio</a>
            </div>
        <?php else: ?>
            <div class="welcome-text">
                Gracias por confiar en CanchaSport para registrar tu Centro Deportivo,<br>
                favor compártenos tus datos y te llamaremos en los próximos minutos.
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono *</label>
                    <input type="tel" id="telefono" name="telefono" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Correo *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="nombre_recinto">Nombre del recinto deportivo *</label>
                    <input type="text" id="nombre_recinto" name="nombre_recinto" required>
                </div>
                
                <button type="submit" class="btn-submit">Enviar datos para contacto ⚽🎾🏐</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>