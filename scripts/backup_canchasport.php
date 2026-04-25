<?php
// scripts/backup_canchasport.php
// Backup automático de archivos críticos - Ejecutar vía cron cada 6 horas

define('BACKUP_DIR', __DIR__ . '/../backups');
define('MAX_BACKUPS', 7); // Mantener últimos 7 backups

$archivos_criticos = [
    '../pages/recinto_dashboard.php',
    '../pages/reservar_cancha.php',
    '../api/mover_reserva.php',
    '../api/canchaboard.php',
    '../api/gestion_reservas.php',
    '../includes/reserva_mailer.php',
    '../includes/brevo_mailer.php',
    '../includes/config.php'
];

// Crear directorio de backup si no existe
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$backup_path = BACKUP_DIR . "/backup_$timestamp";

if (!is_dir($backup_path)) {
    mkdir($backup_path, 0755, true);
}

$log = [];
$log[] = "=== Backup iniciado: $timestamp ===";

foreach ($archivos_criticos as $archivo) {
    $src = __DIR__ . '/' . $archivo;
    $dst = $backup_path . '/' . basename($archivo);
    
    if (file_exists($src)) {
        if (copy($src, $dst)) {
            $log[] = "✅ Copiado: " . basename($archivo);
        } else {
            $log[] = "❌ Error al copiar: " . basename($archivo);
        }
    } else {
        $log[] = "⚠️ No existe: $archivo";
    }
}

// Guardar log
file_put_contents($backup_path . '/backup.log', implode("\n", $log));

// Limpiar backups antiguos
$backups = glob(BACKUP_DIR . '/backup_*');
rsort($backups); // Ordenar por fecha descendente

if (count($backups) > MAX_BACKUPS) {
    $a_borrar = array_slice($backups, MAX_BACKUPS);
    foreach ($a_borrar as $backup) {
        if (is_dir($backup)) {
            array_map('unlink', glob("$backup/*"));
            rmdir($backup);
            $log[] = "🗑️ Eliminado backup antiguo: " . basename($backup);
        }
    }
}

// Output para cron
echo implode("\n", $log) . "\n";
echo "=== Backup completado ===\n";
?>