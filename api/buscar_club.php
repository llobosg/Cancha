<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$q = $_GET['q'] ?? '';
if (!$q) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        id_club,
        nombre,
        deporte,
        ciudad,
        comuna,
        logo
    FROM clubs 
    WHERE email_verified = 1 
    AND (nombre LIKE ? OR ciudad LIKE ? OR comuna LIKE ?)
    LIMIT 10
");
$search = "%$q%";
$stmt->execute([$search, $search, $search]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar slug temporal (o guárdalo en DB al registrar)
foreach ($clubs as &$c) {
    $c['slug'] = substr(md5($c['id_club'] . $c['email_responsable']), 0, 8);
}
echo json_encode($clubs);