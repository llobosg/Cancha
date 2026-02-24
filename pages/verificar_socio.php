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

// Validar parámetros mínimos para mostrar el formulario
if ($modo_individual) {
    if (!$id_socio || !is_numeric($id_socio)) {
        header('Location: ../index.php');
        exit;
    }
} else {
    if (!$club_slug || strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
        header('Location: ../index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>🔐 Verificar Código - CanchaSport</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: 
                linear-gradient(rgba(0, 10, 20, 0.40), rgba(0, 15, 30, 0.50)),
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
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1.4rem;
            text-align: center;
            letter-spacing: 12px;
            color: #071289;
            background: #fafcff;
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
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

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.8rem;
            color: #003366;
            text-decoration: none;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .close-btn:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="../index.php" class="close-btn" title="Volver al inicio">×</a>
        
        <h2>🔐 Ingresar Código de Verificación</h2>

        <?php if (!empty($_GET['error'])): ?>
            <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <form id="verificacionForm">
            <!-- Parámetros según el modo -->
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
                       autocomplete="off"
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
                    // Redirigir al dashboard
                    if (data.club_slug) {
                        window.location.href = 'dashboard_socio.php?id_club=' + data.club_slug;
                    } else {
                        window.location.href = 'dashboard_socio.php';
                    }
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

        // Formatear entrada numérica
        document.getElementById('codigo').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        });

        // Enfocar el campo al cargar
        document.getElementById('codigo').focus();
    </script>
</body>
</html>