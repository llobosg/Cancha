<?php
// pages/torneo_invite.php
require_once __DIR__ . '/../includes/config.php';

$id_torneo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code_pareja = $_GET['code'] ?? '';

$torneo = null;
$invitante_nombre = "";
$error_message = "";
$success_message = "";
$modo_invitacion = false; // true si es ?code=..., false si es ?id=...

if (!empty($code_pareja)) {
    $modo_invitacion = true;
    try {
        // === MODO INVITACIÓN DE PAREJA (SEGUNDO JUGADOR) ===
        
        // 1. Buscar la pareja y el torneo
        $stmt_pareja = $pdo->prepare("
            SELECT pt.id_torneo, pt.codigo_pareja, pt.id_socio_1, t.slug, t.estado, t.nombre as torneo_nombre
            FROM parejas_torneo pt
            JOIN torneos t ON pt.id_torneo = t.id_torneo
            WHERE pt.codigo_pareja = ?
        ");
        $stmt_pareja->execute([$code_pareja]);
        $pareja_data = $stmt_pareja->fetch(PDO::FETCH_ASSOC);

        if (!$pareja_data) {
            die("<h3 style='text-align:center; color:red;'>❌ Código de invitación inválido</h3>");
        }

        // 2. Obtener datos completos del torneo
        $stmt_torneo = $pdo->prepare("
            SELECT t.*, r.nombre as recinto_nombre 
            FROM torneos t
            JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
            WHERE t.id_torneo = ?
        ");
        $stmt_torneo->execute([$pareja_data['id_torneo']]);
        $torneo = $stmt_torneo->fetch(PDO::FETCH_ASSOC);
        
        // 3. Obtener nombre del invitante (Jugador Principal)
        if ($pareja_data['id_socio_1']) {
            $stmt_invitante = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
            $stmt_invitante->execute([$pareja_data['id_socio_1']]);
            $invitante_nombre = $stmt_invitante->fetchColumn() ?: "un jugador";
        } else {
            $invitante_nombre = "un jugador";
        }

        $_SESSION['invite_code'] = $code_pareja;
        $_SESSION['invite_torneo_id'] = $pareja_data['id_torneo'];

    } catch (Exception $e) {
        error_log("Error torneo_invite CODE: " . $e->getMessage());
        die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
    }
} elseif ($id_torneo > 0) {
    // === MODO INFORMACIÓN / QR DEL TORNEO (PRIMER JUGADOR O ADMIN) ===
    
    try {
        $stmt_torneo = $pdo->prepare("
            SELECT t.*, r.nombre as recinto_nombre 
            FROM torneos t
            JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
            WHERE t.id_torneo = ?
        ");
        $stmt_torneo->execute([$id_torneo]);
        $torneo = $stmt_torneo->fetch(PDO::FETCH_ASSOC);

        if (!$torneo) {
            die("<h3 style='text-align:center; color:red;'>❌ Torneo no encontrado</h3>");
        }
        
        // Generar Link de Inscripción Directo para el QR
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Usamos torneo_inscripcion.php con el ID para máxima compatibilidad
        $link_inscripcion = $protocol . $host . "/pages/torneo_inscripcion.php?id=" . $id_torneo;

    } catch (Exception $e) {
        error_log("Error torneo_invite ID: " . $e->getMessage());
        die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
    }
} else {
    die("<h3 style='text-align:center; color:red;'>❌ Enlace inválido</h3>");
}

// Datos comunes para ambos modos
if ($torneo) {
    $fecha_inicio_obj = new DateTime($torneo['fecha_inicio']);
    $fecha_display = $fecha_inicio_obj->format('d/m/Y');
    $hora_display = $fecha_inicio_obj->format('H:i');
    $valor_inscripcion = isset($torneo['valor']) && $torneo['valor'] !== null ? (float)$torneo['valor'] : 0.00;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo_invitacion ? 'Invitación - ' : 'Torneo - ' ?><?= htmlspecialchars($torneo['nombre']) ?></title>
    <!-- Librería QRCode.js solo necesaria si no es modo invitación -->
    <?php if (!$modo_invitacion): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 400px; width: 100%; text-align: center; position: relative; }
        h2 { color: #071289; margin-bottom: 0.5rem; }
        p { color: #666; margin-bottom: 1.5rem; }
        .btn-inscribir { display: block; width: 100%; padding: 1rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 1.1rem; transition: transform 0.2s; cursor: pointer; border: none; margin-top: 1rem; }
        .btn-inscribir:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        .info-torneo { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        .error-msg { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .success-msg { background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; padding: 1rem; }
        .modal-content { background: white; padding: 2rem; border-radius: 16px; max-width: 400px; width: 100%; position: relative; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: #333; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .password-wrapper { position: relative; }
        .toggle-password-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #718096; }
        .close-modal { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; color: #999; }
        
        /* QR Styles */
        #qrcode { margin: 1rem auto; padding: 10px; background: white; border: 1px solid #eee; display: inline-block; }
        .btn-copy { display: block; width: 100%; padding: 10px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 1rem; }
        .btn-copy:hover { background: #5a6fd6; }
        /* Botón Cerrar */
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
            z-index: 10;
            line-height: 1;
        }
        .close-btn:hover { color: #333; }
    </style>
</head>
<body>

    <div class="card">
        <!-- ✅ BOTÓN X PARA SALIR -->
        <button class="close-btn" onclick="window.history.back()" title="Volver">&times;</button>
        <?php if ($error_message): ?>
            <div class="error-msg"><h3>⚠️ <?= $error_message ?></h3></div>
        <?php elseif ($success_message): ?>
             <div class="success-msg">
                <h3>✅ Éxito</h3>
                <p><?= htmlspecialchars($success_message) ?></p>
                <button onclick="window.location.href='/pages/dashboard_socio.php'" class="btn-inscribir">Ir al Dashboard</button>
            </div>
        <?php else: ?>
            <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
            <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
            
            <div class="info-torneo">
                <strong>📅 Fecha:</strong> <?= $fecha_display ?><br>
                <strong>⏰ Hora:</strong> <?= $hora_display ?><br>
                <strong>💰 Valor:</strong> $<?= number_format($valor_inscripcion, 0, ',', '.') ?> (por pareja)<br>
            </div>

            <?php if ($modo_invitacion): ?>
                <!-- === VISTA: INVITADO (SEGUNDO JUGADOR) === -->
                <p style="font-size:1rem; color:#333; font-weight:500; margin-bottom:1.5rem;">
                    Has sido invitado por <strong><?= htmlspecialchars($invitante_nombre) ?></strong> a completar tu pareja.
                </p>
                
                <?php if (isset($_SESSION['id_socio'])): ?>
                    <!-- Usuario Logueado -->
                    <button class="btn-inscribir" onclick="aceptarInvitacionSocio()">✅ Aceptar Invitación</button>
                <?php else: ?>
                    <!-- No logueado: Opciones Híbridas -->
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <button class="btn-inscribir" style="background:#fff; color:#667eea; border:2px solid #667eea;" onclick="abrirModalLogin()">🔐 Soy Socio (Iniciar Sesión)</button>
                        <button class="btn-inscribir" onclick="abrirModalRegistroRapido()">👤 Ingresar como Invitado</button>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- === VISTA: QR DEL TORNEO (PRIMER JUGADOR) === -->
                <p style="font-size:0.9rem; color:#555;">Escanea este QR o copia el link para inscribirte como jugador principal:</p>
                
                <!-- Contenedor del QR -->
                <div id="qrcode"></div>

                <button class="btn-copy" onclick="copiarLink()">📋 Copiar Link de Inscripción</button>
                <input type="hidden" id="linkHidden" value="<?= $link_inscripcion ?>">
                
                <?php if (isset($_SESSION['id_socio'])): ?>
                    <p style="margin-top:1rem; font-size:0.85rem; color:#888;">¿Ya estás inscrito?</p>
                    <a href="/pages/dashboard_socio.php" style="color:#667eea; text-decoration:none; font-size:0.9rem;">Ir a mi Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- MODAL LOGIN SOCIO (Solo para modo invitación) -->
    <?php if ($modo_invitacion): ?>
    <div id="modalLoginSocio" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalLogin()">&times;</span>
            <h3 style="color:#071289; margin-bottom:1rem;">🔐 Iniciar Sesión</h3>
            <form id="formLoginSocio" onsubmit="loginSocio(event)">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" required placeholder="Tu contraseña">
                        <span class="toggle-password-icon" onclick="togglePassword(this)">🙈</span>
                    </div>
                </div>
                <button type="submit" class="btn-inscribir">Ingresar</button>
            </form>
        </div>
    </div>

    <!-- MODAL REGISTRO RÁPIDO (Solo para modo invitación) -->
    <div id="modalRegistroRapido" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalRegistro()">&times;</span>
            <h3 style="color:#071289; margin-bottom:1rem;">👤 Registro Rápido</h3>
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
    <?php endif; ?>

    <script>
        <?php if ($modo_invitacion): ?>
        const codePareja = '<?= $code_pareja ?>';

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

        // Modales
        function abrirModalLogin() { document.getElementById('modalLoginSocio').style.display = 'flex'; }
        function cerrarModalLogin() { document.getElementById('modalLoginSocio').style.display = 'none'; }
        function abrirModalRegistroRapido() { document.getElementById('modalRegistroRapido').style.display = 'flex'; }
        function cerrarModalRegistro() { document.getElementById('modalRegistroRapido').style.display = 'none'; }

        // Login Socio Existente
        async function loginSocio(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Verificando...';

            try {
                const res = await fetch('../api/login_socio_simple.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ email: form.email.value, password: form.password.value })
                });
                const data = await res.json();

                if (data.success) {
                    cerrarModalLogin();
                    aceptarInvitacionSocio(); // Proceder a aceptar invitación
                } else {
                    alert('❌ ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Ingresar';
                }
            } catch (err) {
                alert('❌ Error de conexión');
                btn.disabled = false;
                btn.textContent = 'Ingresar';
            }
        }

        // Aceptar Invitación (Lógica común para socio logueado)
        async function aceptarInvitacionSocio() {
            try {
                const res = await fetch('../api/aceptar_invitacion_pareja.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ codigo_pareja: codePareja })
                });
                const data = await res.json();
                
                if (data.success) {
                    window.location.href = '/pages/dashboard_socio.php';
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('❌ Error de conexión');
            }
        }

        // Registro Rápido Invitado
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
                // 1. Registrar usuario
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
                
                // Verificar si la respuesta es válida JSON
                const regText = await regRes.text();
                let regData;
                try {
                    regData = JSON.parse(regText);
                } catch (jsonErr) {
                    throw new Error("Error en servidor: " + regText.substring(0, 100));
                }

                if (!regData.success) throw new Error(regData.message);

                // 2. Aceptar invitación
                const accRes = await fetch('../api/aceptar_invitacion_pareja.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ codigo_pareja: codePareja })
                });
                const accData = await accRes.json();

                if (accData.success) {
                    alert('✅ ¡Registro exitoso y te has unido a la pareja!');
                    window.location.href = '/pages/dashboard_socio.php';
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
        <?php else: ?>
        // Modo QR: Generar QR y Copiar Link
        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("qrcode");
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: "<?= $link_inscripcion ?>",
                    width: 200,
                    height: 200,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });

        function copiarLink() {
            const link = document.getElementById("linkHidden").value;
            navigator.clipboard.writeText(link).then(() => {
                alert("✅ Link copiado al portapapeles!");
            }).catch(err => {
                console.error('Error al copiar:', err);
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>