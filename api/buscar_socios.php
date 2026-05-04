<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
$id_recinto = (int)($_GET['id_recinto'] ?? 0);

if (!$id_recinto || strlen($q) < 2) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT id_socio, nombre, alias, email, id_convenio
    FROM socios 
    WHERE (nombre LIKE ? OR alias LIKE ? OR email LIKE ?)
    LIMIT 10
");
$search = "%$q%";
$stmt->execute([$search, $search, $search]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>