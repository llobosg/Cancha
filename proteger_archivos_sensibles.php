<?php
// === SCRIPT DE PROTECCIÓN PARA CANCHASPORT ===
// Bloquea el acceso directo a archivos sensibles desde el navegador

$directorios_sensibles = [
    __DIR__ . '/includes',
    __DIR__ . '/api',
    __DIR__ . '/vendor'
];

$archivos_protegidos = [];

foreach ($directorios_sensibles as $dir) {
    if (!is_dir($dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $archivos_protegidos[] = $file->getPathname();
        }
    }
}

// Plantilla de protección
$proteccion_php = <<<PHP
<?php
// Evitar acceso directo desde navegador
if (basename(\$_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Acceso denegado');
}
PHP;

$total_actualizados = 0;

foreach ($archivos_protegidos as $archivo) {
    $contenido = file_get_contents($archivo);
    
    // Saltar si ya está protegido
    if (strpos($contenido, 'Acceso denegado') !== false) {
        continue;
    }
    
    // Si ya tiene <?php al inicio, insertar después
    if (strpos($contenido, '<?php') === 0) {
        $nuevo_contenido = "<?php\n// Evitar acceso directo desde navegador\nif (basename(\$_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {\n    http_response_code(403);\n    exit('Acceso denegado');\n}\n" . substr($contenido, 5);
    } else {
        // Si no, agregar al inicio
        $nuevo_contenido = $proteccion_php . "\n" . $contenido;
    }
    
    file_put_contents($archivo, $nuevo_contenido);
    $total_actualizados++;
}

echo "✅ Protección aplicada a {$total_actualizados} archivos PHP.\n";
echo "🔒 Ahora nadie puede acceder directamente a ellos desde el navegador.\n";
?>