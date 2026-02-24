<?php
require_once __DIR__ . '/../includes/config.php';

// Evitar problemas de headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determinar modo
$modo_individual = isset($_GET['id_socio']);
$id_socio = $_GET['id_socio'] ?? null;
$club_slug = $_GET['club'] ?? '';

// Validar parámetros
if ($modo_individual) {
    if (!$id_socio || !is_numeric($id_socio)) {
        header('Location: ../index.php');
        exit;
    }
    
    // Verificar que el socio exista y esté pendiente de verificación
    $stmt = $pdo->prepare("SELECT id_socio, email_verified FROM socios WHERE id_socio = ? AND email_verified = 0");
    $stmt->execute([$id_socio]);
    if (!$stmt->fetch()) {
        header('Location: ../index.php');
        exit;
    }
} else {
    // Modo club (como antes)
    if (!$club_slug || strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
        header('Location: ../index.php');
        exit;
    }
    
    // Validar club (tu lógica existente)
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
        header('Location: ../index.php');
        exit;
    }
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