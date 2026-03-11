<?php
// === REPARAR ACCESO A ARCHIVOS PÚBLICOS ===
$archivos_publicos = [
    'index.php',
    'ceo_login.php',
    'login.php',
    'registro_socio.php',
    'completar_perfil.php',
    'verificar_socio.php',
    'pagar_cuota.php',
    
    // Páginas del dashboard
    'pages/dashboard_socio.php',
    'pages/mantenedor_socios.php',
    'pages/reservar_cancha.php',
    'pages/eventos.php',
    'pages/login_email.php',
    'pages/perfil_club.php',
    'pages/mantenedor_clubs.php',
    
    // APIs que se acceden vía AJAX (NO directamente, pero por si acaso)
    // → No revertimos estos; deben estar protegidos
];

$patron_proteccion = '/^<\?php\s*\/\/ Evitar acceso directo desde navegador.*?exit\(\'Acceso denegado\'\);\s*/ms';

foreach ($archivos_publicos as $archivo) {
    if (!file_exists($archivo)) continue;
    
    $contenido = file_get_contents($archivo);
    if (strpos($contenido, "Acceso denegado") !== false) {
        // Reemplazar solo el bloque de protección, manteniendo el resto
        $nuevo_contenido = preg_replace($patron_proteccion, '<?php' . "\n", $contenido);
        if ($nuevo_contenido !== $contenido) {
            file_put_contents($archivo, $nuevo_contenido);
            echo "✅ Restaurado: {$archivo}\n";
        }
    }
}

echo "🔧 Reparación completada. Ahora deberías poder acceder.\n";
?>