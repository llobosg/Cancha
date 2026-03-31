<?php
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/config.php';
    session_start();

    try {
        $resultados = [];

        // 1. Si eres socio
        if (isset($_SESSION['id_socio'])) {
            $stmt = $pdo->prepare("
                SELECT 
                    t.id_torneo,
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
                    pt.id_torneo AS id_evento,
                    NULL AS id_socio
                FROM parejas_torneo pt
                JOIN torneos t ON pt.id_torneo = t.id_torneo
                WHERE (pt.id_socio_1 = ? OR pt.id_socio_2 = ?)
                AND t.estado IN ('abierto', 'en_progreso')
                ORDER BY t.fecha_inicio ASC
            ");
            $stmt->execute([$_SESSION['id_socio'], $_SESSION['id_socio']]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Si eres jugador temporal (por email en sesión)
        if (isset($_SESSION['user_email'])) {
            $email = $_SESSION['user_email'];
            $stmt2 = $pdo->prepare("
                SELECT 
                    t.id_torneo,
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
                    pt.id_torneo AS id_evento,
                    NULL AS id_socio
                FROM parejas_torneo pt
                JOIN torneos t ON pt.id_torneo = t.id_torneo
                JOIN jugadores_temporales jt ON (jt.id_jugador = pt.id_jugador_temp_1 OR jt.id_jugador = pt.id_jugador_temp_2)
                WHERE jt.email = ?
                AND t.estado IN ('abierto', 'en_progreso')
                ORDER BY t.fecha_inicio ASC
            ");
            $stmt2->execute([$email]);
            $temporales = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $resultados = array_merge($resultados, $temporales);
        }

        echo json_encode($resultados);

    } catch (Exception $e) {
        error_log("Error en get_mis_torneos.php: " . $e->getMessage());
        echo json_encode([]);
    }
?>