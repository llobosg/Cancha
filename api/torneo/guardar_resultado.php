<?php
// ... autenticación ...
$data = json_decode(file_get_contents('php://input'), true);
// Actualiza el campo en la BD
echo json_encode(['success' => true]);
?>