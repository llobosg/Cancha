<?php
// api/get_parejas_torneo_admin.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar que sea admin/responsable del recinto
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
    // ✅ Consulta con JOINs para traer nombres de ambos jugadores
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            pt.codigo_pareja,
            pt.estado,
            -- Jugador 1 (principal)
            s1.nombre AS jugador1_nombre,
            s1.email AS jugador1_email,
            s1.celular AS jugador1_celular,
            -- Jugador 2 (invitado, puede ser NULL)
            s2.nombre AS jugador2_nombre,
            s2.email AS jugador2_email,
            -- Número de pareja (orden por fecha de inscripción)
            ROW_NUMBER() OVER (ORDER BY pt.created_at ASC) AS numero
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
    error_log("Error get_parejas_torneo_admin: " . $e->getMessage());
    echo json_encode(['error' => 'Error al cargar parejas']);
}
?>