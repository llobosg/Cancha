<?php
require_once __DIR__ . '/includes/config.php';
session_start();

$slug = $_GET['slug'] ?? '';
if (!$slug || strlen($slug) !== 8) {
    http_response_code(404);
    die('Torneo no encontrado');
}

// Buscar torneo
$stmt = $pdo->prepare("SELECT * FROM torneos WHERE slug = ? AND estado = 'abierto'");
$stmt->execute([$slug]);
$torneo = $stmt->fetch();

if (!$torneo) {
    http_response_code(404);
    die('Torneo cerrado o no existe');
}

// Obtener club_slug desde id_recinto
$stmt_club = $pdo->prepare("
    SELECT c.id_club, c.email_responsable 
    FROM clubs c 
    JOIN recintos_deportivos r ON c.id_club = r.id_club 
    WHERE r.id_recinto = ?
");
$stmt_club->execute([$torneo['id_recinto']]);
$club_data = $stmt_club->fetch();

if (!$club_data) {
    header('Location: /index.php');
    exit;
}

$club_slug = substr(md5($club_data['id_club'] . $club_data['email_responsable']), 0, 8);

// Verificar sesión
if (!isset($_SESSION['id_socio'])) {
    $_SESSION['torneo_slug'] = $slug;
    header('Location: /pages/login_email.php?club=' . $club_slug);
    exit;
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
        label { color: white; display: block; margin-bottom: 0.4rem; font-weight: bold; }
        input, select { width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #ccc; background: white; color: #333; }
        .btn-submit { width: 100%; padding: 0.8rem; background: #00cc66; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 <?= htmlspecialchars($torneo['nombre']) ?></h1>
        
        <div class="info">
            <p><strong>📅 Fecha:</strong> <?= date('d/m H:i', strtotime($torneo['fecha_inicio'])) ?> - <?= date('d/m H:i', strtotime($torneo['fecha_fin'])) ?></p>
            <p><strong>🎯 Categoría:</strong> <?= ucfirst($torneo['categoria']) ?></p>
            <p><strong>🏅 Nivel:</strong> <?= $torneo['nivel'] ?></p>
            <p><strong>👥 Cupo:</strong> Máx. <?= $torneo['num_parejas_max'] ?> parejas</p>
            <?php if ($torneo['premios']): ?>
                <p><strong>🎁 Premios:</strong> <?= htmlspecialchars($torneo['premios']) ?></p>
            <?php endif; ?>
        </div>

        <form id="inscripcionForm">
            <input type="hidden" name="slug" value="<?= $slug ?>">
            
            <div class="form-group">
                <label for="id_socio_2">Selecciona a tu compañero/a *</label>
                <select id="id_socio_2" name="id_socio_2" required>
                    <option value="">-- Cargando socios --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="nombre_pareja">Nombre de la pareja (opcional)</label>
                <input type="text" id="nombre_pareja" name="nombre_pareja" placeholder="Ej: Los Crackers">
            </div>

            <button type="submit" class="btn-submit">Inscribir Pareja</button>
        </form>
    </div>

    <script>
        // Cargar socios
        document.addEventListener('DOMContentLoaded', async () => {
            const res = await fetch(`/api/listar_socios_disponibles.php?slug=<?= $slug ?>`);
            const socios = await res.json();
            const select = document.getElementById('id_socio_2');
            select.innerHTML = '<option value="">-- Selecciona un socio --</option>';
            socios.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id_socio;
                opt.textContent = s.alias;
                select.appendChild(opt);
            });
        });

        // Enviar formulario
        document.getElementById('inscripcionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            const res = await fetch('/api/inscribir_pareja.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            alert(result.success ? '✅ ¡Inscripción confirmada!' : '❌ ' + result.message);
            if (result.success) location.reload();
        });
    </script>
</body>
</html>