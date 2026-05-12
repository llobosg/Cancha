<?php
// pages/torneo_invite.php
require_once __DIR__ . '/../includes/config.php';

$id_torneo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code_pareja = $_GET['code'] ?? '';

$torneo = null;
$error_message = "";

if ($id_torneo > 0) {
    // CASO 1: QR General (Admin/Socio busca inscribirse o ver info)
    try {
        $stmt = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.id_torneo = ?");
        $stmt->execute([$id_torneo]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$torneo) die("<h3 style='text-align:center; color:red;'>❌ Torneo no encontrado</h3>");
    } catch (Exception $e) { die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>"); }

} else if (!empty($code_pareja)) {
    // CASO 2: Invitación Específica de Pareja (?code=...)
    try {
        $stmt_pareja = $pdo->prepare("SELECT pt.id_torneo, pt.codigo_pareja, t.slug, t.estado FROM parejas_torneo pt JOIN torneos t ON pt.id_torneo = t.id_torneo WHERE pt.codigo_pareja = ?");
        $stmt_pareja->execute([$code_pareja]);
        $pareja_data = $stmt_pareja->fetch(PDO::FETCH_ASSOC);

        if (!$pareja_data) die("<h3 style='text-align:center; color:red;'>❌ Código de invitación inválido o expirado</h3>");
        
        $stmt_torneo = $pdo->prepare("SELECT t.*, r.nombre as recinto_nombre FROM torneos t JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto WHERE t.id_torneo = ?");
        $stmt_torneo->execute([$pareja_data['id_torneo']]);
        $torneo = $stmt_torneo->fetch(PDO::FETCH_ASSOC);
        $_SESSION['invite_code'] = $code_pareja; // Guardar code para usarlo al registrarse
    } catch (Exception $e) { die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>"); }
} else {
    die("<h3 style='text-align:center; color:red;'>❌ Enlace inválido</h3>");
}

// Datos comunes
$fecha_inicio_obj = new DateTime($torneo['fecha_inicio']);
$fecha_display = $fecha_inicio_obj->format('d/m/Y');
$hora_display = $fecha_inicio_obj->format('H:i');
$valor_inscripcion = isset($torneo['valor']) && $torneo['valor'] !== null ? (float)$torneo['valor'] : 0.00;

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$slug_torneo = $torneo['slug'] ?? substr(md5($torneo['id_torneo']), 0, 8);
$link_invitacion = $protocol . $host . "/pages/torneo_inscripcion.php?slug=" . $slug_torneo;

$mensaje_whatsapp = urlencode(
    "🎾 ¡Hola! Te invitamos al torneo *" . htmlspecialchars($torneo['nombre']) . "* en " . htmlspecialchars($torneo['recinto_nombre']) . ".\n\n" .
    "📅 Fecha: " . $fecha_display . "\n⏰ Hora: " . $hora_display . "\n💰 Inscripción: $" . number_format($valor_inscripcion, 0, ',', '.') . " (por pareja)\n\n¡Inscríbete aquí!: " . $link_invitacion
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 400px; width: 100%; text-align: center; position: relative; }
        h2 { color: #071289; margin-bottom: 0.5rem; }
        p { color: #666; margin-bottom: 1.5rem; }
        .btn-inscribir { display: block; width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s; cursor: pointer; border: none; margin-top: 1rem; }
        .btn-inscribir:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        .info-torneo { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        .error-msg { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        
        /* Modal Registro Rápido */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 1rem; }
        .modal-content { background: white; padding: 2rem; border-radius: 16px; max-width: 400px; width: 100%; position: relative; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: #333; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .password-wrapper { position: relative; }
        .toggle-password-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #718096; }
        .close-modal { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; color: #999; }
    </style>
</head>
<body>

    <div class="card">
        <!-- Botón X para cerrar/volver -->
        <?php if (isset($_SESSION['id_recinto'])): ?>
            <a href="javascript:history.back()" style="position:absolute; top:15px; right:15px; color:#999; text-decoration:none; font-size:1.5rem;">&times;</a>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="error-msg"><h3>⚠️ <?= $error_message ?></h3></div>
        <?php else: ?>
            <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
            <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
            
            <div class="info-torneo">
                <strong>📅 Fecha:</strong> <?= $fecha_display ?><br>
                <strong>⏰ Hora:</strong> <?= $hora_display ?><br>
                <strong>💰 Valor:</strong> $<?= number_format($valor_inscripcion, 0, ',', '.') ?> (por pareja)<br>
                <?php if ($torneo['num_parejas_max']): ?>
                <strong>👥 Cupos:</strong> <?= $torneo['num_parejas_max'] ?> parejas
                <?php endif; ?>
            </div>

            <?php if (!empty($code_pareja)): ?>
                <!-- CASO: INVITACIÓN DE PAREJA -->
                <p style="font-size:0.9rem; color:#555;">Has sido invitado a completar tu pareja.</p>
                
                <?php if (isset($_SESSION['id_socio'])): ?>
                    <!-- Socio Logueado -->
                    <button class="btn-inscribir" onclick="aceptarInvitacionSocio()">✅ Aceptar Invitación</button>
                <?php else: ?>
                    <!-- No logueado: Opciones Híbridas -->
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <a href="../index.php?redirect=<?= urlencode('/pages/torneo_invite.php?code=' . $code_pareja) ?>" class="btn-inscribir" style="background:#fff; color:#667eea; border:2px solid #667eea;">🔐 Soy Socio (Iniciar Sesión)</a>
                        <button class="btn-inscribir" onclick="abrirModalRegistroRapido()">👤 Ingresar como Invitado</button>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- CASO: QR GENERAL (Admin/Socio busca inscribirse) -->
                
                <!-- QR Code Container -->
                <div id="qrcode" style="background:white; padding:15px; border-radius:12px; display:inline-block; margin-bottom:1.5rem; border:1px solid #eee;"></div>
                <p style="font-size:0.85rem; color:#888; margin-bottom:1rem;">Escanea para inscribirte</p>

                <!-- Botón WhatsApp -->
                <a href="https://wa.me/?text=<?= $mensaje_whatsapp ?>" target="_blank" class="btn-inscribir" style="margin-bottom:1rem;">
                    📱 Enviar Invitación por WhatsApp
                </a>

                <!-- Copiar Link -->
                <div style="display:flex; gap:10px; margin-top:1rem;">
                    <input type="text" value="<?= $link_invitacion ?>" readonly id="linkInput" style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px; font-size:0.85rem; background:#fafafa;">
                    <button onclick="copiarLink()" style="padding:10px 15px; background:#667eea; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">Copiar</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- MODAL REGISTRO RÁPIDO (INVITADO) -->
    <div id="modalRegistroRapido" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalRegistro()">&times;</span>
            <h3 style="color:#071289; margin-bottom:1rem;">👤 Registro Rápido</h3>
            <p style="font-size:0.85rem; color:#666; margin-bottom:1.5rem;">Ingresa tus datos para unirte a la pareja. Luego podrás completar tu perfil.</p>
            
            <form id="formRegistroRapido" onsubmit="registrarInvitado(event)">
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="nombre" required placeholder="Tu nombre">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" placeholder="+56 9...">
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
                        <span class="toggle-password-icon" onclick="togglePassword(this)">🙈</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmar Contraseña *</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" required placeholder="Repite contraseña">
                        <span class="toggle-password-icon" onclick="togglePassword(this)">🙈</span>
                    </div>
                </div>
                <button type="submit" class="btn-inscribir">🚀 Unirme a la Pareja</button>
            </form>
        </div>
    </div>

    <script>
        // Generar QR solo si es caso general
        <?php if (empty($code_pareja)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("qrcode");
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: "<?= $link_invitacion ?>",
                    width: 200, height: 200,
                    colorDark : "#764ba2", colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
        <?php endif; ?>

        function copiarLink() {
            const copyText = document.getElementById("linkInput");
            copyText.select();
            navigator.clipboard.writeText(copyText.value).then(() => alert("Link copiado!"));
        }

        // Toggle Password Icon
        function togglePassword(icon) {
            const input = icon.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '👁️';
            } else {
                input.type = 'password';
                icon.textContent = '🙈';
            }
        }

        // Modal Logic
        function abrirModalRegistroRapido() {
            document.getElementById('modalRegistroRapido').style.display = 'flex';
        }
        function cerrarModalRegistro() {
            document.getElementById('modalRegistroRapido').style.display = 'none';
        }

        // Aceptar invitación si ya es socio
        async function aceptarInvitacionSocio() {
            const code = '<?= $code_pareja ?>';
            try {
                const res = await fetch('../api/aceptar_invitacion_pareja.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ codigo_pareja: code })
                });
                const data = await res.json();
                if (data.success) {
                    alert('✅ ¡Te has unido a la pareja! Revisa tu correo.');
                    window.location.href = '../dashboard_socio.php';
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (err) { alert('❌ Error de conexión'); }
        }

        // Registrar Invitado y Unirse
        async function registrarInvitado(e) {
            e.preventDefault();
            const form = e.target;
            const password = form.password.value;
            const confirm = form.confirm_password.value;

            if (password !== confirm) {
                alert('Las contraseñas no coinciden');
                return;
            }

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Procesando...';

            try {
                // 1. Registrar usuario básico (como socio individual/incompleto)
                const regRes = await fetch('../api/registro_rapido_invitado.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        nombre: form.nombre.value,
                        email: form.email.value,
                        telefono: form.telefono.value,
                        password: password
                    })
                });
                const regData = await regRes.json();

                if (!regData.success) throw new Error(regData.message);

                // 2. Ahora que está registrado y logueado, aceptar la invitación
                const accRes = await fetch('../api/aceptar_invitacion_pareja.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ codigo_pareja: '<?= $code_pareja ?>' })
                });
                const accData = await accRes.json();

                if (accData.success) {
                    alert('✅ ¡Registro exitoso y te has unido a la pareja!');
                    window.location.href = '../dashboard_socio.php';
                } else {
                    alert('❌ Error al unirte a la pareja: ' + accData.message);
                    btn.disabled = false;
                    btn.textContent = '🚀 Unirme a la Pareja';
                }

            } catch (err) {
                console.error(err);
                alert('❌ Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = '🚀 Unirme a la Pareja';
            }
        }
    </script>
</body>
</html>