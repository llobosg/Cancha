<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

// Validar que sea admin de recinto
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

// Obtener nombre del torneo (para mostrar en el título)
$stmt_nombre = $pdo->prepare("SELECT nombre FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
$stmt_nombre->execute([$id_torneo, $_SESSION['id_recinto']]);
$torneo_nombre = $stmt_nombre->fetchColumn() ?: 'Torneo';

// Obtener posiciones
$stmt = $pdo->prepare("
    SELECT 
        pt.puntos_totales AS sets_ganados,
        COALESCE(s1.alias, jt1.nombre, '#1') AS nombre_pareja
    FROM parejas_torneo pt
    LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
    LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
    WHERE pt.id_torneo = ?
    ORDER BY pt.puntos_totales DESC, pt.id_pareja ASC
");
$stmt->execute([$id_torneo]);
$posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'torneo_nombre' => $torneo_nombre,
    'posiciones' => $posiciones
]);
?>