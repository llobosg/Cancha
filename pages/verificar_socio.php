<?php
require_once __DIR__ . '/../includes/config.php';

// Configuración robusta de sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Obtener club desde URL
$club_slug_from_url = $_GET['club'] ?? '';

if (!$club_slug_from_url || strlen($club_slug_from_url) !== 8 || !ctype_alnum($club_slug_from_url)) {
    header('Location: ../index.php');
    exit;
}

// Obtener todos los clubs verificados
$stmt_club = $pdo->prepare("SELECT id_club, email_responsable, nombre FROM clubs WHERE email_verified = 1");
$stmt_club->execute();
$clubs = $stmt_club->fetchAll();

$club = null;
foreach ($clubs as $c) {
    $generated_slug = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
    if ($generated_slug === $club_slug_from_url) {
        $club = $c;
        break;
    }
}

if (!$club) {
    header('Location: ../index.php');
    exit;
}

$club_id = (int)$club['id_club'];
$club_nombre = $club['nombre'];

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo_ingresado = trim($_POST['codigo'] ?? '');
        
        if (empty($codigo_ingresado) || strlen($codigo_ingresado) !== 4) {
            throw new Exception('Código inválido. Debe tener 4 dígitos.');
        }
        
        // Buscar socio con este código de verificación y club
        $stmt = $pdo->prepare("
            SELECT id_socio, email, nombre 
            FROM socios 
            WHERE verification_code = ? 
            AND id_club = ?
            AND email_verified = 0
        ");
        $stmt->execute([$codigo_ingresado, $club_id]);
        $socio = $stmt->fetch();
        
        if (!$socio) {
            throw new Exception('Código incorrecto o ya verificado.');
        }
        
        // Actualizar socio como verificado
        $stmt_update = $pdo->prepare("
            UPDATE socios 
            SET email_verified = 1, verification_code = NULL
            WHERE id_socio = ?
        ");
        $stmt_update->execute([$socio['id_socio']]);
        
        // Guardar en sesión
        $_SESSION['user_email'] = $socio['email'];
        $_SESSION['id_socio'] = $socio['id_socio'];
        $_SESSION['club_id'] = $club_id;
        
        // Redirigir al dashboard correcto
        header('Location: ../pages/dashboard_socio.php?id_club=' . $club_slug_from_url);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código - <?= htmlspecialchars($club_nombre) ?></title>
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
            max-width: 400px;
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 8px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            background: #667eea;
            color: white;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verificar Inscripción</h1>
            <p><?= htmlspecialchars($club_nombre) ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="codigo">Código de Verificación</label>
                <input 
                    type="text" 
                    id="codigo" 
                    name="codigo" 
                    placeholder="1234"
                    maxlength="4"
                    required
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                >
            </div>
            
            <button type="submit" class="btn">Verificar Código</button>
        </form>
    </div>
</body>
</html>