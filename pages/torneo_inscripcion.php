<?php
// pages/torneo_inscripcion.php
require_once __DIR__ . '/../includes/config.php';

$slug = $_GET['slug'] ?? '';

if (!$slug) {
    die("<h3 style='text-align:center; color:red;'>❌ Enlace inválido</h3>");
}

try {
    // 1. Buscar torneo por slug
    $stmt = $pdo->prepare("
        SELECT t.*, r.nombre as recinto_nombre 
        FROM torneos t
        JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
        WHERE t.slug = ? AND t.estado IN ('abierto', 'borrador')
    ");
    $stmt->execute([$slug]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$torneo) {
        die("<h3 style='text-align:center; color:red;'>❌ Torneo no encontrado o inscripciones cerradas</h3>");
    }

    // 2. Verificar cupos
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ?");
    $stmt_count->execute([$torneo['id_torneo']]);
    $inscritos = (int)$stmt_count->fetchColumn();
    
    $cupo_lleno = ($inscritos >= $torneo['num_parejas_max']);

    // 3. Si el usuario está logueado, intentar inscripción automática
    if (isset($_SESSION['id_socio']) && !$cupo_lleno) {
        // Verificar si ya está inscrito
        $stmt_check = $pdo->prepare("SELECT 1 FROM parejas_torneo WHERE id_torneo = ? AND id_socio_1 = ?");
        $stmt_check->execute([$torneo['id_torneo'], $_SESSION['id_socio']]);
        
        if (!$stmt_check->fetch()) {
            // Inscribir automáticamente
            require_once __DIR__ . '/../api/inscribir_al_torneo.php'; 
            // Nota: La API espera POST con slug. Simulamos el POST aquí o redirigimos.
            // Mejor opción: Redirigir a una acción que maneje la inscripción silenciosa o mostrar confirmación.
            // Para simplificar y mantener la seguridad, mostraremos un botón grande de "Inscribirme" que hace fetch.
        }
    }

} catch (Exception $e) {
    error_log("Error torneo_inscripcion: " . $e->getMessage());
    die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 400px; width: 100%; text-align: center; }
        h2 { color: #071289; margin-bottom: 0.5rem; }
        p { color: #666; margin-bottom: 1.5rem; }
        .btn-inscribir {
            display: block; width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 1.1rem;
            transition: transform 0.2s; cursor: pointer; border: none;
        }
        .btn-inscribir:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        .info-torneo { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        .error-msg { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: #333; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($cupo_lleno): ?>
            <div class="error-msg">
                <h3>⚠️ Cupo Lleno</h3>
                <p>Lamentablemente, este torneo ha alcanzado su máximo de parejas.</p>
            </div>
        <?php else: ?>
            <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
            <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
            
            <div class="info-torneo">
                <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($torneo['fecha_inicio'])) ?><br>
                <strong>Cupos restantes:</strong> <?= $torneo['num_parejas_max'] - $inscritos ?> parejas<br>
                <strong>Valor:</strong> $<?= number_format($torneo['valor'], 0, ',', '.') ?>
            </div>

            <?php if (isset($_SESSION['id_socio'])): ?>
                <!-- Usuario Logueado -->
                <button class="btn-inscribir" onclick="inscribirseAuto()">✅ Inscríbete Ahora</button>
                <p style="font-size:0.8rem; margin-top:1rem;">Se usará tu perfil actual.</p>
            <?php else: ?>
                <!-- Usuario No Logueado -->
                <form id="formGuest" onsubmit="inscribirseGuest(event)">
                    <div class="form-group">
                        <label>Nombre Completo *</label>
                        <input type="text" name="nombre" required placeholder="Tu nombre">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="tu@email.com">
                    </div>
                    <button type="submit" class="btn-inscribir">📩 Inscribirse como Invitado</button>
                </form>
                <p style="font-size:0.8rem; margin-top:1rem; color:#888;">
                    ¿Ya tienes cuenta? <a href="../index.php?redirect=<?= urlencode('/pages/torneo_inscripcion.php?slug=' . $slug) ?>">Inicia Sesión</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        const slug = '<?= $slug ?>';

        async function inscribirseAuto() {
            try {
                const formData = new FormData();
                formData.append('slug', slug);
                
                const res = await fetch('../api/inscribir_al_torneo.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    alert('✅ ¡Inscripción exitosa! Revisa tu correo.');
                    window.location.href = data.redirect || '../dashboard_socio.php';
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error de conexión');
            }
        }

        async function inscribirseGuest(e) {
            e.preventDefault();
            const form = e.target;
            const nombre = form.nombre.value;
            const email = form.email.value;
            
            if (!nombre || !email) return alert('Completa los campos');

            try {
                const formData = new FormData();
                formData.append('slug', slug);
                formData.append('nombre', nombre);
                formData.append('email', email);
                
                const res = await fetch('../api/inscribir_al_torneo.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    alert('✅ ¡Inscripción exitosa! Te hemos enviado un correo con tus credenciales.');
                    window.location.href = '../index.php';
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error de conexión');
            }
        }
    </script>
</body>
</html>