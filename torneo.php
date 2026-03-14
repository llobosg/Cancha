<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$slug = $_GET['slug'] ?? '';
if (!$slug || strlen($slug) !== 8) {
    http_response_code(404);
    die('Torneo no encontrado');
}

$stmt = $pdo->prepare("SELECT * FROM torneos WHERE slug = ? AND estado = 'abierto'");
$stmt->execute([$slug]);
$torneo = $stmt->fetch();

if (!$torneo) {
    http_response_code(404);
    die('Torneo cerrado o no existe');
}

// Guardar slug en sesión para redirigir después del login
if (!isset($_SESSION['id_socio'])) {
    $_SESSION['torneo_slug'] = $slug;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($torneo['nombre']) ?> | CanchaSport</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            background: linear-gradient(rgba(0,20,10,.4), rgba(0,30,15,.5)), 
                        url('/assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: rgba(0, 51, 102, 0.85);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            text-align: center;
        }
        h1 { color: #FFD700; margin-bottom: 1.5rem; }
        .btn-action {
            display: block;
            width: 100%;
            padding: 0.8rem;
            background: #00cc66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 <?= htmlspecialchars($torneo['nombre']) ?></h1>
        <p><strong>📅 Fecha:</strong> <?= date('d/m H:i', strtotime($torneo['fecha_inicio'])) ?></p>
        <p><strong>🎯 Categoría:</strong> <?= ucfirst($torneo['categoria']) ?></p>
        <p><strong>🏅 Nivel:</strong> <?= $torneo['nivel'] ?></p>

        <?php if (isset($_SESSION['id_socio'])): ?>
            <button class="btn-action" id="btnInscribirme">Inscribirme</button>

            <script>
            document.getElementById('btnInscribirme').addEventListener('click', async () => {
                const slug = '<?= $slug ?>';
                const res = await fetch(`/api/inscribir_jugador_individual.php?slug=${slug}`);
                const data = await res.json();
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert('❌ ' + (data.message || 'Error al inscribirse'));
                }
            });
            </script>
        <?php else: ?>
            <p>Debes estar registrado en CanchaSport para participar.</p>
            <button class="btn-action" onclick="window.location.href='/pages/login_email.php'">
                Iniciar sesión
            </button>
            <button class="btn-action" style="background:#071289;" onclick="window.location.href='/pages/completar_perfil.php?modo=individual&tournament=<?= $slug ?>'">
                Registrarme
            </button>
        <?php endif; ?>
    </div>
</body>
</html>