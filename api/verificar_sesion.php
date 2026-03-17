<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

echo json_encode([
    'socio' => isset($_SESSION['id_socio']) ? true : false
]);
?>