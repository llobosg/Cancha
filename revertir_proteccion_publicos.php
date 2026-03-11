<?php
// === SCRIPT DE REVERSIÓN: Elimina protección solo en archivos públicos ===

$archivos_publicos = [
    // Páginas principales accesibles desde el navegador
    'index.php',
    'pages/dashboard_socio.php',
    'pages/registro_socio.php',
    'pages/completar_perfil.php',
    'pages/mantenedor_socios.php',
    'pages/reservar_cancha.php',
    'pages/eventos.php',
    'pages/login_email.php',
    'pages/pagar_cuota.php',
    'pages/verificar_socio.php',
    
    // APIs que se acceden vía AJAX (NO directamente en navegador)
    // → ¡NO revertir estos! Ya están protegidos correctamente.
];

$archivos_actualizados = 0;

foreach ($archivos_publicos as $archivo) {
    if (!file_exists($archivo)) continue;
    
    $contenido = file_get_contents($archivo);
    
    // Si tiene la protección, la eliminamos
    if (strpos($contenido, "Acceso denegado") !== false) {
        // Eliminar el bloque de protección
        $nuevo_contenido = preg_replace(
            '/^<\?php\s*\/\/ Evitar acceso directo desde navegador.*?exit\(\'Acceso denegado\'\);\s*/ms',
            '<?php' . "\n",
            $contenido
        );
        
        // Solo guardar si hubo cambio real
        if ($nuevo_contenido !== $contenido) {
            file_put_contents($archivo, $nuevo_contenido);
            $archivos_actualizados++;
        }
    }
}

echo "✅ Protección removida de {$archivos_actualizados} archivos públicos.\n";
echo "🔒 Los archivos sensibles (includes/, api/, vendor/) siguen protegidos.\n";
?>