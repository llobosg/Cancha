<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$deporte = $_GET['deporte'] ?? null;

if ($deporte) {
    $stmt = $pdo->prepare("SELECT id_puesto, puesto FROM puestos WHERE deporte = ? ORDER BY puesto");
    $stmt->execute([$deporte]);
} else {
    $stmt = $pdo->prepare("SELECT id_puesto, puesto FROM puestos ORDER BY puesto");
    $stmt->execute();
}

$puestos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($puestos);
?>