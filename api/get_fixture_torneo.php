<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode(['error' => 'ID de torneo no proporcionado']);
    exit;
}

try {
    // Verificar que el torneo pertenece al recinto
    $stmt = $pdo->prepare("
        SELECT nombre, deporte 
        FROM torneos 
        WHERE id_torneo = ? AND id_recinto = ?
    ");
    $stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
    $torneo = $stmt->fetch();
    if (!$torneo) {
        echo json_encode(['error' => 'Torneo no encontrado']);
        exit;
    }

    // Cargar partidos con nombres de parejas
    $stmt = $pdo->prepare("
        SELECT 
            p.id_partido,
            p.fecha_hora_programada,
            p.resultado_1,
            p.resultado_2,
            p.estado,
            s1.alias AS equipo1,
            s2.alias AS equipo2
        FROM partidos_torneo p
        LEFT JOIN inscritos i1 ON p.id_pareja_1 = i1.id_inscrito
        LEFT JOIN socios s1 ON i1.id_socio = s1.id_socio
        LEFT JOIN inscritos i2 ON p.id_pareja_2 = i2.id_inscrito
        LEFT JOIN socios s2 ON i2.id_socio = s2.id_socio
        WHERE p.id_torneo = ?
        ORDER BY p.fecha_hora_programada ASC
    ");
    $stmt->execute([$id_torneo]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'torneo_nombre' => $torneo['nombre'],
        'deporte' => $torneo['deporte'],
        'partidos' => $partidos
    ]);
} catch (Exception $e) {
    error_log("Error en get_fixture_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar el fixture']);
}
?>