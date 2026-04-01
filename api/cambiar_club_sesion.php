<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$club_slug = $input['club_slug'] ?? '';

if (strlen($club_slug) !== 8 || !ctype_alnum($club_slug)) {
    echo json_encode(['success' => false]);
    exit;
}

// Buscar club por slug
$stmt = $pdo->prepare("SELECT id_club FROM clubs WHERE email_verified = 1");
$stmt->execute();
$clubs = $stmt->fetchAll();

$id_club = null;
foreach ($clubs as $c) {
    if (substr(md5($c['id_club'] . $c['email_responsable']), 0, 8) === $club_slug) {
        $id_club = $c['id_club'];
        break;
    }
}

if (!$id_club) {
    echo json_encode(['success' => false]);
    exit;
}

// Verificar que el socio pertenezca a ese club
$stmt = $pdo->prepare("SELECT 1 FROM socio_club WHERE id_socio = ? AND id_club = ?");
$stmt->execute([$_SESSION['id_socio'], $id_club]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

// Actualizar sesión
$_SESSION['club_id'] = $id_club;
$_SESSION['current_club'] = $club_slug;

echo json_encode(['success' => true]);
?>