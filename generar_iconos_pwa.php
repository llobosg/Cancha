<?php
// generar_iconos_pwa.php
// Ejecuta este script una vez para crear los íconos circulares de Cancha

$iconos = [
    192 => 'assets/icons/icon-192.png',
    512 => 'assets/icons/icon-512.png'
];

// Crear directorios si no existen
foreach ($iconos as $size => $path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

foreach ($iconos as $size => $output_path) {
    // Crear imagen PNG con fondo transparente
    $image = imagecreatetruecolor($size, $size);
    
    // Habilitar transparencia
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Color de fondo circular (#003366)
    $bg_color = imagecolorallocate($image, 0, 51, 102);
    
    // Dibujar círculo de fondo
    imagefilledellipse($image, $size/2, $size/2, $size, $size, $bg_color);
    
    // Texto del balón (usaremos un círculo blanco con borde para simular ⚽)
    $ball_color = imagecolorallocate($image, 255, 255, 255);
    $ball_size = $size * 0.6;
    
    // Círculo blanco principal
    imagefilledellipse($image, $size/2, $size/2, $ball_size, $ball_size, $ball_color);
    
    // Líneas del balón (simuladas con líneas cruzadas)
    $line_color = imagecolorallocate($image, 0, 0, 0);
    $line_width = max(2, $size / 64);
    
    // Línea horizontal
    imageline($image, 
        ($size - $ball_size)/2, 
        $size/2, 
        ($size + $ball_size)/2, 
        $size/2, 
        $line_color
    );
    
    // Línea vertical
    imageline($image, 
        $size/2, 
        ($size - $ball_size)/2, 
        $size/2, 
        ($size + $ball_size)/2, 
        $line_color
    );
    
    // Guardar imagen
    imagepng($image, $output_path);
    imagedestroy($image);
    
    echo "✅ Ícono generado: $output_path ($size x $size)\n";
}

echo "\n🎉 ¡Íconos PWA generados exitosamente!\n";
echo "📁 Ubicación: assets/icons/\n";
echo "🔄 Recuerda eliminar este script después de usarlo por seguridad.\n";
?>