<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $code = $_POST['code'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$code || strlen($code) !== 8) {
        throw new Exception('Código de invitación no válido');
    }
    if (!$nombre || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Nombre y email válidos son requeridos');
    }

    // Verificar que la invitación existe
    $stmt = $pdo->prepare("
        SELECT id_pareja, id_torneo 
        FROM parejas_torneo 
        WHERE codigo_pareja = ? AND estado = 'esperando_pareja'
    ");
    $stmt->execute([$code]);
    $pareja = $stmt->fetch();
    if (!$pareja) {
        throw new Exception('Invitación no válida o ya usada');
    }

    // Crear o obtener jugador temporal
    $stmt_check = $pdo->prepare("SELECT id_jugador FROM jugadores_temporales WHERE email = ?");
    $stmt_check->execute([$email]);
    $temp = $stmt_check->fetch();

    if ($temp) {
        $id_temporal = $temp['id_jugador'];
    } else {
        $token = hash('sha256', $email . time() . random_bytes(16));
        $pdo->prepare("
            INSERT INTO jugadores_temporales (nombre, email, token_registro)
            VALUES (?, ?, ?)
        ")->execute([$nombre, $email, $token]);
        $id_temporal = $pdo->lastInsertId();
    }

    // Completar la pareja
    $pdo->prepare("
        UPDATE parejas_torneo
        SET id_jugador_temp_2 = ?, estado = 'completa'
        WHERE id_pareja = ?
    ")->execute([$id_temporal, $pareja['id_pareja']]);

    echo json_encode(['success' => true, 'message' => '✅ ¡Pareja completada!']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>