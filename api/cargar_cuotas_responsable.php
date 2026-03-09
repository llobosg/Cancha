<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['club_id'])) {
        throw new Exception('Acceso no autorizado');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id_cuota,
            c.monto,
            c.fecha_vencimiento,
            c.estado,
            c.comentario,
            s.alias as nombre_socio,
            r.fecha as fecha_evento,
            rd.nombre as origen
        FROM cuotas c
        JOIN socios s ON c.id_socio = s.id_socio
        LEFT JOIN reservas r ON c.id_evento = r.id_reserva
        LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
        LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
        WHERE s.id_club = ? AND c.estado != 'pagado'
        ORDER BY c.estado DESC, c.fecha_vencimiento ASC
    ");
    $stmt->execute([$_SESSION['club_id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>