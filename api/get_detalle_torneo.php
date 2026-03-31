<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$id_torneo = $_GET['id_torneo'] ?? null;
if (!$id_torneo) {
    echo json_encode(['torneo' => null]);
    exit;
}

try {
    // Datos del torneo
    $stmt = $pdo->prepare("SELECT id_torneo, nombre, fecha_inicio FROM torneos WHERE id_torneo = ?");
    $stmt->execute([$id_torneo]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$torneo) {
        echo json_encode(['torneo' => null]);
        exit;
    }

    // Mi posición
    $stmt = $pdo->prepare("
        SELECT puntos_totales 
        FROM parejas_torneo 
        WHERE id_torneo = ? AND (id_socio_1 = ? OR id_socio_2 = ?)
    ");
    $stmt->execute([$id_torneo, $_SESSION['id_socio'], $_SESSION['id_socio']]);
    $mi_posicion = $stmt->fetchColumn() ?: '—';

    // Resultados personales
    $email = $_SESSION['user_email'] ?? '';
    $stmt = $pdo->prepare("
    SELECT 
        p.juegos_pareja_1,
        p.juegos_pareja_2,
        CASE
            WHEN p.id_pareja_1 = pt_mia.id_pareja THEN 
                COALESCE(
                    CONCAT(s_rival1.alias, ' / ', s_rival2.alias),
                    CONCAT(jt_rival1.nombre, ' / ', jt_rival2.nombre),
                    'Pareja ' || p.id_pareja_2
                )
            ELSE 
                COALESCE(
                    CONCAT(s_rival1.alias, ' / ', s_rival2.alias),
                    CONCAT(jt_rival1.nombre, ' / ', jt_rival2.nombre),
                    'Pareja ' || p.id_pareja_1
                )
        END AS rival,
        CASE
            WHEN p.id_pareja_1 = pt_mia.id_pareja THEN p.juegos_pareja_1 > p.juegos_pareja_2
            ELSE p.juegos_pareja_2 > p.juegos_pareja_1
        END AS resultado
        FROM partidos_torneo p
        JOIN parejas_torneo pt_mia ON (
            (pt_mia.id_socio_1 = ? OR pt_mia.id_socio_2 = ? OR pt_mia.id_jugador_temp_1 = (SELECT id_jugador FROM jugadores_temporales WHERE email = ?) OR pt_mia.id_jugador_temp_2 = (SELECT id_jugador FROM jugadores_temporales WHERE email = ?))
            AND pt_mia.id_pareja IN (p.id_pareja_1, p.id_pareja_2)
        )
        LEFT JOIN parejas_torneo pt_rival ON (
            pt_rival.id_pareja = CASE WHEN p.id_pareja_1 = pt_mia.id_pareja THEN p.id_pareja_2 ELSE p.id_pareja_1 END
        )
        LEFT JOIN socios s_rival1 ON pt_rival.id_socio_1 = s_rival1.id_socio
        LEFT JOIN socios s_rival2 ON pt_rival.id_socio_2 = s_rival2.id_socio
        LEFT JOIN jugadores_temporales jt_rival1 ON pt_rival.id_jugador_temp_1 = jt_rival1.id_jugador
        LEFT JOIN jugadores_temporales jt_rival2 ON pt_rival.id_jugador_temp_2 = jt_rival2.id_jugador
        WHERE p.id_torneo = ? AND p.estado = 'finalizado'
        ORDER BY p.fecha_hora_programada ASC
    ");
    $stmt->execute([
        $_SESSION['id_socio'], $_SESSION['id_socio'],
        $email, $email,
        $id_torneo
    ]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tabla de posiciones completa
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            COALESCE(
                CONCAT(s1.alias, ' / ', s2.alias),
                CONCAT(jt1.nombre, ' / ', jt2.nombre),
                'Pareja ' || pt.id_pareja
            ) AS nombre_pareja,
            pt.puntos_totales AS sets_ganados
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
        WHERE pt.id_torneo = ?
        ORDER BY pt.puntos_totales DESC, pt.id_pareja ASC
    ");
    $stmt->execute([$id_torneo]);
    $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'torneo' => $torneo,
        'mi_posicion' => $mi_posicion,
        'resultados' => $resultados,
        'posiciones' => $posiciones
    ]);

    error_log("🔍 Detalle torneo ID: $id_torneo, socio: " . ($_SESSION['id_socio'] ?? 'null'));
    error_log("✅ Torneo: " . json_encode($torneo));
    error_log("✅ Mi posición: " . $mi_posicion);
    error_log("✅ Resultados: " . json_encode($resultados));
    error_log("✅ Posiciones: " . json_encode($posiciones));

} catch (Exception $e) {
    error_log("Error en get_detalle_torneo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
?>