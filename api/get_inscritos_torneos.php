<?php
// api/get_inscritos_torneos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar admin/responsable
if (!isset($_SESSION['id_recinto']) || !in_array($_SESSION['recinto_rol'] ?? '', ['admin', 'responsable'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode(['error' => 'ID de torneo requerido']);
    exit;
}

try {
    // ✅ Consulta con JOINs para traer nombres y id_pareja
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            pt.codigo_pareja as nombre_pareja,
            ROW_NUMBER() OVER (ORDER BY pt.created_at ASC) as numero,
            s1.nombre as jugador1,
            s1.email as contacto,
            s2.nombre as jugador2
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        WHERE pt.id_torneo = ?
        ORDER BY pt.created_at ASC
    ");
    $stmt->execute([$id_torneo]);
    $parejas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($parejas);
    
} catch (Exception $e) {
    error_log("Error get_inscritos_torneos: " . $e->getMessage());
    echo json_encode(['error' => 'Error al cargar datos']);
}
?>