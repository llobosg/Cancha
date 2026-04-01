<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

error_log("=== INICIO get_tabla_datos.php ===");
error_log("GET recibido: " . print_r($_GET, true));
error_log("SESSION recibida: " . print_r($_SESSION, true));

// === VALIDAR SESIÓN Y CLUB ===
if (!isset($_SESSION['id_socio'])) {
    echo json_encode([]);
    exit;
}

// Si viene de modo club, asegurar que el socio pertenezca a ese club
if (isset($_SESSION['club_id']) && $_SESSION['club_id']) {
    $stmt = $pdo->prepare("SELECT 1 FROM socio_club WHERE id_socio = ? AND id_club = ? AND estado = 'activo'");
    $stmt->execute([$_SESSION['id_socio'], $_SESSION['club_id']]);
    if (!$stmt->fetch()) {
        // El socio no pertenece a este club → limpiar sesión
        unset($_SESSION['club_id']);
        unset($_SESSION['current_club']);
    }
}

$club_id = (int)$_SESSION['club_id'];

if (!isset($_SESSION['club_id']) || $_SESSION['club_id'] === null) {
    // Intentar obtener el club desde socio_club
    $stmt = $pdo->prepare("
        SELECT id_club FROM socio_club 
        WHERE id_socio = ? AND estado = 'activo' 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['id_socio']]);
    $club_id_row = $stmt->fetch();
    
    if ($club_id_row) {
        $_SESSION['club_id'] = $club_id_row['id_club'];
        error_log("🔄 Club ID recuperado desde socio_club: " . $_SESSION['club_id']);
    } else {
        error_log("⚠️ Socio no pertenece a ningún club activo");
        echo json_encode([]);
        exit;
    }
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
                    WHERE 
                        r.id_club = ? 
                        AND s.id_socio = ?
                        AND r.fecha >= CURDATE()
                    ORDER BY r.fecha ASC, r.hora_inicio ASC
                    LIMIT 50
                ";
                $params = [$_SESSION['club_id'], $_SESSION['id_socio']];
                error_log("🔍 Inscritos - Club ID: " . ($_SESSION['club_id'] ?? 'null'));
                error_log("🔍 Inscritos - Socio ID: " . ($_SESSION['id_socio'] ?? 'null'));
                error_log("🔍 Inscritos - SQL: " . $sql);
                error_log("🔍 Inscritos - Params: " . json_encode($params));
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
                        c.monto,
                        c.fecha_vencimiento,
                        c.estado,
                        c.comentario,
                        c.fecha_pago,
                        c.adjunto,
                        s.nombre AS nombre_socio,
                        s.alias,
                        s.rol,
                        COALESCE(r.id_club, e.id_club) AS id_club_evento,
                        cl.nombre AS club_nombre,
                        CASE
                            WHEN c.tipo_actividad = 'reserva' THEN rd.nombre
                            WHEN c.tipo_actividad = 'evento' THEN te.tipoevento
                            ELSE 'Sin detalle'
                        END as origen,
                        COALESCE(r.fecha, e.fecha) as fecha_evento
                    FROM cuotas c
                    INNER JOIN socios s ON c.id_socio = s.id_socio
                    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                    LEFT JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
                    INNER JOIN clubs cl ON cl.id_club = COALESCE(r.id_club, e.id_club)
                    WHERE 
                        c.id_socio = ? 
                        AND COALESCE(r.id_club, e.id_club) = ?
                    ORDER BY c.fecha_vencimiento DESC
                ";
                $params = [$_SESSION['id_socio'], $_SESSION['club_id']];
                break;

            case 'socios':
                if (!isset($_SESSION['club_id'])) {
                    echo json_encode([]);
                    return;
                }
                $stmt = $pdo->prepare("
                    SELECT 
                        s.id_socio AS id_evento,
                        s.alias,
                        s.email,
                        p.puesto AS posicion_jugador,
                        s.created_at
                    FROM socios s
                    LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
                    JOIN socio_club sc ON s.id_socio = sc.id_socio
                    WHERE sc.id_club = ? AND sc.estado = 'activo'
                    ORDER BY s.alias
                ");
                $stmt->execute([$_SESSION['club_id']]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            default:
                echo json_encode([]);
                exit;
        }

    } else {
        // === Modo individual ===
        switch ($filtro) {
            case 'inscritos':
                // Si no hay club_id, devolver vacío para 'inscritos'
                if ($filtro === 'inscritos' && (!isset($_SESSION['club_id']) || !$_SESSION['club_id'])) {
                    echo json_encode([]);
                    exit;
                }
                error_log("🔍 Inscritos - Club ID: " . ($_SESSION['club_id'] ?? 'null'));
                error_log("🔍 Inscritos - Socio ID: " . ($_SESSION['id_socio'] ?? 'null'));
                error_log("🔍 Inscritos - SQL: " . $sql);
                error_log("🔍 Inscritos - Params: " . json_encode($params));

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
    error_log("❌ Error en get_tabla_datos.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>