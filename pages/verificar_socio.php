<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $codigo = $_POST['codigo'] ?? '';
    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido');
    }

    // Determinar modo
    $modo_individual = isset($_POST['id_socio']);
    
    if ($modo_individual) {
        $id_socio = $_POST['id_socio'] ?? null;
        if (!$id_socio || !is_numeric($id_socio)) {
            throw new Exception('Socio no válido');
        }
        
        // Verificar código para socio individual
        $stmt = $pdo->prepare("
            SELECT id_socio, email_verified 
            FROM socios 
            WHERE id_socio = ? AND verification_code = ? AND email_verified = 0
        ");
        $stmt->execute([$id_socio, $codigo]);
        $socio = $stmt->fetch();
        
        if (!$socio) {
            throw new Exception('Código incorrecto o ya verificado');
        }
        
        // Actualizar verificación
        $stmt = $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?");
        $stmt->execute([$id_socio]);
        
        $response_data = [
            'success' => true,
            'id_socio' => $id_socio,
            'club_slug' => '' // Sin club en modo individual
        ];
        
    } else {
        $club_slug = $_POST['club_slug'] ?? '';
        if (strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
            throw new Exception('Club no válido');
        }
        
        // Obtener id_club desde slug
        $stmt = $pdo->prepare("SELECT id_club, email_responsable FROM clubs WHERE email_verified = 1");
        $stmt->execute();
        $clubs = $stmt->fetchAll();
        $id_club = null;
        
        foreach ($clubs as $c) {
            if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
                $id_club = $c['id_club'];
                break;
            }
        }
        
        if (!$id_club) {
            throw new Exception('Club no encontrado');
        }
        
        // Verificar código para socio de club
        $stmt = $pdo->prepare("
            SELECT id_socio, email_verified 
            FROM socios 
            WHERE id_club = ? AND verification_code = ? AND email_verified = 0
        ");
        $stmt->execute([$id_club, $codigo]);
        $socio = $stmt->fetch();
        
        if (!$socio) {
            throw new Exception('Código incorrecto o ya verificado');
        }
        
        // Actualizar verificación
        $stmt = $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?");
        $stmt->execute([$socio['id_socio']]);
        
        $response_data = [
            'success' => true,
            'id_socio' => $socio['id_socio'],
            'club_slug' => $club_slug
        ];
    }
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Verificación código error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código - Cancha</title>
    <style>
        /* Tus estilos aquí */
        body {
            background: linear-gradient(rgba(0, 10, 20, 0.40), rgba(0, 15, 30, 0.50)),
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
            background: white;
            padding: 2rem;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            position: relative;
            margin: 0 auto;
        }
        
        .form-container h2 {
            text-align: center;
            color: #003366;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 10px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.65rem;
            background: #071289;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 0.7rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>🔐 Ingresar Código de Verificación</h2>
        
        <?php if ($_GET['error'] ?? ''): ?>
            <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        
        <form id="verificacionForm">
            <!-- Pasar parámetros según el modo -->
            <?php if ($modo_individual): ?>
                <input type="hidden" name="id_socio" value="<?= htmlspecialchars($id_socio) ?>">
            <?php else: ?>
                <input type="hidden" name="club_slug" value="<?= htmlspecialchars($club_slug) ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <input type="text" 
                       id="codigo" 
                       name="codigo" 
                       maxlength="4" 
                       placeholder="____"
                       required>
            </div>
            
            <button type="submit" class="btn-submit">Verificar Código</button>
            <label>Ingresa el código enviado al correo registrado</label>
        </form>
    </div>

    <script>
        document.getElementById('verificacionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const btn = e.submitter;
            const originalText = btn.innerHTML;
            
            btn.innerHTML = 'Verificando...';
            btn.disabled = true;
            
            try {
                const response = await fetch('../api/verificar_codigo_socio.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ¡Cuenta verificada exitosamente!');
                    window.location.href = '../dashboard_socio.php' + 
                        (data.club_slug ? '?id_club=' + data.club_slug : '');
                } else {
                    alert('❌ ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al verificar el código');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
        
        // Formatear input de código
        document.getElementById('codigo').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        });
    </script>
</body>
</html>