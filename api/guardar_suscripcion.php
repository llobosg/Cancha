<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();
if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$subscription = $input['subscription'] ?? null;
$id_socio = (int)$input['id_socio'];

if (!$subscription || $id_socio !== $_SESSION['id_socio']) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO suscripciones_push (id_socio, endpoint, p256dh, auth)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        p256dh = VALUES(p256dh), auth = VALUES(auth)
    ");
    $stmt->execute([
        $id_socio,
        $subscription['endpoint'],
        $subscription['keys']['p256dh'] ?? '',
        $subscription['keys']['auth'] ?? ''
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error guardando suscripción: " . $e->getMessage());
    echo json_encode(['success' => false]);
}
?>