<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$slug = $_GET['slug'] ?? '';
$code = $_GET['code'] ?? '';

if (!$slug || !$code || strlen($slug) !== 8 || strlen($code) !== 8) {
    header('Location: /index.php');
    exit;
}

// Buscar pareja pendiente + datos del recinto
$stmt = $pdo->prepare("
    SELECT 
        pt.id_socio_1, 
        s.alias AS alias_invitador,
        t.nombre AS nombre_torneo,
        t.categoria,
        t.nivel,
        t.fecha_inicio,
        rd.nombre AS nombre_recinto,
        rd.logorecinto
    FROM parejas_torneo pt
    JOIN socios s ON pt.id_socio_1 = s.id_socio
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    JOIN recintos_deportivos rd ON t.id_recinto = rd.id_recinto
    WHERE pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
");
$stmt->execute([$code]);
$pareja = $stmt->fetch();

if (!$pareja) {
    die('<h2 style="color:white;text-align:center;margin-top:3rem;">❌ Invitación no válida o ya usada</h2>');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación a Torneo | CanchaSport</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        body {
            background: linear-gradient(rgba(0,20,10,.4), rgba(0,30,15,.5)), 
                        url('/assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .submodal {
            background: white;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            overflow: hidden;
            position: relative;
        }
        .submodal-header {
            background: linear-gradient(135deg, #003366 0%, #0055aa 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }
        .logo-recinto {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            overflow: hidden;
        }
        .logo-recinto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .submodal-body {
            padding: 2rem;
            color: #333;
        }
        .torneo-info {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .torneo-info h2 {
            color: #003366;
            margin: 0.5rem 0;
            font-size: 1.4rem;
        }
        .torneo-meta {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin: 1.2rem 0;
            font-size: 0.95rem;
        }
        .btn-action {
            display: block;
            width: 100%;
            padding: 0.9rem;
            background: #00cc66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
        }
        .btn-action:hover {
            background: #00aa55;
        }
        .brand-footer {
            background: #f1f1f1;
            padding: 0.8rem;
            text-align: center;
            font-size: 0.85rem;
            color: #666;
            border-top: 1px solid #eee;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="submodal">
        <div class="submodal-header">
            <div class="logo-recinto">
                <?php if (!empty($pareja['logorecinto'])): ?>
                    <img src="/uploads/logos_recintos/<?= htmlspecialchars($pareja['logorecinto']) ?>" alt="Logo">
                <?php else: ?>
                    🏟️
                <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($pareja['nombre_recinto']) ?></h3>
            <p>Te invita a participar</p>
        </div>

        <div class="submodal-body">
            <div class="torneo-info">
                <h2>🤝 ¡Te han invitado!</h2>
                <p><strong><?= htmlspecialchars($pareja['alias_invitador']) ?></strong> te invita a jugar en:</p>
                <h3><?= htmlspecialchars($pareja['nombre_torneo']) ?></h3>
            </div>

            <div class="torneo-meta">
                <p><strong>📅 Fecha:</strong> <?= date('d/m H:i', strtotime($pareja['fecha_inicio'])) ?></p>
                <p><strong>🎯 Categoría:</strong> <?= ucfirst($pareja['categoria']) ?></p>
                <p><strong>🏅 Nivel:</strong> <?= $pareja['nivel'] ?></p>
            </div>

            <?php if (isset($_SESSION['id_socio'])): ?>
                <form id="completarParejaForm">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    <button class="btn-action" type="submit">✅ Aceptar Invitación</button>
                </form>
            <?php else: ?>
                <p style="text-align:center;margin:1.5rem 0;color:#e74c3c;font-weight:bold;">
                    Debes iniciar sesión para aceptar
                </p>
                <button class="btn-action" style="background:#071289;" onclick="window.location.href='/pages/login_email.php'">
                    Iniciar sesión
                </button>
                <button class="btn-action" style="background:#6c757d;" onclick="window.location.href='/pages/completar_perfil.php?modo=individual&tournament=<?= $slug ?>'">
                    Registrarme
                </button>
            <?php endif; ?>

            <a href="/index.php" class="back-link">← Volver al inicio</a>
        </div>

        <div class="brand-footer">
            Campeonato Americano creado en <strong>CanchaSport.com</strong>
        </div>
    </div>

    <script>
        document.getElementById('completarParejaForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/api/completar_pareja.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert('✅ ¡Pareja confirmada!');
                    window.location.href = '/torneo.php?slug=<?= $slug ?>';
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error al aceptar la invitación');
            }
        });
    </script>
</body>
</html>