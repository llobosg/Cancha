<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

$id_socio = $_GET['id_socio'] ?? $_SESSION['id_socio'];
if (!$id_socio) exit;

// Total puntos individuales
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(puntos_individual_1), 0) +
        COALESCE(SUM(puntos_individual_2), 0) AS total_puntos
    FROM ranking_padel
    WHERE id_socio_1 = ? OR id_socio_2 = ?
");
$stmt->execute([$id_socio, $id_socio]);
$total = $stmt->fetchColumn();

// Última posición (del último torneo)
$stmt = $pdo->prepare("
    SELECT r.posicion
    FROM (
        SELECT 
            rp.id_socio_1,
            rp.id_socio_2,
            ROW_NUMBER() OVER (ORDER BY SUM(rp.puntos_pareja) DESC) AS posicion
        FROM ranking_padel rp
        WHERE rp.id_torneo = (
            SELECT id_torneo FROM torneos WHERE estado = 'finalizado' ORDER BY fecha_fin DESC LIMIT 1
        )
        GROUP BY rp.id_socio_1, rp.id_socio_2
    ) r
    WHERE r.id_socio_1 = ? OR r.id_socio_2 = ?
");
$stmt->execute([$id_socio, $id_socio]);
$ultima = $stmt->fetchColumn() ?: null;

echo json_encode(['total_puntos' => (int)$total, 'ultima_posicion' => $ultima]);
?>