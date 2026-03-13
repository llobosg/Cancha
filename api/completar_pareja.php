<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Acceso no autorizado');
    }

    $code = $_POST['code'] ?? '';
    if (!$code) throw new Exception('Código no válido');

    $id_socio_2 = $_SESSION['id_socio'];

    // Verificar que la invitación existe y está disponible
    $stmt = $pdo->prepare("
        SELECT id_pareja, id_socio_1, id_torneo
        FROM parejas_torneo
        WHERE codigo_pareja = ? AND estado = 'esperando_pareja'
    ");
    $stmt->execute([$code]);
    $pareja = $stmt->fetch();
    if (!$pareja) throw new Exception('Invitación no válida o ya usada');

    if ($pareja['id_socio_1'] == $id_socio_2) {
        throw new Exception('No puedes invitarte a ti mismo');
    }

    // Completar la pareja
    $pdo->prepare("
        UPDATE parejas_torneo
        SET id_socio_2 = ?, estado = 'completa'
        WHERE id_pareja = ?
    ")->execute([$id_socio_2, $pareja['id_pareja']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>