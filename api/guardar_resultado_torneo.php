<?php
// api/guardar_resultado_torneo.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $id_partido = $_POST['id_partido'] ?? null;
    $juegos1 = $_POST['juegos1'] ?? null; // Cambiado de goles1 a juegos1 para claridad
    $juegos2 = $_POST['juegos2'] ?? null;

    if (!$id_partido || !is_numeric($juegos1) || !is_numeric($juegos2)) {
        throw new Exception('Datos incompletos o inválidos');
    }

    // Verificar que el partido pertenece a un torneo del recinto
    $stmt = $pdo->prepare("
        SELECT p.id_partido, t.id_torneo
        FROM partidos_torneo p
        JOIN torneos t ON p.id_torneo = t.id_torneo
        WHERE p.id_partido = ? AND t.id_recinto = ?
    ");
    $stmt->execute([$id_partido, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        throw new Exception('Partido no encontrado o no pertenece a tu recinto');
    }

    // Actualizar resultado y marcar como finalizado
    $pdo->prepare("
        UPDATE partidos_torneo 
        SET juegos_pareja_1 = ?, juegos_pareja_2 = ?, estado = 'finalizado'
        WHERE id_partido = ?
    ")->execute([$juegos1, $juegos2, $id_partido]);

    echo json_encode(['success' => true, 'message' => '✅ Resultado guardado correctamente']);

} catch (Exception $e) {
    error_log("Error en guardar_resultado_torneo.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>