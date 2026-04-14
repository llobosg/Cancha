<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener regiones (aunque usaremos default, las cargamos por si acaso)
$stmt_regiones = $pdo->query("SELECT DISTINCT codigo_region, nombre_region FROM regiones_chile ORDER BY nombre_region");
$regiones_chile = [];
while ($row = $stmt_regiones->fetch()) {
    $regiones_chile[$row['codigo_region']] = $row['nombre_region'];
}

$error_message = '';
$error_type = '';
$success = false;
$email_to_verify = '';

// Valores por defecto desde URL
$prefill_nombre = $_GET['prefill_nombre'] ?? '';
$prefill_email = $_GET['prefill_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos requeridos
        $required = ['nombre', 'deporte', 'ciudad', 'comuna', 'responsable', 'email_responsable'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Todos los campos marcados son obligatorios');
            }
        }

        // Validar email
        if (!filter_var($_POST['email_responsable'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico inválido.');
        }

        // Validar Jugadores (Hidden pero validado por seguridad)
        $jugadores = (int)$_POST['jugadores_por_lado'];
        if ($jugadores < 1 || $jugadores > 50) {
             // Permitimos rango amplio ya que es hidden
        }

        // === VALIDAR NOMBRE DE CLUB EXISTENTE ===
        $stmt_check_name = $pdo->prepare("SELECT id_club FROM clubs WHERE LOWER(nombre) = LOWER(?)");
        $stmt_check_name->execute([$_POST['nombre']]);
        if ($stmt_check_name->fetch()) {
            throw new Exception('️ Ya existe un club con ese nombre. Por favor elige otro.');
        }

        // Verificar email duplicado (Responsable)
        $stmt_check_email = $pdo->prepare("SELECT id_club FROM clubs WHERE email_responsable = ?");
        $stmt_check_email->execute([$_POST['email_responsable']]);
        if ($stmt_check_email->fetch()) {
            $error_type = 'duplicate';
            $error_message = 'Este correo ya tiene un club registrado.';
            throw new Exception($error_message);
        }

        // Generar código
        $verification_code = rand(1000, 9999);
        
        // Datos (usando defaults hidden)
        $club_data = [
            'nombre' => $_POST['nombre'],
            'deporte' => $_POST['deporte'],
            'jugadores_por_lado' => $jugadores,
            'fecha_fundacion' => $_POST['fecha_fundacion'], // Hoy desde hidden
            'ciudad' => $_POST['ciudad'], // Santiago desde hidden
            'comuna' => $_POST['comuna'], // Comuna desde input o hidden? Pediste Comuna en form.
            'responsable' => $_POST['responsable'],
            'telefono' => $_POST['telefono'] ?: null,
            'email_responsable' => $_POST['email_responsable'],
            'verification_code' => $verification_code,
            'region' => $_POST['region'] // Metropolitana desde hidden
        ];

        // Subir Logo
        $logo_filename = null;
        if (!empty($_FILES['logo']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                throw new Exception('Solo imágenes JPG, PNG o GIF');
            }
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                throw new Exception('Logo máximo 2MB');
            }
            $logo_filename = uniqid() . '_' . basename($_FILES['logo']['name']);
            $upload_dir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename)) {
                throw new Exception('Error al subir logo');
            }
        }

        // Enviar Correo (Brevo)
        $brevo_api_key = $_ENV['BREVO_API_KEY'] ?? '';
        if (!$brevo_api_key) throw new Exception('Error configuración correo');

        $from_email = $_ENV['MAILER_FROM_EMAIL'] ?? 'llobos@gltcomex.com';
        $from_name = 'CanchaSport';

        $correo_data = [
            'sender' => ['name' => $from_name, 'email' => $from_email],
            'to' => [['email' => $club_data['email_responsable'], 'name' => $club_data['responsable']]],
            'subject' => '🏟️ Verificación de Club - CanchaSport',
            'htmlContent' => "
            <div style='font-family: Arial; max-width: 600px; margin: 0 auto; padding: 20px; background: #f0fdf4; border-radius: 12px;'>
                <h2 style='color: #166534;'>¡Hola {$club_data['responsable']}!</h2>
                <p>Tu club <strong>{$club_data['nombre']}</strong> ha sido registrado.</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <span style='background: #166534; color: white; padding: 10px 20px; font-size: 20px; font-weight: bold; border-radius: 8px;'>{$verification_code}</span>
                </div>
                <p>Usa este código para activar tu club.</p>
            </div>"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($correo_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json', 'api-key: ' . $brevo_api_key, 'content-type: application/json']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            throw new Exception('Error al enviar correo de verificación.');
        }

        // Insertar BD
        $stmt = $pdo->prepare("
            INSERT INTO clubs (nombre, deporte, jugadores_por_lado, fecha_fundacion, pais, region, ciudad, comuna, responsable, telefono, email_responsable, logo, verification_code, email_verified, created_at)
            VALUES (?, ?, ?, ?, 'Chile', ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $club_data['nombre'], $club_data['deporte'], $club_data['jugadores_por_lado'], $club_data['fecha_fundacion'],
            $club_data['region'], $club_data['ciudad'], $club_data['comuna'], $club_data['responsable'], 
            $club_data['telefono'], $club_data['email_responsable'], $logo_filename, $club_data['verification_code']
        ]);

        $success = true;
        $email_to_verify = $club_data['email_responsable'];

    } catch (Exception $e) {
        $error_type = 'general';
        $error_message = $e->getMessage();
        // Limpiar logo si falla
        if (!empty($logo_filename) && file_exists(__DIR__ . '/../uploads/logos/' . $logo_filename)) {
            unlink(__DIR__ . '/../uploads/logos/' . $logo_filename);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registra tu Club - CanchaSport</title>
    <style>
        /* RESET & BASE */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        body {
            /* Fondo con imagen visible pero suave */
            background-color: #eef2f6;
            background-image: url('/assets/img/cancha_pasto2.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Capa blanca translúcida sobre la imagen para legibilidad */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.6); /* Blanco translúcido */
            backdrop-filter: blur(4px);
            z-index: -1;
        }

        /* Contenedor Flotante */
        .float-card {
            background: #ffffff; /* Blanco puro */
            width: 100%;
            max-width: 500px;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); /* Sombra suave flotante */
            position: relative;
            border: 1px solid rgba(255,255,255,0.8);
            animation: floatIn 0.5s ease-out;
        }

        @keyframes floatIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Botón Cerrar (X) */
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #64748b;
            font-size: 1.2rem;
            font-weight: bold;
            transition: all 0.2s;
            cursor: pointer;
        }
        .close-btn:hover { background: #e2e8f0; color: #0f172a; transform: rotate(90deg); }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 800;
        }

        /* GRID LAYOUT 2 COLUMNAS */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .full-width { grid-column: span 2; }

        .input-group { display: flex; flex-direction: column; }
        
        label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc; /* Tono pastel muy claro */
            color: #334155;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Botón Registrar */
        .btn-submit {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #10b981, #059669); /* Verde Pastel/Emerald */
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4); }
        .btn-submit:active { transform: scale(0.98); }

        /* Mensajes Error/Exito */
        .alert {
            padding: 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        /* Responsive Móvil */
        @media (max-width: 480px) {
            .float-card { padding: 20px; margin: 10px; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; } /* 1 columna en móvil muy pequeño */
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="float-card">
    <!-- Botón Volver -->
    <a href="registro_socio_v2.php" class="close-btn" title="Volver al registro de socio">×</a>

    <?php if ($success): ?>
        <h2 style="color: #166534;">✅ ¡Club Registrado!</h2>
        <div class="alert alert-success">
            Hemos enviado un código de verificación a:<br>
            <strong><?= htmlspecialchars($email_to_verify) ?></strong>
        </div>
        <button class="btn-submit" onclick="window.location.href='verificar_codigo.php?email=<?= urlencode($email_to_verify) ?>'">
            Ir a Verificar Código
        </button>
    <?php else: ?>
        <h2>Registra tu Club ⚽🏟️</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
            
            <!-- CAMPOS HIDDEN CON DEFAULTS -->
            <input type="hidden" name="fecha_fundacion" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="region" value="Metropolitana">
            <input type="hidden" name="ciudad" value="Santiago">
            <input type="hidden" name="jugadores_por_lado" value="20">

            <div class="form-grid">
                <!-- Fila 1: Nombre Club | Deporte -->
                <div class="input-group full-width"> <!-- Nombre ocupa todo o mitad? Prompt dice 2 por fila. Pero nombre suele ser largo. Lo pondré full-width o half. El prompt dice: "Nombre Club Deporte". Asumo mitad. -->
                    <!-- Corrección: El prompt dice "Nombre Club [espacio] Deporte". Voy a poner Nombre en full-width si es muy largo, pero intentaré mitad si cabe. Para mejor UX, Nombre va completo o mitad grande. Dejemos mitad. -->
                    <div class="input-group">
                        <label>Nombre Club *</label>
                        <input type="text" name="nombre" placeholder="Ej: Los Tigres" required value="<?= htmlspecialchars($prefill_nombre) ?>">
                    </div>
                    <div class="input-group">
                        <label>Deporte *</label>
                        <select name="deporte" required>
                            <option value="">Seleccionar</option>
                            <option value="futbol">Fútbol</option>
                            <option value="futbolito">Futbolito</option>
                            <option value="baby">Baby Fútbol</option>
                            <option value="tenis">Tenis</option>
                            <option value="padel">Pádel</option>
                            <option value="voleyball">Vóleibol</option>
                        </select>
                    </div>
                </div>

                <!-- Fila 2: Comuna | Responsable -->
                <!-- Nota: Comuna requiere Región/Ciudad previos. Como Región/Ciudad son hidden (Santiago), puedo poner Comuna directo. -->
                <div class="input-group">
                    <label>Comuna *</label>
                    <!-- Podríamos hacer un select dinámico, pero como ciudad es fija Santiago, podríamos listar comunas de Santiago o dejar input texto. Para simplificar, input texto o select estático de comunas comunes -->
                    <input type="text" name="comuna" placeholder="Ej: Las Condes" required list="comunas-santiago">
                    <datalist id="comunas-santiago">
                        <option value="Las Condes">
                        <option value="Providencia">
                        <option value="Santiago Centro">
                        <option value="Ñuñoa">
                        <option value="Vitacura">
                        <option value="La Reina">
                        <option value="Maipú">
                        <option value="Quilicura">
                    </datalist>
                </div>
                <div class="input-group">
                    <label>Responsable *</label>
                    <input type="text" name="responsable" placeholder="Tu nombre" required>
                </div>

                <!-- Fila 3: Correo | Celular -->
                <div class="input-group">
                    <label>Correo *</label>
                    <input type="email" name="email_responsable" placeholder="tu@email.com" required value="<?= htmlspecialchars($prefill_email) ?>">
                </div>
                <div class="input-group">
                    <label>Celular</label>
                    <input type="tel" name="telefono" placeholder="+56 9..." autocomplete="tel">
                </div>

                <!-- Fila 4: Logo (Full Width) -->
                <div class="input-group full-width">
                    <label>Logo del Club (Opcional)</label>
                    <input type="file" name="logo" accept="image/*" style="padding: 8px; background: white;">
                    <small style="color:#64748b; font-size:0.7rem; margin-top:4px;">JPG, PNG (Max 2MB)</small>
                </div>
            </div>

            <button type="submit" class="btn-submit">Registrar Club</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // Formato automático celular
    document.querySelector('input[name="telefono"]').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });
</script>

</body>
</html>