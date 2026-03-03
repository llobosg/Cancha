<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

session_start();

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    $filtro = $_GET['filtro'] ?? 'inscritos';
    $id_socio = $_SESSION['id_socio'];
    $sql = "";
    $params = [];

    // === Modo individual: solo mostrar datos del socio ===
    if (!isset($_SESSION['club_id'])) {
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
                        s.nombre,
                        i.posicion_jugador,
                        c.monto AS cuota_monto,          -- ✅ Alias único
                        c.fecha_pago,
                        c.comentario,
                        r.id_reserva AS id_evento
                    FROM reservas r
                    INNER JOIN inscritos i 
                        ON r.id_reserva = i.id_evento 
                        AND i.tipo_actividad = 'reserva'
                    INNER JOIN socios s 
                        ON i.id_socio = s.id_socio
                    LEFT JOIN cuotas c 
                        ON c.id_evento = r.id_reserva 
                        AND c.id_socio = s.id_socio 
                        AND c.tipo_actividad = 'reserva'   -- ✅ Condición clave
                    INNER JOIN canchas ca 
                        ON r.id_cancha = ca.id_cancha
                    INNER JOIN tipoeventos te 
                        ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE s.id_socio = ?
                    ORDER BY r.fecha DESC, r.hora_inicio DESC
                    LIMIT 50
                ";
                $params = [$id_socio];
                break;

            default:
                echo json_encode([]);
                exit;
        }
    } 
    // === Modo club: mostrar datos del club ===
    else {
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
                        s.nombre,
                        i.posicion_jugador,
                        c.monto AS cuota_monto,
                        c.fecha_pago,
                        c.comentario,
                        r.id_reserva AS id_evento
                    FROM reservas r
                    JOIN inscritos i ON r.id_reserva = i.id_evento
                    JOIN socios s ON i.id_socio = s.id_socio
                    LEFT JOIN cuotas c ON r.id_reserva = c.id_evento AND i.id_socio = c.id_socio
                    JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE r.id_club = ?
                    ORDER BY r.fecha DESC, r.hora_inicio DESC
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
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        '' AS comentario,
                        r.id_reserva AS id_evento
                    FROM reservas r
                    JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE r.id_club = ?
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
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        e.comentario,
                        e.id_evento
                    FROM eventos e
                    WHERE e.id_club = ?
                    ORDER BY e.fecha DESC
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
                        s.nombre,
                        s.rol AS posicion_jugador,
                        NULL AS cuota_monto,
                        NULL AS fecha_pago,
                        s.email AS comentario,
                        s.id_socio AS id_evento
                    FROM socios s
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
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (Exception $e) {
    error_log("Error en cargar_detalle_eventos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>