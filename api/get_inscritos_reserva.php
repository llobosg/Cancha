<?php
// api/get_inscritos_reserva.php - VERSIÓN CORREGIDA (usa tabla 'inscritos')
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Validar sesión
if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_reserva = (int)($_GET['id_reserva'] ?? 0);
$id_socio = $_SESSION['id_socio'];
$club_id = $_SESSION['club_id'] ?? null;

if (!$id_reserva) {
    echo json_encode(['error' => 'ID de reserva requerido']);
    exit;
}

try {
    // === CONSULTA CORREGIDA: Usa tabla 'inscritos' (NO 'reservas_participantes') ===
    $stmt = $pdo->prepare("
        SELECT
            i.id_inscrito,
            i.id_socio,
            s.alias AS nombre,
            s.nombre AS nombre_completo,
            i.equipo,
            i.posicion_jugador,
            i.lleva_cerveza,
            r.fecha,
            r.hora_inicio,
            c.monto AS cuota_monto,
            c.estado AS estado_cuota,
            c.comentario
        FROM reservas r
        JOIN inscritos i ON r.id_reserva = i.id_evento AND i.tipo_actividad = 'reserva'
        JOIN socios s ON i.id_socio = s.id_socio
        LEFT JOIN cuotas c ON r.id_reserva = c.id_evento 
            AND i.id_socio = c.id_socio 
            AND c.tipo_actividad = 'reserva'
        WHERE r.id_reserva = ?
        ORDER BY s.alias ASC
    ");
    
    $stmt->execute([$id_reserva]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalizar salida para el frontend
    $output = [];
    foreach ($inscritos as $row) {
        $output[] = [
            'id_inscrito' => $row['id_inscrito'],
            'id_socio' => $row['id_socio'],
            'nombre' => $row['nombre'] ?? $row['nombre_completo'] ?? 'Sin nombre',
            'equipo' => $row['equipo'] ?? '-',
            'posicion' => $row['posicion_jugador'] ?? '-',
            'lleva_cerveza' => (bool)$row['lleva_cerveza'],
            'cuota_monto' => (float)($row['cuota_monto'] ?? 0),
            'estado_cuota' => $row['estado_cuota'] ?? 'pendiente',
            'comentario' => $row['comentario'] ?? ''
        ];
    }
    
    echo json_encode($output);
    
} catch (PDOException $e) {
    error_log("❌ Error get_inscritos_reserva: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
?>