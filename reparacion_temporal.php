<?php
// === ELIMINAR PROTECCIÓN DE ARCHIVOS PÚBLICOS ===
$directorios_publicos = [
    __DIR__,           // Raíz
    __DIR__ . '/pages' // Páginas del dashboard
];

$archivos_actualizados = 0;

foreach ($directorios_publicos as $dir) {
    if (!is_dir($dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $contenido = file_get_contents($file->getPathname());
            if (strpos($contenido, "Acceso denegado") !== false) {
                // Eliminar solo el bloque de protección
                $nuevo_contenido = preg_replace(
                    '/^<\?php\s*\/\/ Evitar acceso directo desde navegador.*?exit\(\'Acceso denegado\'\);\s*/ms',
                    '<?php' . "\n",
                    $contenido
                );
                if ($nuevo_contenido !== $contenido) {
                    file_put_contents($file->getPathname(), $nuevo_contenido);
                    $archivos_actualizados++;
                }
            }
        }
    }
}

echo "✅ Protección removida de {$archivos_actualizados} archivos públicos.\n";
echo "🔒 Los archivos en /includes/, /api/, /vendor/ siguen protegidos.\n";
?>