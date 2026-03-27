<?php
header('Content-Type: application/json');
// ... autenticación ...
echo json_encode([
    ['nombre' => 'Ana López', 'alias' => 'Ana', 'puntos' => 6],
    ['nombre' => 'Carlos Ruiz', 'alias' => 'Carlitos', 'puntos' => 3],
    // ...
]);
?>