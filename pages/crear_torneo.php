<?php
require_once __DIR__ . '/../includes/config.php';
session_start();

// Verificar que es administrador de recinto
if (!isset($_SESSION['id_recinto'])) {
    header('Location: ../index.php');
    exit;
}

$id_recinto = $_SESSION['id_recinto'];

// Verificar que el recinto existe
$stmt_check = $pdo->prepare("SELECT id_recinto FROM recintos_deportivos WHERE id_recinto = ?");
$stmt_check->execute([$id_recinto]);
if (!$stmt_check->fetch()) {
    header('Location: ../index.php');
    exit;
}

// Modo edición
$modo_edicion = false;
$torneo_data = null;

if (isset($_GET['editar'])) {
    $id_torneo = (int)$_GET['editar'];
    $stmt = $pdo->prepare("
        SELECT * 
        FROM torneos 
        WHERE id_torneo = ? AND id_recinto = ?
    ");
    $stmt->execute([$id_torneo, $id_recinto]);
    $torneo_data = $stmt->fetch();
    if ($torneo_data) {
        $modo_edicion = true;
    } else {
        header('Location: recinto_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo_edicion ? 'Editar Torneo' : 'Crear Torneo Americano' ?> | CanchaSport</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(rgba(0,20,10,.4), rgba(0,30,15,.5)), url('../assets/img/cancha_pasto2.jpg') center/cover no-repeat fixed;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            padding: 2rem 1rem;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(0, 51, 102, 0.85);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #FFD700;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: bold;
            color: white;
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
            background: <?= $modo_edicion ? '#FFA500' : '#00cc66' ?>;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: <?= $modo_edicion ? '#e69500' : '#00aa55' ?>;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: #4ECDC4;
            text-decoration: none;
        }
        /* QR Section */
        #qrSection {
            margin-top: 2rem;
            text-align: center;
            display: none;
        }
        #qrCanvas {
            margin: 0 auto;
            background: white;
            padding: 10px;
            border-radius: 8px;
            width: 200px;
            height: 200px;
        }
        .copy-btn {
            margin-top: 1rem;
            background: #071289;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>🏆 <?= $modo_edicion ? 'Editar Torneo' : 'Crear Torneo Americano' ?></h2>
        <form id="crearTorneoForm">
            <input type="hidden" name="id_recinto" value="<?= $id_recinto ?>">
            <?php if ($modo_edicion): ?>
                <input type="hidden" name="id_torneo" value="<?= $torneo_data['id_torneo'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nombre">Nombre del torneo *</label>
                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($torneo_data['nombre'] ?? '') ?>" required placeholder="Ej: Torneo Primavera Pádel">
            </div>

            <div class="form-group">
                <label for="deporte">Deporte *</label>
                <select id="deporte" name="deporte" required>
                    <option value="padel" <?= ($torneo_data['deporte'] ?? 'padel') === 'padel' ? 'selected' : '' ?>>Pádel</option>
                    <option value="tenis" <?= ($torneo_data['deporte'] ?? '') === 'tenis' ? 'selected' : '' ?>>Tenis</option>
                    <option value="futbolito" <?= ($torneo_data['deporte'] ?? '') === 'futbolito' ? 'selected' : '' ?>>Futbolito</option>
                </select>
            </div>

            <div class="form-group">
                <label for="categoria">Categoría *</label>
                <select id="categoria" name="categoria" required>
                    <option value="masculina" <?= ($torneo_data['categoria'] ?? '') === 'masculina' ? 'selected' : '' ?>>Masculina</option>
                    <option value="femenina" <?= ($torneo_data['categoria'] ?? '') === 'femenina' ? 'selected' : '' ?>>Femenina</option>
                    <option value="mixta" <?= ($torneo_data['categoria'] ?? 'mixta') === 'mixta' ? 'selected' : '' ?>>Mixta</option>
                </select>
            </div>

            <div class="form-group">
                <label for="nivel">Nivel *</label>
                <select id="nivel" name="nivel" required>
                    <?php
                    $niveles = ['Sexta','Quinta','Cuarta','Tercera','Segunda','Primera'];
                    foreach ($niveles as $n): ?>
                        <option value="<?= $n ?>" <?= ($torneo_data['nivel'] ?? '') === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha_inicio">Fecha y hora inicio *</label>
                <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" 
                       value="<?= $torneo_data ? date('Y-m-d\TH:i', strtotime($torneo_data['fecha_inicio'])) : '' ?>" required>
            </div>

            <div class="form-group">
                <label for="fecha_fin">Fecha y hora fin *</label>
                <input type="datetime-local" id="fecha_fin" name="fecha_fin" 
                       value="<?= $torneo_data ? date('Y-m-d\TH:i', strtotime($torneo_data['fecha_fin'])) : '' ?>" required>
            </div>

            <div class="form-group">
                <label for="num_parejas_max">Cantidad máxima de parejas *</label>
                <input type="number" id="num_parejas_max" name="num_parejas_max" 
                       value="<?= (int)($torneo_data['num_parejas_max'] ?? 8) ?>" min="2" max="32" required>
            </div>

            <div class="form-group">
                <label>Valor por pareja ($)</label>
                <input type="number" name="valor" 
                       value="<?= (int)($torneo_data['valor'] ?? 0) ?>" min="0" required>
            </div>

            <div class="form-group">
                <label for="premios">Premios (opcional)</label>
                <input type="text" id="premios" name="premios" 
                       value="<?= htmlspecialchars($torneo_data['premios'] ?? '') ?>" placeholder="Ej: Trofeo + Vales">
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="publico" value="1" 
                           <?= ($torneo_data['publico'] ?? 0) ? 'checked' : '' ?>> Publicar en landing global
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <?= $modo_edicion ? 'Actualizar Torneo' : 'Crear Torneo' ?>
            </button>
        </form>

        <!-- QR Resultado (solo en creación) -->
        <div id="qrSection">
            <h3>✅ ¡Torneo creado!</h3>
            <p>Comparte este enlace o escanea el QR:</p>
            <div id="qrUrl" style="background:#f1f1f1; padding:0.5rem; border-radius:6px; margin:1rem 0; word-break:break-all; font-size:0.9rem;"></div>
            <canvas id="qrCanvas"></canvas>
            <button class="copy-btn" onclick="copiarEnlace()">📋 Copiar enlace</button>
        </div>

        <a href="recinto_dashboard.php" class="back-link">← Volver al dashboard</a>
    </div>

    <script>
        document.getElementById('crearTorneoForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            try {
                const res = await fetch('../api/<?= $modo_edicion ? 'editar_torneo.php' : 'crear_torneo.php' ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    <?php if (!$modo_edicion): ?>
                        // Mostrar QR solo en creación
                        const qrSection = document.getElementById('qrSection');
                        const qrUrlEl = document.getElementById('qrUrl');
                        qrUrlEl.textContent = result.qr_url;
                        qrSection.style.display = 'block';

                        if (typeof QRCode !== 'undefined') {
                            QRCode.toCanvas(document.getElementById('qrCanvas'), result.qr_url, { width: 200 }, function (error) {
                                if (error) console.error(error);
                            });
                        }
                        qrSection.scrollIntoView({ behavior: 'smooth' });
                    <?php else: ?>
                        alert('✅ Torneo actualizado');
                        window.location.href = 'recinto_dashboard.php';
                    <?php endif; ?>
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error al procesar el formulario');
            }
        });

        function copiarEnlace() {
            const url = document.getElementById('qrUrl').textContent;
            navigator.clipboard.writeText(url).then(() => {
                alert('✅ Enlace copiado al portapapeles');
            });
        }
    </script>
</body>
</html>