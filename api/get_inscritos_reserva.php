<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id_reserva = (int)($_GET['id_reserva'] ?? 0);
if (!$id_reserva) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT rp.id_socio, s.nombre, rp.estado
    FROM reservas_participantes rp
    JOIN socios s ON rp.id_socio = s.id_socio
    WHERE rp.id_reserva = ?
    ORDER BY rp.created_at ASC
");
$stmt->execute([$id_reserva]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>