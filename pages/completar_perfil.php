<?php
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar autenticación
if (!isset($_SESSION['google_email']) && !isset($_SESSION['user_email'])) {
    header('Location: ../index.php');
    exit;
}

$user_email = $_SESSION['google_email'] ?? $_SESSION['user_email'];
$id_socio = $_SESSION['id_socio'] ?? 0;

if (!$id_socio) {
    header('Location: ../index.php');
    exit;
}

// Obtener club desde URL o sesión
$club_id_from_url = $_GET['id'] ?? '';

// Validar que sea un número entero positivo
if (!$club_id_from_url || !is_numeric($club_id_from_url) || (int)$club_id_from_url <= 0) {
    if (isset($_SESSION['club_id'])) {
        $club_id = (int)$_SESSION['club_id'];
    } else {
        header('Location: ../pages/dashboard_socio.php');
        exit;
    }
} else {
    $club_id = (int)$club_id_from_url;
}

// Verificar que el club exista
$stmt = $pdo->prepare("SELECT id_club, nombre FROM clubs WHERE id_club = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();

if (!$club) {
    header('Location: ../pages/dashboard_socio.php');
    exit;
}

// Verificar que el socio pertenezca a este club
$stmt = $pdo->prepare("SELECT s.*, p.puesto as puesto_nombre FROM socios s LEFT JOIN puestos p ON s.id_puesto = p.id_puesto WHERE s.id_socio = ? AND s.id_club = ?");
$stmt->execute([$id_socio, $club_id]);
$socio = $stmt->fetch();

if (!$socio) {
    header('Location: ../pages/dashboard_socio.php');
    exit;
}

// Obtener puestos disponibles
$stmt_puestos = $pdo->prepare("SELECT id_puesto, puesto FROM puestos WHERE 1=1 ORDER BY puesto");
$stmt_puestos->execute();
$puestos = $stmt_puestos->fetchAll();

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fecha_nac = trim($_POST['fecha_nac'] ?? '');
        $celular = trim($_POST['celular'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $foto_url = trim($_POST['foto_url'] ?? '');
        $id_puesto = (int)($_POST['id_puesto'] ?? 0);
        $genero = trim($_POST['genero'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validaciones
        if (empty($fecha_nac)) {
            throw new Exception('La fecha de nacimiento es requerida');
        }
        
        $fecha_nac_obj = DateTime::createFromFormat('Y-m-d', $fecha_nac);
        if (!$fecha_nac_obj || $fecha_nac_obj->format('Y-m-d') !== $fecha_nac) {
            throw new Exception('Formato de fecha de nacimiento inválido (YYYY-MM-DD)');
        }
        
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac_obj)->y;
        if ($edad < 13) {
            throw new Exception('Debes tener al menos 13 años');
        }
        
        if (empty($celular)) {
            throw new Exception('El número de celular es requerido');
        }
        
        if (!preg_match('/^[\d\s+\-\(\)]{8,20}$/', $celular)) {
            throw new Exception('Formato de celular inválido');
        }
        
        if (empty($direccion)) {
            throw new Exception('La dirección es requerida');
        }
        
        if (strlen($direccion) < 5) {
            throw new Exception('La dirección debe tener al menos 5 caracteres');
        }
        
        if (!empty($foto_url) && !filter_var($foto_url, FILTER_VALIDATE_URL)) {
            throw new Exception('URL de foto inválida');
        }
        
        if ($id_puesto > 0) {
            $stmt_check = $pdo->prepare("SELECT id_puesto FROM puestos WHERE id_puesto = ?");
            $stmt_check->execute([$id_puesto]);
            if (!$stmt_check->fetch()) {
                throw new Exception('Puesto no válido');
            }
        }
        
        // Validación de género
        $generos_validos = ['Femenino', 'Masculino', 'Otro'];
        if (!in_array($genero, $generos_validos)) {
            throw new Exception('Género no válido');
        }
        
        // Validación de email
        if (empty($email)) {
            throw new Exception('El correo electrónico es requerido');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de correo electrónico inválido');
        }
        
        // Verificar si el email ya existe para otro socio
        $stmt_check_email = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? AND id_socio != ? AND id_club = ?");
        $stmt_check_email->execute([$email, $id_socio, $club_id]);
        if ($stmt_check_email->fetch()) {
            throw new Exception('Este correo electrónico ya está registrado en este club');
        }
        
        // Actualizar perfil
        $stmt = $pdo->prepare("
            UPDATE socios 
            SET 
                fecha_nac = ?, 
                celular = ?, 
                direccion = ?, 
                foto_url = ?, 
                id_puesto = ?,
                genero = ?,
                email = ?,
                datos_completos = 1, 
                updated_at = NOW()
            WHERE id_socio = ? AND id_club = ?
        ");
        $stmt->execute([
            $fecha_nac, 
            $celular, 
            $direccion, 
            $foto_url ?: null, 
            $id_puesto ?: null,
            $genero,
            $email,
            $id_socio, 
            $club_id
        ]);
        
        // Actualizar la sesión con el nuevo email si cambió
        if ($email !== $socio['email']) {
            if (isset($_SESSION['google_email'])) {
                $_SESSION['google_email'] = $email;
            } else {
                $_SESSION['user_email'] = $email;
            }
        }
        
        $_SESSION['mensaje_exito'] = 'Perfil actualizado exitosamente';

        // Después de guardar el socio en la base de datos
        if ($modo_individual) {
            $asunto = '⚽🎾  ¡Bienvenido a CanchaSport!';
            $mensaje = "
                <p>¡Hola {$nombre}!</p>
                <p>Gracias por unirte a CanchaSport como socio individual.</p>
                <p>Aquí podrás gestionar tus partidos, reservar canchas y participar en la comunidad deportiva.</p>
                <p><a href='https://canchasport.com'>Ingresa a tu dashboard</a></p>
            ";
        } else {
            $asunto = '⚽🎾  ¡Bienvenido a tu club en CanchaSport!';
            $mensaje = "
                <p>¡Hola {$nombre}!</p>
                <p>Has sido registrado exitosamente en el club <strong>{$club_nombre}</strong>.</p>
                <p>A partir de ahora recibirás notificaciones sobre eventos, cuotas y novedades del club.</p>
                <p><a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}'>Ir a tu dashboard</a></p>
            ";
        }

        $mail = new BrevoMailer();
        $mail->setTo($email, $nombre);
        $mail->setSubject($asunto);
        $mail->setHtmlBody($mensaje);
        $mail->send();
        
        // Generar club_slug para redirección
        $stmt_club = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE id_club = ?");
        $stmt_club->execute([$club_id]);
        $club_data = $stmt_club->fetch();
        
        if ($club_data) {
            $club_slug = substr(md5($club_data['id_club'] . $club_data['email_responsable']), 0, 8);
            $dashboard_url = '../pages/dashboard_socio.php?id_club=' . $club_slug;
        } else {
            $dashboard_url = '../pages/dashboard_socio.php';
        }
        
        header('Location: ' . $dashboard_url);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos actuales
$fecha_nac_actual = $socio['fecha_nac'] ?? '';
$celular_actual = $socio['celular'] ?? '';
$direccion_actual = $socio['direccion'] ?? '';
$foto_url_actual = $socio['foto_url'] ?? '';
$id_puesto_actual = $socio['id_puesto'] ?? 0;
$genero_actual = $socio['genero'] ?? 'Femenino';
$email_actual = $socio['email'] ?? '';
$datos_completos = (bool)$socio['datos_completos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $datos_completos ? 'Actualizar Perfil' : 'Completar Perfil' ?> - <?= htmlspecialchars($club['nombre']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e1e5e9;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $datos_completos ? 'Actualizar Mi Perfil' : 'Completar Mi Perfil' ?></h1>
            <p><?= htmlspecialchars($club['nombre']) ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($datos_completos): ?>
            <div class="success">
                ✅ Tu perfil ya está completo. Puedes actualizar tus datos si lo deseas.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Correo Electrónico *</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= htmlspecialchars($email_actual) ?>"
                    placeholder="tu@email.com"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="fecha_nac">Fecha de Nacimiento *</label>
                <input 
                    type="date" 
                    id="fecha_nac" 
                    name="fecha_nac" 
                    value="<?= htmlspecialchars($fecha_nac_actual) ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="genero">Género *</label>
                <select id="genero" name="genero" required>
                    <option value="">Seleccionar género</option>
                    <option value="Femenino" <?= $genero_actual === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                    <option value="Masculino" <?= $genero_actual === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                    <option value="Otro" <?= $genero_actual === 'Otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="celular">Número de Celular *</label>
                <input 
                    type="tel" 
                    id="celular" 
                    name="celular" 
                    value="<?= htmlspecialchars($celular_actual) ?>"
                    placeholder="+56 9 1234 5678"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="direccion">Dirección *</label>
                <input 
                    type="text" 
                    id="direccion" 
                    name="direccion" 
                    value="<?= htmlspecialchars($direccion_actual) ?>"
                    placeholder="Calle Principal 123, Ciudad"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="foto_url">URL de Foto (opcional)</label>
                <input 
                    type="url" 
                    id="foto_url" 
                    name="foto_url" 
                    value="<?= htmlspecialchars($foto_url_actual) ?>"
                    placeholder="https://ejemplo.com/foto.jpg"
                >
            </div>
            
            <div class="form-group">
                <label for="id_puesto">Puesto en el Club</label>
                <select id="id_puesto" name="id_puesto">
                    <option value="">Seleccionar puesto</option>
                    <?php foreach ($puestos as $puesto): ?>
                        <option value="<?= $puesto['id_puesto'] ?>" 
                                <?= $puesto['id_puesto'] == $id_puesto_actual ? 'selected' : '' ?>>
                            <?= htmlspecialchars($puesto['puesto']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="btn-container">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= $datos_completos ? 'Actualizar Perfil' : 'Completar Perfil' ?>
                </button>
            </div>
        </form>
    </div>
    
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const fecha_nac = document.getElementById('fecha_nac').value;
            const genero = document.getElementById('genero').value;
            const celular = document.getElementById('celular').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Por favor ingresa un correo electrónico válido');
                return;
            }
            
            if (!fecha_nac) {
                e.preventDefault();
                alert('La fecha de nacimiento es requerida');
                return;
            }
            
            if (!genero) {
                e.preventDefault();
                alert('Por favor selecciona un género');
                return;
            }
            
            if (celular.length < 8) {
                e.preventDefault();
                alert('El número de celular debe tener al menos 8 dígitos');
                return;
            }
            
            if (direccion.length < 5) {
                e.preventDefault();
                alert('La dirección debe tener al menos 5 caracteres');
                return;
            }
        });
    </script>
</body>
</html>