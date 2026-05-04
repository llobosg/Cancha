<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_socio = (int)$input['id_socio'];
$id_convenio = (int)$input['id_convenio'];
$id_recinto = $_SESSION['id_recinto'] ?? 0;

// Validar que el convenio pertenece al recinto actual
$stmt = $pdo->prepare("SELECT 1 FROM convenios WHERE id_convenio = ? AND id_recinto = ?");
$stmt->execute([$id_convenio, $id_recinto]);
if (!$stmt->fetch()) { echo json_encode(['success'=>false, 'message'=>'Convenio no válido']); exit; }

$pdo->prepare("UPDATE socios SET id_convenio = ? WHERE id_socio = ?")->execute([$id_convenio, $id_socio]);
echo json_encode(['success'=>true]);
?>