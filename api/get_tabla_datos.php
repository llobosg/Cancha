<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

error_log("=== INICIO get_tabla_datos.php ===");
error_log("GET recibido: " . print_r($_GET, true));
error_log("SESSION recibida: " . print_r($_SESSION, true));

if (!isset($_SESSION['id_socio'])) {
    echo json_encode([]);
    exit;
}

$filtro = $_GET['filtro'] ?? 'inscritos';
$id_socio = $_SESSION['id_socio'];
$club_id = $_SESSION['club_id'] ?? null;

// Intentar recuperar club desde socio_club si no está en sesión (opcional, pero útil)
if (!$club_id) {
    $stmt = $pdo->prepare("SELECT id_club FROM socio_club WHERE id_socio = ? AND estado = 'activo' LIMIT 1");
    $stmt->execute([$id_socio]);
    $row = $stmt->fetch();
    if ($row) {
        $club_id = (int)$row['id_club'];
        $_SESSION['club_id'] = $club_id;
    }
}

// CORRECCIÓN: No salir si no hay club para filtros que pueden ser individuales (como reservas)
// Solo salimos si es un filtro estrictamente de club y no tenemos club ni socio individual válido para ese contexto
// Pero para 'reservas', siempre queremos mostrar algo si hay id_socio.

try {
    $result = [];

    switch ($filtro) {
        case 'inscritos':
            // Si no hay club, no hay inscritos de club
            if (!$club_id) { echo json_encode([]); exit; }
            
            $stmt_next = $pdo->prepare("
                SELECT id_reserva FROM reservas 
                WHERE id_club = ? AND fecha >= CURDATE() AND estado = 'confirmada'
                ORDER BY fecha, hora_inicio LIMIT 1
            ");
            $stmt_next->execute([$club_id]);
            $next = $stmt_next->fetch();
            if (!$next) { echo json_encode([]); exit; }

            $stmt = $pdo->prepare("
                SELECT
                    r.fecha, r.hora_inicio, te.tipoevento AS id_tipoevento,
                    COALESCE(ca.nombre_cancha, 'Cancha') AS origen, r.monto_total AS costo_evento,
                    s.alias AS nombre, p.puesto AS posicion_jugador,
                    c.monto AS cuota_monto, c.monto AS monto, c.comentario, c.estado, c.fecha_pago, i.id_inscrito
                FROM reservas r
                JOIN inscritos i ON r.id_reserva = i.id_evento AND i.tipo_actividad = 'reserva'
                JOIN socios s ON i.id_socio = s.id_socio
                LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
                LEFT JOIN cuotas c ON r.id_reserva = c.id_evento AND i.id_socio = c.id_socio AND c.tipo_actividad = 'reserva'
                JOIN canchas ca ON r.id_cancha = ca.id_cancha
                JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                WHERE r.id_reserva = ?
                ORDER BY s.alias
            ");
            $stmt->execute([$next['id_reserva']]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'cuotas':
            // Si no hay club, no hay cuotas de club que mostrar
            if (!$club_id) { 
                echo json_encode([]); 
                exit; 
            }

            // Verificar si el usuario actual es responsable del club
            $es_responsable = false;
            if (isset($_SESSION['id_socio'])) {
                $stmt_rol = $pdo->prepare("SELECT es_responsable FROM socios WHERE id_socio = ?");
                $stmt_rol->execute([$_SESSION['id_socio']]);
                $rol_data = $stmt_rol->fetch();
                $es_responsable = ($rol_data && $rol_data['es_responsable'] == 1);
            }

            $sql = "";
            $params = [];

            if ($es_responsable) {
                // === LÓGICA PARA RESPONSABLE: Ver TODAS las cuotas del club ===
                // Filtramos solo por el ID del club, sin restringir por id_socio
                $sql = "
                    SELECT
                        COALESCE(r.fecha, e.fecha) AS fecha, r.hora_inicio,
                        COALESCE(te.tipoevento, ete.tipoevento) AS id_tipoevento,
                        COALESCE(rd.nombre, cl.nombre, 'Evento') AS origen,
                        COALESCE(r.monto_total, e.valor_cuota, 0) AS costo_evento,
                        s.alias AS nombre, p.puesto AS posicion_jugador,
                        c.monto AS cuota_monto, c.monto AS monto, c.comentario, c.estado, c.fecha_pago, c.id_cuota AS id_inscrito,
                        r.tipo_pago, 
                        r.valor_mes
                    FROM cuotas c
                    JOIN socios s ON c.id_socio = s.id_socio
                    LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
                    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                    LEFT JOIN clubs cl ON r.id_club = cl.id_club
                    LEFT JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    LEFT JOIN tipoeventos ete ON e.id_tipoevento COLLATE utf8mb4_unicode_ci = ete.id_tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE COALESCE(r.id_club, e.id_club) = ?
                    ORDER BY c.fecha_vencimiento DESC
                ";
                $params[] = $club_id;
                
                error_log("🔍 [API CUOTAS] Modo Responsable: Cargando TODAS las cuotas del club $club_id");
            } else {
                // === LÓGICA PARA SOCIO NORMAL: Ver solo SUS cuotas ===
                $sql = "
                    SELECT
                        COALESCE(r.fecha, e.fecha) AS fecha, r.hora_inicio,
                        COALESCE(te.tipoevento, ete.tipoevento) AS id_tipoevento,
                        COALESCE(rd.nombre, cl.nombre, 'Evento') AS origen,
                        COALESCE(r.monto_total, e.valor_cuota, 0) AS costo_evento,
                        s.alias AS nombre, p.puesto AS posicion_jugador,
                        c.monto AS cuota_monto, c.monto AS monto, c.comentario, c.estado, c.fecha_pago, c.id_cuota AS id_inscrito
                    FROM cuotas c
                    JOIN socios s ON c.id_socio = s.id_socio
                    LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
                    LEFT JOIN reservas r ON c.id_evento = r.id_reserva AND c.tipo_actividad = 'reserva'
                    LEFT JOIN eventos e ON c.id_evento = e.id_evento AND c.tipo_actividad = 'evento'
                    LEFT JOIN canchas ca ON r.id_cancha = ca.id_cancha
                    LEFT JOIN recintos_deportivos rd ON ca.id_recinto = rd.id_recinto
                    LEFT JOIN clubs cl ON r.id_club = cl.id_club
                    LEFT JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                    LEFT JOIN tipoeventos ete ON e.id_tipoevento COLLATE utf8mb4_unicode_ci = ete.id_tipoevento COLLATE utf8mb4_unicode_ci
                    WHERE c.id_socio = ? AND COALESCE(r.id_club, e.id_club) = ?
                    ORDER BY c.fecha_vencimiento DESC
                ";
                $params[] = $id_socio;
                $params[] = $club_id;
                
                error_log("🔍 [API CUOTAS] Modo Socio: Cargando solo cuotas del socio $id_socio en club $club_id");
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'reservas':
            // CORRECCIÓN PRINCIPAL AQUÍ:
            // Buscar reservas por id_socio, independientemente de si tienen id_club o no.
            // Esto muestra reservas individuales (id_club NULL) y de club.
            $stmt = $pdo->prepare("
                SELECT
                    r.fecha,
                    r.hora_inicio,
                    te.tipoevento AS id_tipoevento,
                    COALESCE(ca.nombre_cancha, 'Cancha') AS origen,
                    r.monto_total AS costo_evento,
                    '' AS nombre,
                    '' AS posicion_jugador,
                    NULL AS cuota_monto,
                    NULL AS monto,
                    '' AS comentario,
                    '' AS estado,
                    NULL AS fecha_pago,
                    r.id_reserva AS id_inscrito
                FROM reservas r
                JOIN canchas ca ON r.id_cancha = ca.id_cancha
                JOIN tipoeventos te ON ca.id_deporte COLLATE utf8mb4_unicode_ci = te.tipoevento COLLATE utf8mb4_unicode_ci
                WHERE r.id_socio = ? AND r.fecha >= CURDATE()
                ORDER BY r.fecha DESC
                LIMIT 50
            ");
            $stmt->execute([$id_socio]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'eventos':
            if (!$club_id) { echo json_encode([]); exit; }
            $stmt = $pdo->prepare("
                SELECT
                    e.fecha, e.hora AS hora_inicio, te.tipoevento AS id_tipoevento,
                    COALESCE(e.lugar, 'Evento') AS origen, e.valor_cuota AS costo_evento,
                    '' AS nombre, '' AS posicion_jugador,
                    NULL AS cuota_monto, NULL AS monto, e.comentario, '' AS estado,
                    NULL AS fecha_pago, e.id_evento AS id_inscrito
                FROM eventos e
                JOIN tipoeventos te ON e.id_tipoevento = te.id_tipoevento
                WHERE e.id_club = ? AND e.fecha >= CURDATE()
                ORDER BY e.fecha DESC
                LIMIT 50
            ");
            $stmt->execute([$club_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'socios':
            if (!$club_id) { echo json_encode([]); exit; }
            $stmt = $pdo->prepare("
                SELECT
                    NULL AS fecha, NULL AS hora_inicio, 'Socio' AS id_tipoevento, 'Club' AS origen, 0 AS costo_evento,
                    s.alias AS nombre, p.puesto AS posicion_jugador,
                    NULL AS cuota_monto, NULL AS monto, s.email AS comentario, '' AS estado,
                    s.created_at AS fecha_pago, s.id_socio AS id_inscrito
                FROM socios s
                LEFT JOIN puestos p ON s.id_puesto = p.id_puesto
                JOIN socio_club sc ON s.id_socio = sc.id_socio
                WHERE sc.id_club = ? AND sc.estado = 'activo'
                ORDER BY s.alias
            ");
            $stmt->execute([$club_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        default:
            echo json_encode([]);
            exit;
    }

    // Normalizar salida
    $output = [];
    foreach ($result as $row) {
        $comentario_completo = implode(' - ', array_filter([
            $row['estado'] ?? '',
            trim($row['comentario'] ?? ''),
            ($row['fecha_pago'] ? date('d/m', strtotime($row['fecha_pago'])) : '')
        ])) ?: '-';

        $output[] = [
            'fecha' => $row['fecha'] ?? null,
            'hora_inicio' => $row['hora_inicio'] ?? null,
            'id_tipoevento' => $row['id_tipoevento'] ?? '-',
            'origen' => $row['origen'] ?? '-',
            'costo_evento' => $row['costo_evento'] ?? 0,
            'nombre' => $row['nombre'] ?? '-',
            'posicion_jugador' => $row['posicion_jugador'] ?? '-',
            'cuota_monto' => $row['cuota_monto'] ?? 0,
            'monto' => $row['monto'] ?? 0,
            'comentario_completo' => $comentario_completo,
            'id_inscrito' => $row['id_inscrito'] ?? null,
            'estado' => $row['estado'] ?? '',
            'fecha_pago' => $row['fecha_pago'] ?? null,
            'comentario' => $row['comentario'] ?? ''
        ];
    }

    echo json_encode($output);

} catch (Exception $e) {
    error_log("❌ Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
?>