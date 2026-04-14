<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener regiones desde la base de datos
$stmt_regiones = $pdo->query("SELECT DISTINCT codigo_region, nombre_region FROM regiones_chile ORDER BY nombre_region");
$regiones_chile = [];
while ($row = $stmt_regiones->fetch()) {
    $regiones_chile[$row['codigo_region']] = $row['nombre_region'];
}

$error_message = '';
$error_type = '';
$success = false;
$email_to_verify = '';
$club_data = [];

// Valores por defecto desde URL (para prefill)
$prefill_nombre = $_GET['prefill_nombre'] ?? '';
$prefill_email = $_GET['prefill_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos requeridos
        $required = ['nombre', 'deporte', 'jugadores_por_lado', 'ciudad', 'comuna', 'responsable', 'email_responsable'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Todos los campos marcados son obligatorios');
            }
        }

        // Validar email_responsable
        if (!filter_var($_POST['email_responsable'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico del responsable inválido.');
        }

        // Validar jugadores_por_lado
        $jugadores = (int)$_POST['jugadores_por_lado'];
        if ($jugadores < 1 || $jugadores > 20) {
            throw new Exception('Jugadores por lado debe estar entre 1 y 20');
        }

        // Verificar si el correo ya tiene un club registrado
        $stmt_check = $pdo->prepare("SELECT id_club FROM clubs WHERE email_responsable = ?");
        $stmt_check->execute([$_POST['email_responsable']]);
        if ($stmt_check->fetch()) {
            $error_type = 'duplicate';
            $error_message = 'duplicate_email';
        } else {
            // Generar código de verificación
            $verification_code = rand(1000, 9999);
            
            // Preparar datos del club
            $club_data = [
                'nombre' => $_POST['nombre'],
                'deporte' => $_POST['deporte'],
                'jugadores_por_lado' => $jugadores,
                'fecha_fundacion' => $_POST['fecha_fundacion'] ?: null,
                'ciudad' => $_POST['ciudad'],
                'comuna' => $_POST['comuna'],
                'responsable' => $_POST['responsable'],
                'telefono' => $_POST['telefono'] ?: null,
                'email_responsable' => $_POST['email_responsable'],
                'verification_code' => $verification_code
            ];

            // Subir logo si existe
            $logo_filename = null;
            if (!empty($_FILES['logo']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                    throw new Exception('Solo se permiten imágenes JPG, PNG o GIF');
                }
                
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('El logo debe pesar menos de 2MB');
                }
                
                $logo_filename = uniqid() . '_' . basename($_FILES['logo']['name']);
                $upload_dir = __DIR__ . '/../uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename)) {
                    throw new Exception('Error al subir el logo');
                }
                $club_data['logo'] = $logo_filename;
            }

            // ENVIAR CORREO DE VERIFICACIÓN CON BREVO
            $email_to_verify = $_POST['email_responsable'];
            $brevo_api_key = $_ENV['BREVO_API_KEY'] ?? '';

            if (!$brevo_api_key) {
                throw new Exception('Error de configuración del servicio de correo');
            }

            $from_email = $_ENV['MAILER_FROM_EMAIL'] ?? 'llobos@gltcomex.com';
            $from_name = 'CanchaSport';

            $correo_data = [
                'sender' => [
                    'name' => $from_name,
                    'email' => $from_email
                ],
                'to' => [[
                    'email' => $email_to_verify,
                    'name' => $_POST['responsable']
                ]],
                'subject' => '⚽ Código de verificación - CanchaSport',
                'htmlContent' => "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 12px;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #003366; font-size: 28px;'>⚽ CanchaSport</h1>
                        <p style='color: #666; font-size: 14px;'>Tu club deportivo a un click</p>
                    </div>
                    
                    <div style='background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                        <h2 style='color: #003366; margin-bottom: 15px;'>¡Hola " . htmlspecialchars($_POST['responsable']) . "!</h2>
                        <p style='font-size: 16px; line-height: 1.6; color: #333; margin-bottom: 20px;'>
                            Gracias por registrar tu club en CanchaSport. 
                        </p>
                        <p style='font-size: 16px; line-height: 1.6; color: #333; margin-bottom: 25px;'>
                            Tu código de verificación es:
                        </p>
                        
                        <div style='text-align: center; margin: 25px 0;'>
                            <span style='background: #003366; color: white; padding: 15px 25px; font-size: 24px; font-weight: bold; border-radius: 8px; letter-spacing: 3px; display: inline-block;'>
                                " . $verification_code . "
                            </span>
                        </div>
                        
                        <p style='font-size: 16px; line-height: 1.6; color: #333; text-align: center;'>
                            Ingresa este código en la página de verificación para activar tu club.
                        </p>
                    </div>
                    
                    <div style='text-align: center; color: #666; font-size: 14px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p>¡Bienvenido a CanchaSport!</p>
                        <p>El equipo de CanchaSport</p>
                    </div>
                </div>
                "
            ];

            // Enviar con Brevo API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($correo_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $brevo_api_key,
                'content-type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 201) {
                error_log("Brevo error: HTTP $httpCode, Response: " . substr($response, 0, 200));
                throw new Exception('No se pudo enviar el correo de verificación. Por favor, inténtalo nuevamente.');
            }

            // Guardar en base de datos
            $stmt = $pdo->prepare("
                INSERT INTO clubs (
                    nombre, deporte, jugadores_por_lado, fecha_fundacion, pais, ciudad, comuna, 
                    responsable, telefono, email_responsable, logo, verification_code, email_verified, created_at
                ) VALUES (?, ?, ?, ?, 'Chile', ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $club_data['nombre'],
                $club_data['deporte'],
                $club_data['jugadores_por_lado'],
                $club_data['fecha_fundacion'],
                $club_data['ciudad'],
                $club_data['comuna'],
                $club_data['responsable'],
                $club_data['telefono'],
                $club_data['email_responsable'],
                $club_data['logo'] ?? null,
                $club_data['verification_code']
            ]);

            $club_id = $pdo->lastInsertId();
            // El slug se genera al verificar o al usar el club, pero podemos prepararlo
            // $club_slug = substr(md5($club_id . $club_data['email_responsable']), 0, 8);

            $success = true;
        }

    } catch (Exception $e) {
        $error_type = 'general';
        $error_message = $e->getMessage();
        
        if (!empty($logo_filename)) {
            $upload_dir = __DIR__ . '/../uploads/logos/';
            if (file_exists($upload_dir . $logo_filename)) {
                unlink($upload_dir . $logo_filename);
            }
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
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Estilos Base (Igual que registro_socio_v2) */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body {
            background-color: #0f172a;
            background-image: url('/assets/img/cancha_pasto2.jpg');
            background-size: cover;
            background-position: center;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px 0;
        }
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
            pointer-events: none; z-index: -1;
        }
        .app-container { width: 100%; max-width: 480px; padding-bottom: 40px; position: relative; }
        
        .logo-header { text-align: center; margin: 20px 0 15px; }
        .logo-header h1 { 
            font-size: 1.8rem; 
            background: linear-gradient(135deg, #4ade80, #3b82f6); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            font-weight: 900;
        }
        .logo-header p { color: #cbd5e1; font-size: 0.9rem; margin-top: 5px; }

        .card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 25px;
            margin: 0 16px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .form-group { margin-bottom: 15px; }
        .input-label { display: block; color: #94a3b8; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15,23,42,0.6);
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15,23,42,0.9);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }
        .btn:active { transform: scale(0.98); }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2rem;
            color: #94a3b8;
            text-decoration: none;
            cursor: pointer;
            z-index: 10;
        }

        .hidden { display: none !important; }
        
        /* Toast */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #333; color: #fff;
            text-align: center; border-radius: 8px; padding: 16px; position: fixed;
            z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        .error-box {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .success-box {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Botón Cerrar -->
    <a href="../index.php" class="close-btn" title="Volver">×</a>

    <div class="logo-header">
        <h1>CanchaSport ⚽</h1>
        <p>Registra tu Club de Amigos</p>
    </div>

    <div class="card">
        <?php if ($success): ?>
            <!-- VISTA ÉXITO -->
            <div class="success-box">
                <h3 style="margin-bottom:10px;">✅ ¡Club Registrado!</h3>
                <p>Hemos enviado un código de verificación a:<br><strong><?= htmlspecialchars($email_to_verify) ?></strong></p>
                <p style="font-size:0.8rem; margin-top:10px; opacity:0.8;">Revisa tu bandeja de entrada (y spam).</p>
            </div>
            <button class="btn" onclick="window.location.href='verificar_codigo.php?email=<?= urlencode($email_to_verify) ?>'">
                Ingresar Código de Verificación
            </button>
            <p style="text-align:center; margin-top:15px; font-size:0.85rem; color:#94a3b8;">
                ¿No llegó el correo? <a href="#" onclick="location.reload()" style="color:#60a5fa;">Intentar de nuevo</a>
            </p>

        <?php else: ?>
            <!-- FORMULARIO -->
            <?php if ($error_message): ?>
                <div class="error-box">
                    <?php if ($error_type === 'duplicate'): ?>
                        <strong>⚠️ ¡Hola! Ya tienes un club registrado.</strong><br><br>
                        La versión <strong>Gratuita</strong> permite 1 club por responsable.<br>
                        ¿Necesitas gestionar múltiples clubes? Contáctanos para la versión <strong>Premiere League</strong>:<br>
                        📧 contacto@canchasport.com |  +569 3656 0392
                    <?php else: ?>
                        <?= htmlspecialchars($error_message) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
                
                <!-- Nombre Club -->
                <div class="form-group">
                    <label class="input-label">Nombre del Club *</label>
                    <input type="text" name="nombre" class="input" placeholder="Ej: Los Guerreros del Sábado" required value="<?= htmlspecialchars($prefill_nombre) ?>">
                </div>

                <!-- Deporte y Jugadores (Fila) -->
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label class="input-label">Deporte *</label>
                        <select name="deporte" class="input" required>
                            <option value="">Seleccionar</option>
                            <option value="futbol">Fútbol</option>
                            <option value="futbolito">Futbolito</option>
                            <option value="baby">Baby fútbol</option>
                            <option value="tenis">Tenis</option>
                            <option value="padel">Pádel</option>
                            <option value="voleyball">Vóleibol</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="input-label">Jugadores/Lado</label>
                        <input type="number" name="jugadores_por_lado" class="input" placeholder="Ej: 7" min="1" max="20" value="7" required>
                    </div>
                </div>

                <!-- Fecha Fundación -->
                <div class="form-group">
                    <label class="input-label">Fecha Fundación (Opcional)</label>
                    <input type="date" name="fecha_fundacion" class="input">
                </div>

                <!-- Ubicación (Región, Ciudad, Comuna) -->
                <div class="form-group">
                    <label class="input-label">Región *</label>
                    <select id="region" name="region" class="input" required onchange="actualizarCiudades()">
                        <option value="">Seleccionar región</option>
                        <?php foreach ($regiones_chile as $codigo => $nombre): ?>
                            <option value="<?= $codigo ?>"><?= htmlspecialchars($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="input-label">Ciudad *</label>
                    <select id="ciudad" name="ciudad" class="input" required disabled>
                        <option value="">Seleccionar región primero</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="input-label">Comuna *</label>
                    <select id="comuna" name="comuna" class="input" required disabled>
                        <option value="">Seleccionar ciudad primero</option>
                    </select>
                </div>

                <!-- Datos Responsable -->
                <div class="form-group">
                    <label class="input-label">Nombre Responsable *</label>
                    <input type="text" name="responsable" class="input" placeholder="Tu nombre completo" required>
                </div>

                <div class="form-group">
                    <label class="input-label">Correo Electrónico *</label>
                    <input type="email" name="email_responsable" class="input" placeholder="tu@email.com" required value="<?= htmlspecialchars($prefill_email) ?>">
                </div>

                <div class="form-group">
                    <label class="input-label">Teléfono (Opcional)</label>
                    <input type="tel" id="telefono" name="telefono" class="input" placeholder="+56 9..." autocomplete="tel">
                </div>

                <!-- Logo -->
                <div class="form-group">
                    <label class="input-label">Logo del Club (Opcional)</label>
                    <input type="file" name="logo" class="input" accept="image/*" style="padding: 8px;">
                    <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:5px;">JPG, PNG o GIF (Máx 2MB)</small>
                </div>

                <button type="submit" class="btn"> Registrar Club</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div id="toast">Mensaje</div>

<script>
    let datosChile = {};
    
    // Cargar datos desde API
    fetch('../api/get_regiones.php')
      .then(response => response.json())
      .then(data => {
        datosChile = data;
      })
      .catch(error => console.error('Error regiones:', error));

    function actualizarCiudades() {
      const region = document.getElementById('region').value;
      const ciudadSelect = document.getElementById('ciudad');
      const comunaSelect = document.getElementById('comuna');
      
      ciudadSelect.innerHTML = '<option value="">Seleccionar ciudad</option>';
      comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
      ciudadSelect.disabled = !region;
      comunaSelect.disabled = true;
      
      if (region && datosChile[region]) {
        Object.entries(datosChile[region].ciudades).forEach(([codigo, nombre]) => {
          const option = document.createElement('option');
          option.value = codigo;
          option.textContent = nombre;
          ciudadSelect.appendChild(option);
        });
        ciudadSelect.disabled = false;
      }
    }

    document.getElementById('ciudad')?.addEventListener('change', function() {
      const region = document.getElementById('region').value;
      const ciudad = this.value;
      const comunaSelect = document.getElementById('comuna');
      
      comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
      comunaSelect.disabled = !(region && ciudad && datosChile[region]?.comunas?.[ciudad]);
      
      if (region && ciudad && datosChile[region]?.comunas?.[ciudad]) {
        datosChile[region].comunas[ciudad].forEach(comuna => {
          const option = document.createElement('option');
          option.value = comuna.toLowerCase().replace(/\s+/g, '_');
          option.textContent = comuna;
          comunaSelect.appendChild(option);
        });
        comunaSelect.disabled = false;
      }
    });

    // Formato teléfono
    document.getElementById('telefono')?.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9+]/g, '');
    });

    function showToast(msg) {
        const t = document.getElementById("toast");
        if(t) {
            t.textContent = msg;
            t.className = "show";
            setTimeout(() => t.className = t.className.replace("show", ""), 3000);
        }
    }
</script>
</body>
</html>