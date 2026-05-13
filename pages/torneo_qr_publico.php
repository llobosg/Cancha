<?php
// pages/torneo_qr_publico.php
require_once __DIR__ . '/../includes/config.php';

$id_torneo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_torneo) {
    die("<h3 style='text-align:center; color:red;'>❌ ID de torneo inválido</h3>");
}

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

    // Generar Link de Inscripción Directo (usando ID para máxima compatibilidad)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Usamos ?id= porque torneo_inscripcion.php ahora soporta búsqueda por ID
    $link_inscripcion = $protocol . $host . "/pages/torneo_inscripcion.php?id=" . $id_torneo;

} catch (Exception $e) {
    error_log("Error torneo_qr: " . $e->getMessage());
    die("<h3 style='text-align:center; color:red;'>❌ Error interno</h3>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Inscripción - <?= htmlspecialchars($torneo['nombre']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 400px; width: 100%; text-align: center; }
        h2 { color: #071289; margin-bottom: 0.5rem; }
        p { color: #666; margin-bottom: 1.5rem; }
        .info-torneo { background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: left; font-size: 0.9rem; }
        #qrcode { margin: 1rem auto; padding: 10px; background: white; border: 1px solid #eee; display: inline-block; }
        .btn-copy { display: block; width: 100%; padding: 10px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 1rem; }
        .btn-copy:hover { background: #5a6fd6; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🎾 <?= htmlspecialchars($torneo['nombre']) ?></h2>
        <p><?= htmlspecialchars($torneo['recinto_nombre']) ?></p>
        
        <div class="info-torneo">
            <strong>📅 Fecha:</strong> <?= date('d/m/Y H:i', strtotime($torneo['fecha_inicio'])) ?><br>
            <strong>💰 Valor:</strong> $<?= number_format($torneo['valor'] ?? 0, 0, ',', '.') ?>
        </div>

        <p style="font-size:0.9rem; color:#555;">Escanea para inscribirte como jugador principal:</p>
        
        <!-- Contenedor del QR -->
        <div id="qrcode"></div>

        <button class="btn-copy" onclick="copiarLink()">📋 Copiar Link de Inscripción</button>
        <input type="hidden" id="linkHidden" value="<?= $link_inscripcion ?>">
    </div>

    <script>
        // Generar QR
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $link_inscripcion ?>",
            width: 200,
            height: 200,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        function copiarLink() {
            const link = document.getElementById("linkHidden").value;
            navigator.clipboard.writeText(link).then(() => {
                alert("✅ Link copiado al portapapeles!");
            }).catch(err => {
                console.error('Error al copiar:', err);
            });
        }
    </script>
</body>
</html>