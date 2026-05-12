<?php
// pages/torneo_invite.php
require_once __DIR__ . '/../includes/config.php';

$id_torneo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_torneo) {
    die("<h3 style='text-align:center; color:red;'>❌ ID de torneo inválido</h3>");
}

try {
    // 1. Obtener datos del torneo
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

    // ✅ CORRECCIÓN: Validar valor_inscripcion antes de usarlo
    $valor_inscripcion = isset($torneo['valor_inscripcion']) && $torneo['valor_inscripcion'] !== null 
                         ? (float)$torneo['valor_inscripcion'] 
                         : 0.00;

    // 2. Generar Link de Invitación Único
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $link_invitacion = $protocol . $host . "/pages/torneo_inscripcion.php?id=" . $id_torneo;
    
    // Mensaje para WhatsApp
    $mensaje_whatsapp = urlencode(
        "🎾 ¡Hola! Te invitamos al torneo *" . htmlspecialchars($torneo['nombre']) . "* en " . htmlspecialchars($torneo['recinto_nombre']) . ".\n\n" .
        "📅 Fecha: " . date('d/m/Y', strtotime($torneo['fecha_inicio'])) . "\n" .
        "🏆 Categoría: " . ($torneo['categoria'] ?? 'Abierto') . "\n" .
        "💰 Inscripción: $" . number_format($valor_inscripcion, 0, ',', '.') . "\n\n" .
        "¡Inscríbete aquí!: " . $link_invitacion
    );

} catch (Exception $e) {
    error_log("Error torneo_invite: " . $e->getMessage());
    die("<h3 style='text-align:center; color:red;'>❌ Error interno al cargar el torneo</h3>");
}
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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
            text-align: center;
            position: relative;
        }
        .header {
            /* ✅ Degradado Morado/Azul Oscuro solicitado */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1rem;
        }
        .header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .content {
            padding: 2rem 1.5rem;
        }
        
        /* ✅ QR Container con fondo blanco explícito */
        #qrcode {
            background: white; 
            padding: 15px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 1.5rem;
            border: 1px solid #eee;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
            font-size: 0.9rem;
            color: #555;
        }
        .info-box strong {
            color: #333;
        }
        
        /* ✅ Botón WhatsApp con Degradado y Márgenes Equilibrados */
        .btn-whatsapp {
            display: block;
            width: 100%; /* Ocupa todo el ancho disponible dentro del contenedor */
            padding: 12px;
            /* Degradado igual que el header */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            margin-top: 1rem;
            /* Centrado automático por ser block en un contenedor centrado */
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); /* Sombra morada */
        }
        
        .copy-link-box {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
            /* Asegura que este bloque tenga el mismo ancho visual que el botón */
            width: 100%; 
        }
        .copy-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.85rem;
            background: #fafafa;
        }
        .btn-copy {
            padding: 10px 15px;
            background: #667eea; /* Color sólido similar al inicio del degradado */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            white-space: nowrap; /* Evita que el texto se rompa */
        }
        .btn-copy:hover {
            background: #5a6fd6;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="header">
            <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
            <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
        </div>
        
        <div class="content">
            <!-- Info Básica -->
            <div class="info-box">
                <div><strong>📅 Inicio:</strong> <?= date('d/m/Y', strtotime($torneo['fecha_inicio'])) ?></div>
                <!-- ✅ Usamos la variable segura $valor_inscripcion -->
                <div><strong>💰 Inscripción:</strong> $<?= number_format($valor_inscripcion, 0, ',', '.') ?></div>
                <?php if ($torneo['num_parejas_max']): ?>
                <div><strong>👥 Cupos:</strong> <?= $torneo['num_parejas_max'] ?> parejas</div>
                <?php endif; ?>
            </div>

            <!-- QR Code Container -->
            <div id="qrcode"></div>
            
            <p style="font-size:0.85rem; color:#888; margin-bottom:1rem;">Escanea para inscribirte</p>

            <!-- Botón WhatsApp -->
            <a href="https://wa.me/?text=<?= $mensaje_whatsapp ?>" target="_blank" class="btn-whatsapp">
                📱 Enviar Invitación por WhatsApp
            </a>

            <!-- Copiar Link -->
            <div class="copy-link-box">
                <input type="text" class="copy-input" value="<?= $link_invitacion ?>" readonly id="linkInput">
                <button class="btn-copy" onclick="copiarLink()">Copiar</button>
            </div>
        </div>
    </div>

    <script>
        // === GENERAR QR CON COLORES MORADOS OSCUROS ===
        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("qrcode");
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: "<?= $link_invitacion ?>",
                    width: 200,
                    height: 200,
                    // ✅ Color Morado Oscuro (similar al final del degradado #764ba2)
                    colorDark : "#764ba2", 
                    // Fondo Blanco Puro
                    colorLight : "#ffffff", 
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });

        // === FUNCIÓN COPIAR LINK ===
        function copiarLink() {
            const copyText = document.getElementById("linkInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Para móviles
            navigator.clipboard.writeText(copyText.value).then(() => {
                const btn = document.querySelector('.btn-copy');
                const originalText = btn.textContent;
                btn.textContent = "✅ Copiado";
                btn.style.background = "#4CAF50";
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = "#667eea";
                }, 2000);
            }).catch(err => {
                console.error('Error al copiar:', err);
                alert("No se pudo copiar automáticamente. Por favor selecciona y copia manualmente.");
            });
        }
    </script>

</body>
</html>