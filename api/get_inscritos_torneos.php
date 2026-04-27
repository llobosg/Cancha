<?php
// api/get_inscritos_torneo.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$id_torneo = (int)($_GET['id_torneo'] ?? 0);
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            -- Jugador 1: Socio o Temporal
            COALESCE(NULLIF(s1.alias, ''), SUBSTRING_INDEX(s1.nombre, ' ', 1), jt1.nombre, 'J1') AS jugador1,
            -- Jugador 2: Socio o Temporal
            COALESCE(NULLIF(s2.alias, ''), SUBSTRING_INDEX(s2.nombre, ' ', 1), jt2.nombre, 'J2') AS jugador2,
            -- Nombre Pareja
            CONCAT(
                COALESCE(NULLIF(s1.alias, ''), SUBSTRING_INDEX(s1.nombre, ' ', 1), jt1.nombre), 
                ' & ', 
                COALESCE(NULLIF(s2.alias, ''), SUBSTRING_INDEX(s2.nombre, ' ', 1), jt2.nombre)
            ) AS nombre_pareja,
            -- Contacto (Email de quien sea que tenga)
            COALESCE(s1.email, jt1.email, s2.email, jt2.email, '-') AS contacto
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
        WHERE pt.id_torneo = ?
        ORDER BY pt.id_pareja ASC
    ");
    $stmt->execute([$id_torneo]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    error_log("Error get_inscritos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
?>