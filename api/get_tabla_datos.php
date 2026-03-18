<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$filtro = $_GET['filtro'] ?? '';
$club_id = $_SESSION['club_id'] ?? null;

if (!$club_id) {
    echo json_encode([]);
    exit;
}

try {
    $filtro = $_GET['filtro'] ?? 'inscritos';
    $sql = "";
    $params = [];

    if (isset($_SESSION['club_id'])) {
        // === Modo club ===
        $club_id = $_SESSION['club_id'];
        switch ($filtro) {
            case 'inscritos':
                $sql = "
                    SELECT 
                        r.fecha,
                        r.hora_inicio,
                        te.tipoevento AS id_tipoevento,
                        r.id_club,
                        r.id_cancha,
                        r.monto_total AS costo_evento,
                        s.alias as nombre,
                        i.posicion_jugador,
                        i.lleva_cerveza,
                        i.id_inscrito,
                        c.monto AS cuota_monto,
                        c.fecha_pago,
                        c.comentario,
                        r.id_reserva AS id_evento,
                        s.id_socio
                    FROM reservas r
                    JOIN inscritos i ON r.id_reserva = i.id_evento AND i.tipo_actividad = 'reserva'
                    JOIN socios s ON i.id_socio = s.id_socio
                    LEFT JOIN cuotas c ON r.id_reserva = c.id_evento AND i.id_socio = c.id_socio AND c.tipo_actividad = 'reserva'
                    JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE r.id_club = ? 
                      AND (
                        r.fecha > CURDATE() 
                        OR (r.fecha = CURDATE() AND r.hora_inicio > CURTIME())
                      )
                    ORDER BY r.fecha ASC, r.hora_inicio ASC
                    LIMIT 50
                ";
                $params = [$club_id];
                break;

            case 'reservas':
                $sql = "
                    SELECT 
                        r.fecha,
                        r.hora_inicio,
                        te.tipoevento AS id_tipoevento,
                        r.id_club,
                        r.id_cancha,
                        r.monto_total AS costo_evento,
                        '' AS nombre,
                        '' AS posicion_jugador,
                        0 AS lleva_cerveza,
                        0 AS id_inscrito,
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        '' AS comentario,
                        r.id_reserva AS id_evento
                    FROM reservas r
                    JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE r.id_club = ? AND r.fecha >= CURDATE()
                    ORDER BY r.fecha DESC
                    LIMIT 50
                ";
                $params = [$club_id];
                break;

            case 'eventos':
                $sql = "
                    SELECT 
                        e.fecha,
                        e.hora,
                        e.id_tipoevento,
                        e.id_club,
                        e.lugar AS id_cancha,
                        e.valor_cuota AS costo_evento,
                        '' AS nombre,
                        '' AS posicion_jugador,
                        0 AS lleva_cerveza,
                        0 AS id_inscrito,
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        e.comentario,
                        e.id_evento
                    FROM eventos e
                    WHERE e.id_club = ? AND e.fecha >= CURDATE()
                    ORDER BY e.fecha DESC
                    LIMIT 50
                ";
                $params = [$club_id];
                break;

            case 'cuotas':
                $sql = "
                    SELECT 
                        c.id_cuota,
                        COALESCE(r.fecha, e.fecha) AS fecha_evento,
                        CASE 
                            WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                            WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                            ELSE 'Sin detalle'
                        END AS origen,
                        c.monto AS costo_evento,
                        s.nombre AS nombre_socio,
                        c.monto,
                        c.fecha_pago,
                        c.estado,
                        c.comentario
                    FROM cuotas c
                    INNER JOIN socios s ON c.id_socio = s.id_socio
                    INNER JOIN clubs cl ON s.id_club = cl.id_club
                    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                    LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
                    WHERE cl.id_club = ? AND c.estado IN ('pendiente', 'en_revision')
                    ORDER BY c.fecha_vencimiento DESC
                    LIMIT 50
                ";
                $params = [$club_id];
                break;

            case 'socios':
                $sql = "
                    SELECT 
                        NULL AS fecha,
                        NULL AS hora_inicio,
                        NULL AS id_tipoevento,
                        s.id_club,
                        NULL AS id_cancha,
                        NULL AS costo_evento,
                        s.nombre AS nombre_socio,
                        s.rol AS posicion_jugador,
                        0 AS lleva_cerveza,
                        0 AS id_inscrito,
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        s.email AS comentario,
                        s.id_socio AS id_evento,
                        s.id_socio,
                        cl.nombre AS club_nombre
                    FROM socios s
                    INNER JOIN clubs cl ON s.id_club = cl.id_club
                    WHERE s.id_club = ?
                    ORDER BY s.nombre ASC
                    LIMIT 50
                ";
                $params = [$club_id];
                break;

            default:
                echo json_encode([]);
                exit;
        }

    } else {
        // === Modo individual ===
        switch ($filtro) {
            case 'inscritos':
                $sql = "
                    SELECT 
                        r.fecha,
                        r.hora_inicio,
                        te.tipoevento AS id_tipoevento,
                        NULL AS id_club,
                        r.id_cancha,
                        r.monto_total AS costo_evento,
                        s.alias as nombre,
                        i.posicion_jugador,
                        i.lleva_cerveza,
                        i.id_inscrito,
                        c.monto AS cuota_monto,
                        c.fecha_pago,
                        c.comentario,
                        r.id_reserva AS id_evento,
                        s.id_socio
                    FROM reservas r
                    JOIN inscritos i ON r.id_reserva = i.id_evento AND i.tipo_actividad = 'reserva'
                    JOIN socios s ON i.id_socio = s.id_socio
                    LEFT JOIN cuotas c ON r.id_reserva = c.id_evento AND i.id_socio = c.id_socio AND c.tipo_actividad = 'reserva'
                    JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE s.id_socio = ?
                      AND (
                        r.fecha > CURDATE() 
                        OR (r.fecha = CURDATE() AND r.hora_inicio > CURTIME())
                      )
                    ORDER BY r.fecha ASC, r.hora_inicio ASC
                    LIMIT 50
                ";
                $params = [$_SESSION['id_socio']];
                break;

            default:
                echo json_encode([]);
                exit;
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultados);

} catch (Exception $e) {
    error_log("Error en get_tabla_datos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>