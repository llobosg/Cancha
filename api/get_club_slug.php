<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$id_club = $_GET['id'] ?? null;
if (!$id_club) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
$stmt->execute([$id_club]);
$email = $stmt->fetchColumn();

if ($email) {
    $slug = substr(md5($id_club . $email), 0, 8);
    echo json_encode(['success' => true, 'slug' => $slug]);
} else {
    echo json_encode(['success' => false]);
}
?>