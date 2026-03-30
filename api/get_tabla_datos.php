<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

error_log("=== INICIO get_tabla_datos.php ===");
error_log("GET recibido: " . print_r($_GET, true));
error_log("SESSION recibida: " . print_r($_SESSION, true));

if (!isset($_SESSION['id_socio'])) {
    error_log("❌ Acceso denegado: id_socio no encontrado en sesión");
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$filtro = $_GET['filtro'] ?? 'inscritos';
$club_id = $_SESSION['club_id'] ?? null;

error_log("Filtro solicitado: $filtro");
error_log("Club ID en sesión: " . ($club_id ?: 'NULL'));

if (!$club_id) {
    error_log("⚠️ Club ID no definido. Devolviendo array vacío.");
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
                error_log("🔍 Entrando al caso 'cuotas'");
                error_log("ID Socio: " . $_SESSION['id_socio']);
                error_log("ID Club activo: " . $_SESSION['club_id']);

                $sql = "
                    SELECT 
                        c.id_cuota,
                        c.monto,
                        c.fecha_vencimiento,
                        c.estado,
                        c.comentario,
                        c.fecha_pago,
                        c.adjunto,
                        s.nombre AS nombre_socio,
                        s.alias,
                        s.rol,
                        sc.id_club,
                        cl.nombre AS club_nombre,
                        CASE
                            WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                            WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                            ELSE 'Sin detalle'
                        END as origen,
                        COALESCE(r.fecha, e.fecha) as fecha_evento
                    FROM cuotas c
                    INNER JOIN socios s ON c.id_socio = s.id_socio
                    INNER JOIN socio_club sc ON s.id_socio = sc.id_socio AND sc.estado = 'activo'
                    INNER JOIN clubs cl ON sc.id_club = cl.id_club
                    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                    LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
                    WHERE 
                        c.id_socio = ? 
                        AND sc.id_club = ?
                    ORDER BY c.fecha_vencimiento DESC
                ";

                $params = [$_SESSION['id_socio'], $_SESSION['club_id']];
                error_log("SQL generado: " . $sql);
                error_log("Parámetros: " . json_encode($params));

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("✅ Cuotas encontradas: " . count($resultados));
                    echo json_encode($resultados);
                } catch (Exception $e) {
                    error_log("❌ Error en consulta de cuotas: " . $e->getMessage());
                    echo json_encode([]);
                }
                break;

            case 'socios':
                $sql = "
                    SELECT 
                        s.id_socio AS id_evento,
                        s.id_socio,
                        s.alias,                -- ✅ ya existe
                        s.nombre AS nombre_socio,
                        s.rol AS posicion_jugador,
                        s.email,
                        s.created_at
                    FROM socios s
                    WHERE s.id_club = ?
                    ORDER BY s.alias ASC
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

            case 'americanos':
                $sql = "
                    SELECT 
                        t.nombre AS torneo,
                        t.fecha_inicio AS fecha,
                        'americano' AS id_tipoevento,
                        '' AS id_club,
                        '' AS id_cancha,
                        t.valor AS costo_evento,
                        CONCAT('#', pt.id_pareja) AS nombre,
                        '' AS posicion_jugador,
                        0 AS lleva_cerveza,
                        pt.id_pareja AS id_inscrito,
                        0 AS cuota_monto,
                        NULL AS fecha_pago,
                        pt.estado AS comentario,
                        pt.id_torneo AS id_evento
                    FROM parejas_torneo pt
                    JOIN torneos t ON pt.id_torneo = t.id_torneo
                    WHERE (pt.id_socio_1 = ? OR pt.id_socio_2 = ?)
                    AND t.estado IN ('abierto', 'en_progreso')
                    ORDER BY t.fecha_inicio ASC
                ";
                $params = [$_SESSION['id_socio'], $_SESSION['id_socio']];
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