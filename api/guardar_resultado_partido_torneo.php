<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $id_partido = $_POST['id_partido'] ?? null;
    $goles1 = $_POST['goles1'] ?? null;
    $goles2 = $_POST['goles2'] ?? null;

    if (!$id_partido || !is_numeric($goles1) || !is_numeric($goles2)) {
        throw new Exception('Datos incompletos');
    }

    // Verificar que el partido pertenece a un torneo del recinto
    $stmt = $pdo->prepare("
        SELECT p.id_partido
        FROM partidos_torneo p
        JOIN torneos t ON p.id_torneo = t.id_torneo
        WHERE p.id_partido = ? AND t.id_recinto = ?
    ");
    $stmt->execute([$id_partido, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        throw new Exception('Partido no encontrado');
    }

    // Actualizar resultado
    $pdo->prepare("
        UPDATE partidos_torneo 
        SET resultado_1 = ?, resultado_2 = ?, estado = 'jugado'
        WHERE id_partido = ?
    ")->execute([$goles1, $goles2, $id_partido]);

    echo json_encode(['success' => true, 'message' => 'Resultado guardado']);
} catch (Exception $e) {
    error_log("Error en guardar_resultado_partido_torneo.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>