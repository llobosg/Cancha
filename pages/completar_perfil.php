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
    // Si no hay ID válido en URL, intentar obtener del session
    if (isset($_SESSION['club_id'])) {
        $club_id = (int)$_SESSION['club_id'];
    } else {
        // Redirigir a dashboard si no hay club
        header('Location: ../pages/dashboard.php');
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
    header('Location: ../pages/dashboard.php');
    exit;
}

// Verificar que el socio pertenezca a este club
$stmt = $pdo->prepare("SELECT id_socio, nombre, email, celular, datos_completos FROM socios WHERE id_socio = ? AND id_club = ?");
$stmt->execute([$id_socio, $club_id]);
$socio = $stmt->fetch();

if (!$socio) {
    header('Location: ../pages/dashboard.php');
    exit;
}

// Guardar en sesión
$_SESSION['club_id'] = $club_id;

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $celular = trim($_POST['celular'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        
        // Validaciones
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
        
        // Actualizar perfil
        $stmt = $pdo->prepare("
            UPDATE socios 
            SET celular = ?, direccion = ?, datos_completos = 1, updated_at = NOW()
            WHERE id_socio = ? AND id_club = ?
        ");
        $stmt->execute([$celular, $direccion, $id_socio, $club_id]);
        
        // Redirigir al dashboard con mensaje de éxito
        $_SESSION['mensaje_exito'] = 'Perfil completado exitosamente';
        header('Location: ../pages/dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos actuales
$celular_actual = $socio['celular'] ?? '';
$direccion_actual = $socio['direccion'] ?? '';
$datos_completos = (bool)$socio['datos_completos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Perfil - <?= htmlspecialchars($club['nombre']) ?></title>
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
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
            <h1>Completar Mi Perfil</h1>
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
            
            <div class="btn-container">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Guardar Perfil
                </button>
            </div>
        </form>
    </div>
    
    <script>
        // Validación adicional en frontend
        document.querySelector('form').addEventListener('submit', function(e) {
            const celular = document.getElementById('celular').value.trim();
            const direccion = document.getElementById('direccion').value.trim();
            
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