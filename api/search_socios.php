<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../includes/config.php';
$q = $_GET['q'] ?? '';
$stmt = $pdo->prepare("SELECT id_socio, nombre, email, celular FROM socios WHERE nombre LIKE ? OR email LIKE ? OR celular LIKE ? LIMIT 8");
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>