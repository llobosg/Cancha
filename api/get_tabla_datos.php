<?php
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
    if ($filtro === 'inscritos') {
        // Obtener próximos eventos con inscritos
        $stmt = $pdo->prepare("
            SELECT 
                r.id_reserva AS id_evento,
                r.fecha,
                r.hora_inicio,
                te.tipoevento AS id_tipoevento,
                cl.nombre AS id_club,
                ca.id_cancha,
                r.monto_total AS costo_evento,
                s.nombre,
                i.posicion_jugador,
                i.id_inscrito,
                i.lleva_cerveza,
                i.id_socio
            FROM reservas r
            INNER JOIN canchas ca ON r.id_cancha = ca.id_cancha
            INNER JOIN clubs cl ON ca.id_recinto = cl.id_recinto
            INNER JOIN tipoeventos te ON r.id_tipoevento = te.id_tipoevento
            LEFT JOIN inscritos i ON r.id_reserva = i.id_reserva
            LEFT JOIN socios s ON i.id_socio = s.id_socio
            WHERE cl.id_club = ? AND r.fecha >= CURDATE()
            ORDER BY r.fecha, r.hora_inicio
            LIMIT 20
        ");
        $stmt->execute([$club_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($filtro === 'cuotas') {
        // Obtener cuotas pendientes/en revisión
        $stmt = $pdo->prepare("
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
        ");
        $stmt->execute([$club_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $data = [];
    }

    echo json_encode($data);

} catch (Exception $e) {
    error_log("Error en get_tabla_datos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar los datos']);
}
?>