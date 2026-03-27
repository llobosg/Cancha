<?php
header('Content-Type: application/json');
// ... autenticación ...
$id = $_GET['id'] ?? 0;
// Devuelve partidos con resultados actuales
echo json_encode([
    ['id_partido' => 1, 'pareja1' => 'Ana & Luis', 'pareja2' => 'Carlos & María', 'set1_p1' => 6, 'set1_p2' => 4],
    // ...
]);
?>