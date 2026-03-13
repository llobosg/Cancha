<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$slug = $_GET['slug'] ?? '';
$code = $_GET['code'] ?? '';

if (!$slug || !$code) {
    header('Location: /index.php');
    exit;
}

// Buscar pareja
$stmt = $pdo->prepare("
    SELECT pt.id_socio_1, s.alias, t.nombre
    FROM parejas_torneo pt
    JOIN socios s ON pt.id_socio_1 = s.id_socio
    JOIN torneos t ON pt.id_torneo = t.id_torneo
    WHERE pt.codigo_pareja = ? AND pt.estado = 'esperando_pareja'
");
$stmt->execute([$code]);
$pareja = $stmt->fetch();

if (!$pareja) {
    die('Invitación no válida o ya usada');
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
        /* ... mismo estilo que torneo.php ... */
    </style>
</head>
<body>
    <div class="container">
        <h1>🤝 ¡Te han invitado!</h1>
        <p><strong><?= htmlspecialchars($pareja['alias']) ?></strong> te invita a jugar en:</p>
        <p><strong><?= htmlspecialchars($pareja['nombre']) ?></strong></p>

        <?php if (isset($_SESSION['id_socio'])): ?>
            <form id="completarParejaForm">
                <input type="hidden" name="code" value="<?= $code ?>">
                <button class="btn-action" type="submit">Aceptar Invitación</button>
            </form>
        <?php else: ?>
            <p>Debes iniciar sesión para aceptar.</p>
            <button class="btn-action" onclick="window.location.href='/pages/login_email.php'">Iniciar sesión</button>
            <button class="btn-action" style="background:#071289;" onclick="window.location.href='/pages/completar_perfil.php?modo=individual&tournament=<?= $slug ?>'">Registrarme</button>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('completarParejaForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await fetch('/api/completar_pareja.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(e.target))
            });
            const data = await res.json();
            if (data.success) {
                alert('✅ ¡Pareja confirmada!');
                window.location.href = '/torneo.php?slug=<?= $slug ?>';
            } else {
                alert('❌ ' + data.message);
            }
        });
    </script>
</body>
</html>