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
$id_socio = $_SESSION['id_socio'];
if (!$id_torneo) {
    echo json_encode([]);
    exit;
}

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
        (pt_mia.id_socio_1 = ? OR pt_mia.id_socio_2 = ? OR pt_mia.id_jugador_temp_1 = ? OR pt_mia.id_jugador_temp_2 = ?)
        AND pt_mia.id_pareja IN (p.id_pareja_1, p.id_pareja_2)
    )
    -- Rival info
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

// Buscar si el socio está como jugador temporal
$stmt_temp = $pdo->prepare("SELECT id_jugador FROM jugadores_temporales WHERE email = ?");
$stmt_temp->execute([$_SESSION['user_email']]);
$jugador_temp = $stmt_temp->fetch();
$id_jugador_temp = $jugador_temp ? $jugador_temp['id_jugador'] : null;

$stmt->execute([
    $id_socio, $id_socio,
    $id_jugador_temp, $id_jugador_temp,
    $id_torneo
]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>