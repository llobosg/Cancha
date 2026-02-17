<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['club_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    $club_id = $_SESSION['club_id'];
    $filtro = $_GET['filtro'] ?? 'inscritos';

    $sql = "";
    switch ($filtro) {
        case 'inscritos':
            $sql = "
                SELECT 
                    r.fecha,
                    r.hora_inicio,
                    te.tipoevento as id_tipoevento,
                    r.id_club,
                    r.id_cancha,
                    r.monto_total as costo_evento,
                    s.nombre,
                    i.posicion_jugador,
                    c.monto as cuota_monto,
                    c.fecha_pago,
                    c.comentario,
                    r.id_reserva as id_evento
                FROM reservas r
                JOIN inscritos i ON r.id_reserva = i.id_evento
                JOIN socios s ON i.id_socio = s.id_socio
                LEFT JOIN cuotas c ON r.id_reserva = c.id_evento AND i.id_socio = c.id_socio
                JOIN canchas ca ON r.id_cancha = ca.id_cancha
                JOIN tipoeventos te ON ca.id_deporte = te.tipoevento
                WHERE r.id_club = ?
                ORDER BY r.fecha DESC, r.hora_inicio DESC
                LIMIT 50
            ";
            break;

        default:
            $sql = "
                SELECT 
                    r.fecha,
                    r.hora_inicio,
                    te.tipoevento as id_tipoevento,
                    r.id_club,
                    r.id_cancha,
                    r.monto_total as costo_evento,
                    '' as nombre,
                    '' as posicion_jugador,
                    NULL as cuota_monto,
                    NULL as fecha_pago,
                    '' as comentario,
                    r.id_reserva as id_evento
                FROM reservas r
                JOIN canchas ca ON r.id_cancha = ca.id_cancha
                JOIN tipoeventos te ON ca.id_deporte = te.tipoevento
                WHERE r.id_club = ?
                ORDER BY r.fecha DESC
                LIMIT 50
            ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$club_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>