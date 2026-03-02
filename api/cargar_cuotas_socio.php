<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    $id_socio = $_SESSION['id_socio'];

    $stmt = $pdo->prepare("
        SELECT 
            c.id_cuota,
            c.monto,
            c.fecha_vencimiento,
            c.fecha_pago,
            c.estado,
            c.comentario,
            CASE 
                WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                ELSE 'Sin detalle'
            END as origen,
            COALESCE(r.fecha, e.fecha) as fecha_evento
        FROM cuotas c
        LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
        LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
        LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
        LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
        LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
        WHERE c.id_socio = ? AND c.estado = 'pendiente'
        ORDER BY c.fecha_vencimiento ASC
    ");

    $stmt->execute([$id_socio]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>