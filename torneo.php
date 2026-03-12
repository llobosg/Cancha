<?php
require_once __DIR__ . '/includes/config.php';

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($torneo['nombre']) ?> | CanchaSport</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(rgba(0,20,10,.4), rgba(0,30,15,.5)), url('assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
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
        h1 {
            color: #FFD700;
            margin-bottom: 1.5rem;
        }
        .info {
            background: rgba(255,255,255,0.15);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .form-group {
            margin: 1rem 0;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        .btn-submit {
            width: 100%;
            padding: 0.8rem;
            background: #00cc66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
        }
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

            <script>
            // Cargar socios disponibles dinámicamente
            document.addEventListener('DOMContentLoaded', async () => {
                const urlParams = new URLSearchParams(window.location.search);
                const slug = urlParams.get('slug');

                if (!slug) return;

                try {
                    const res = await fetch(`api/listar_socios_disponibles.php?slug=${slug}`);
                    const socios = await res.json();

                    const select = document.getElementById('id_socio_2');
                    select.innerHTML = '<option value="">-- Selecciona un socio --</option>';
                    
                    if (socios.length === 0) {
                        select.innerHTML = '<option value="">No hay socios disponibles</option>';
                        select.disabled = true;
                    } else {
                        socios.forEach(socio => {
                            const option = document.createElement('option');
                            option.value = socio.id_socio;
                            option.textContent = socio.alias;
                            select.appendChild(option);
                        });
                    }
                } catch (err) {
                    console.error('Error al cargar socios:', err);
                    document.getElementById('id_socio_2').innerHTML = '<option value="">Error al cargar</option>';
                }
            });
            </script>

            <div class="form-group">
                <label for="nombre_pareja">Nombre de la pareja (opcional)</label>
                <input type="text" id="nombre_pareja" name="nombre_pareja" placeholder="Ej: Los Crackers">
            </div>

            <button type="submit" class="btn-submit">Inscribir Pareja</button>
        </form>
    </div>

    <script>
        document.getElementById('inscripcionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            try {
                const res = await fetch('api/inscribir_pareja.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    alert('✅ ¡Inscripción confirmada!');
                    window.location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error al inscribirse');
            }
        });
    </script>
</body>
</html>