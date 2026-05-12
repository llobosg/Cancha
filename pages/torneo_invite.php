<?php
// pages/torneo_invite.php
require_once __DIR__ . '/../includes/config.php';

// Determinar si es acceso por ID (QR Admin) o por CODE (Invitación Pareja)
$id_torneo = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code_pareja = $_GET['code'] ?? '';

$torneo = null;
$error_message = "";

if ($id_torneo > 0) {
    // === CASO 1: Acceso por ID (QR del Admin) ===
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, r.nombre as recinto_nombre 
            FROM torneos t
            JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
            WHERE t.id_torneo = ?
        ");
        $stmt->execute([$id_torneo]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$torneo) {
            die("<h3 style='text-align:center; color:red;'>❌ Torneo no encontrado</h3>");
        }
        
        // Validar estado
        if (!in_array($torneo['estado'], ['abierto', 'borrador'])) {
            die("<h3 style='text-align:center; color:red;'>❌ Inscripciones cerradas para este torneo</h3>");
        }

    } catch (Exception $e) {
        error_log("Error torneo_invite ID: " . $e->getMessage());
        die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
    }

} else if (!empty($code_pareja)) {
    // === CASO 2: Acceso por CODE (Invitación de Pareja) ===
    // Buscar la pareja usando el código
    try {
        $stmt_pareja = $pdo->prepare("
            SELECT pt.id_torneo, pt.codigo_pareja, t.nombre as torneo_nombre, t.slug, t.estado
            FROM parejas_torneo pt
            JOIN torneos t ON pt.id_torneo = t.id_torneo
            WHERE pt.codigo_pareja = ?
        ");
        $stmt_pareja->execute([$code_pareja]);
        $pareja_data = $stmt_pareja->fetch(PDO::FETCH_ASSOC);

        if (!$pareja_data) {
            die("<h3 style='text-align:center; color:red;'>❌ Código de invitación inválido o expirado</h3>");
        }

        // Obtener datos completos del torneo para mostrar info
        $stmt_torneo = $pdo->prepare("
            SELECT t.*, r.nombre as recinto_nombre 
            FROM torneos t
            JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
            WHERE t.id_torneo = ?
        ");
        $stmt_torneo->execute([$pareja_data['id_torneo']]);
        $torneo = $stmt_torneo->fetch(PDO::FETCH_ASSOC);
        
        // Guardamos el code en sesión o hidden input para usarlo al inscribirse
        $_SESSION['invite_code'] = $code_pareja;

    } catch (Exception $e) {
        error_log("Error torneo_invite CODE: " . $e->getMessage());
        die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
    }

} else {
    die("<h3 style='text-align:center; color:red;'>❌ Enlace inválido</h3>");
}

// === DATOS COMUNES PARA AMBOS CASOS ===
// Extraer fecha y hora de fecha_inicio (formato datetime: 2026-05-17 13:00:00)
$fecha_inicio_obj = new DateTime($torneo['fecha_inicio']);
$fecha_display = $fecha_inicio_obj->format('d/m/Y');
$hora_display = $fecha_inicio_obj->format('H:i');

// Valor inscripción (columna 'valor')
$valor_inscripcion = isset($torneo['valor']) && $torneo['valor'] !== null 
                     ? (float)$torneo['valor'] 
                     : 0.00;

// Generar Link de Invitación Único (siempre basado en slug o id)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// Si tenemos un slug, usamos eso, sino usamos el ID
$slug_torneo = $torneo['slug'] ?? substr(md5($torneo['id_torneo']), 0, 8);
$link_invitacion = $protocol . $host . "/pages/torneo_inscripcion.php?slug=" . $slug_torneo;

// Mensaje para WhatsApp
$mensaje_whatsapp = urlencode(
    "🎾 ¡Hola! Te invitamos al torneo *" . htmlspecialchars($torneo['nombre']) . "* en " . htmlspecialchars($torneo['recinto_nombre']) . ".\n\n" .
    "📅 Fecha: " . $fecha_display . "\n" .
    "⏰ Hora: " . $hora_display . "\n" .
    "💰 Inscripción: $" . number_format($valor_inscripcion, 0, ',', '.') . " (por pareja)\n\n" .
    "¡Inscríbete aquí!: " . $link_invitacion
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <!-- Librería QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
    </style>
</head>
<body>
    <div class="card">
        <?php if ($error_message): ?>
            <div class="error-msg">
                <h3>⚠️ <?= $error_message ?></h3>
            </div>
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
                    <!-- Socio Logueado: Inscribe directamente como segunda pareja -->
                    <button class="btn-inscribir" onclick="inscribirseComoPareja()">✅ Aceptar Invitación</button>
                <?php else: ?>
                    <!-- No logueado: Ir a login/registro -->
                    <a href="../index.php?redirect=<?= urlencode('/pages/torneo_invite.php?code=' . $code_pareja) ?>" class="btn-inscribir">
                        🔐 Iniciar Sesión / Registrarse
                    </a>
                    <p style="font-size:0.8rem; margin-top:1rem;">Debes iniciar sesión para aceptar la invitación.</p>
                <?php endif; ?>

            <?php else: ?>
                <!-- CASO: QR GENERAL (Admin/Socio busca inscribirse) -->
                
                <!-- QR Code Container -->
                <div id="qrcode" style="background:white; padding:15px; border-radius:12px; display:inline-block; margin-bottom:1.5rem; border:1px solid #eee;"></div>
                <p style="font-size:0.85rem; color:#888; margin-bottom:1rem;">Escanea para inscribirte</p>

                <!-- Botón WhatsApp -->
                <a href="https://wa.me/?text=<?= $mensaje_whatsapp ?>" target="_blank" class="btn-inscribir" style="background:linear-gradient(135deg, #667eea, #764ba2); margin-bottom:1rem;">
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

    <script>
        // Generar QR solo si es caso general (no code_pareja)
        <?php if (empty($code_pareja)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("qrcode");
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: "<?= $link_invitacion ?>",
                    width: 200,
                    height: 200,
                    colorDark : "#764ba2",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
        <?php endif; ?>

        // Función copiar link
        function copiarLink() {
            const copyText = document.getElementById("linkInput");
            copyText.select();
            navigator.clipboard.writeText(copyText.value).then(() => {
                alert("Link copiado!");
            });
        }

        // Función para inscribirse como pareja invitada
        async function inscribirseComoPareja() {
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
            } catch (err) {
                console.error(err);
                alert('❌ Error de conexión');
            }
        }
    </script>
</body>
</html>