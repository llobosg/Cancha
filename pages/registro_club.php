<?php
require_once __DIR__ . '/../includes/config.php';

// Obtener regiones (solo por si acaso, aunque usaremos default)
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
        $required = ['nombre', 'deporte', 'comuna', 'responsable', 'email_responsable'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Todos los campos marcados son obligatorios');
            }
        }

        // Agrega esto al inicio de la validación POST
        if (empty($_POST['password']) || empty($_POST['password_confirm'])) {
            throw new Exception('La contraseña es obligatoria');
        }
        if ($_POST['password'] !== $_POST['password_confirm']) {
            throw new Exception('Las contraseñas no coinciden');
        }
        if (strlen($_POST['password']) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }

        // Hashear la contraseña para guardarla luego en el socio
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Validar email
        if (!filter_var($_POST['email_responsable'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico inválido.');
        }

        // === VALIDAR NOMBRE DE CLUB EXISTENTE (Backend también) ===
        $stmt_check_name = $pdo->prepare("SELECT id_club FROM clubs WHERE LOWER(nombre) = LOWER(?)");
        $stmt_check_name->execute([$_POST['nombre']]);
        if ($stmt_check_name->fetch()) {
            throw new Exception('⚠️ Ya existe un club con ese nombre. Por favor elige otro.');
        }

        // Verificar email duplicado
        $stmt_check_email = $pdo->prepare("SELECT id_club FROM clubs WHERE email_responsable = ?");
        $stmt_check_email->execute([$_POST['email_responsable']]);
        if ($stmt_check_email->fetch()) {
            throw new Exception('Este correo ya tiene un club registrado.');
        }

        // Generar código
        $verification_code = rand(1000, 9999);
        
        // Datos (usando defaults hidden)
        // NOTA: Se eliminó 'region' del INSERT si tu tabla no lo tiene.
        $club_data = [
            'nombre' => $_POST['nombre'],
            'deporte' => $_POST['deporte'],
            'jugadores_por_lado' => 20, // Hardcoded según requerimiento
            'fecha_fundacion' => date('Y-m-d'), // Hoy
            'ciudad' => 'Santiago', // Hardcoded
            'comuna' => $_POST['comuna'],
            'responsable' => $_POST['responsable'],
            'telefono' => $_POST['telefono'] ?: null,
            'email_responsable' => $_POST['email_responsable'],
            'verification_code' => $verification_code
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

        // Insertar BD (CORREGIDO: Sin 'region' si no existe en tu tabla)
        // Ajusta los campos según tu tabla real. Si tienes 'region', agrégalos de vuelta.
        $stmt = $pdo->prepare("
            INSERT INTO clubs (nombre, deporte, jugadores_por_lado, fecha_fundacion, ciudad, comuna, responsable, telefono, email_responsable, logo, verification_code, email_verified, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $club_data['nombre'], $club_data['deporte'], $club_data['jugadores_por_lado'], $club_data['fecha_fundacion'],
            $club_data['ciudad'], $club_data['comuna'], $club_data['responsable'], 
            $club_data['telefono'], $club_data['email_responsable'], $logo_filename, $club_data['verification_code']
        ]);

        // Después de insertar el club y antes de mostrar éxito:
        if (session_status() === PHP_SESSION_NONE) session_start();

        $_SESSION['temp_club_data'] = [
            'email' => $club_data['email_responsable'],
            'responsable' => $club_data['responsable'],
            'password_hash' => $password_hash, // La que generaste arriba
            'club_id' => $pdo->lastInsertId() // O el ID que obtuviste
        ];

        // Luego muestra el formulario de verificación normalmente

        $success = true;
        $email_to_verify = $club_data['email_responsable'];

    } catch (Exception $e) {
        $error_type = 'general';
        $error_message = $e->getMessage();
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
        
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(4px);
            z-index: -1;
        }

        .float-card {
            background: #ffffff;
            width: 100%;
            max-width: 500px;
            border-radius: 24px;
            padding: 25px; /* Reducido un poco */
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            position: relative;
            border: 1px solid rgba(255,255,255,0.8);
            animation: floatIn 0.5s ease-out;
        }

        @keyframes floatIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 28px;
            height: 28px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #64748b;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.2s;
            cursor: pointer;
        }
        .close-btn:hover { background: #e2e8f0; color: #0f172a; transform: rotate(90deg); }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 1.3rem; /* Fuente más pequeña */
            font-weight: 800;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px; /* Gap reducido */
            margin-bottom: 15px;
            position: relative; /* Para posicionar resultados búsqueda */
        }
        
        .full-width { grid-column: span 2; }

        .input-group { display: flex; flex-direction: column; position: relative; }
        
        label {
            font-size: 0.7rem; /* Fuente más pequeña */
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select {
            width: 100%;
            padding: 8px 10px; /* Padding reducido */
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            font-size: 0.85rem; /* Fuente más pequeña */
            transition: all 0.2s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        /* Estilos para la lista de búsqueda */
        #search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-height: 150px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
            margin-top: 2px;
        }
        .search-item {
            padding: 8px 10px;
            cursor: pointer;
            font-size: 0.8rem;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .search-item:hover { background: #eff6ff; color: #2563eb; }
        .search-item:last-child { border-bottom: none; }

        .btn-submit {
            width: 100%;
            padding: 10px; /* Padding reducido */
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-weight: bold;
            font-size: 0.9rem; /* Fuente más pequeña */
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: transform 0.2s;
            margin-top: 5px;
        }
        .btn-submit:hover { transform: translateY(-2px); }

        .alert {
            padding: 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        @media (max-width: 480px) {
            .float-card { padding: 15px; margin: 10px; }
            .form-grid { grid-template-columns: 1fr; gap: 10px; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="float-card">
    <a href="registro_socio.php" class="close-btn" title="Volver">×</a>

    <?php if ($success): ?>
        <h2 style="color: #166534;">✅ ¡Club Registrado!</h2>
        <div class="alert alert-success">
            Código enviado a:<br><strong><?= htmlspecialchars($email_to_verify) ?></strong>
        </div>
        <button class="btn-submit" onclick="window.location.href='verificar_codigo.php?email=<?= urlencode($email_to_verify) ?>'">Ir a Verificar</button>
    <?php else: ?>
        <h2>Registra tu Club ⚽</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
            
            <!-- HIDDEN DEFAULTS -->
            <input type="hidden" name="fecha_fundacion" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="ciudad" value="Santiago">
            <input type="hidden" name="jugadores_por_lado" value="20">

            <div class="form-grid">
                <!-- Fila 1: Nombre Club (con búsqueda) | Deporte -->
                <div class="input-group">
                    <label>Nombre Club *</label>
                    <input type="text" id="nombre_club_input" name="nombre" placeholder="Ej: Los Tigres" required>
                    <!-- Contenedor de resultados -->
                    <div id="search-results"></div>
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

                <!-- Fila 2: Comuna | Responsable -->
                <div class="input-group">
                    <label>Comuna *</label>
                    <input type="text" name="comuna" placeholder="Ej: Las Condes" required list="comunas-santiago">
                    <datalist id="comunas-santiago">
                        <option value="Las Condes"><option value="Providencia"><option value="Santiago Centro">
                        <option value="Ñuñoa"><option value="Vitacura"><option value="La Reina">
                        <option value="Maipú"><option value="Quilicura"><option value="Puente Alto">
                    </datalist>
                </div>
                <div class="input-group">
                    <label>Responsable *</label>
                    <input type="text" name="responsable" placeholder="Tu nombre" required value="<?= htmlspecialchars($prefill_nombre) ?>">
                </div>

                <!-- Fila 3: Correo | Celular -->
                <div class="input-group">
                    <label>Correo *</label>
                    <input type="email" name="email_responsable" placeholder="tu@email.com" required value="<?= htmlspecialchars($prefill_email) ?>">
                </div>
                <div class="input-group">
                    <label>Celular</label>
                    <input type="tel" name="telefono" placeholder="+56 9..." value="<?= htmlspecialchars($prefill_telefono) ?>">
                </div>

                <!-- Fila 4: Logo -->
                <div class="input-group full-width">
                    <label>Logo del Club (Opcional)</label>
                    <input type="file" name="logo" accept="image/*" style="padding: 6px; background: white; font-size: 0.75rem;">
                </div>

                <!-- Fila 5: Contraseñas -->
                <div class="input-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" required style="padding: 8px 10px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; font-size: 0.85rem; width: 100%;">
                </div>
                <div class="input-group">
                    <label>Confirmar Contraseña *</label>
                    <input type="password" name="password_confirm" placeholder="Repite contraseña" required style="padding: 8px 10px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; font-size: 0.85rem; width: 100%;">
                </div>
            </div>

            <button type="submit" class="btn-submit">Registrar Club</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // 1. Búsqueda en tiempo real de clubes
    const inputNombre = document.getElementById('nombre_club_input');
    const resultsDiv = document.getElementById('search-results');
    let timeout = null;

    if (inputNombre) {
        inputNombre.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            resultsDiv.style.display = 'none';
            resultsDiv.innerHTML = '';

            if (query.length < 2) return;

            // Debounce para no saturar el servidor
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                fetch(`../api/buscar_clubes_nombre.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.length > 0) {
                            resultsDiv.innerHTML = '';
                            data.forEach(club => {
                                const div = document.createElement('div');
                                div.className = 'search-item';
                                div.textContent = club.nombre + (club.deporte ? ` (${club.deporte})` : '');
                                div.onclick = () => {
                                    // Opcional: Si quieres bloquear la selección, solo muestra alerta
                                    // inputNombre.value = ''; 
                                    // alert('Este nombre ya está en uso. Elige otro.');
                                    
                                    // O simplemente mostrar que existe y dejar que el usuario decida cambiarlo
                                    // Aquí solo mostramos la lista para informar
                                };
                                resultsDiv.appendChild(div);
                            });
                            resultsDiv.style.display = 'block';
                        }
                    })
                    .catch(err => console.error(err));
            }, 300);
        });

        // Cerrar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!inputNombre.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
    }

    // 2. Formato celular
    document.querySelector('input[name="telefono"]').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });
</script>

</body>
</html>