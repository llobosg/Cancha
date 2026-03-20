<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Acceso denegado');
    }

    $id_torneo = $_GET['id_torneo'] ?? null;
    if (!$id_torneo || !is_numeric($id_torneo)) {
        echo json_encode([]);
        exit;
    }

    // Verificar que el torneo pertenezca al recinto
    $stmt_check = $pdo->prepare("SELECT id_torneo FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
    $stmt_check->execute([$id_torneo, $_SESSION['id_recinto']]);
    if (!$stmt_check->fetch()) {
        echo json_encode([]);
        exit;
    }

    // Consulta completa y segura
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            COALESCE(s1.alias, jt1.nombre, '#1') AS nombre1,
            COALESCE(s2.alias, jt2.nombre, '#2') AS nombre2,
            'pendiente' AS estado_valor
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
        WHERE pt.id_torneo = ?
        ORDER BY pt.id_pareja ASC
    ");
    $stmt->execute([$id_torneo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    error_log("Error en get_parejas_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
?>