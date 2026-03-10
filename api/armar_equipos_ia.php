<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['club_id']) || empty($_POST['id_reserva'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_reserva = (int)$_POST['id_reserva'];
    $club_id = $_SESSION['club_id'];

    // Verificar que la reserva pertenece al club
    $stmt_check = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt_check->execute([$id_reserva, $club_id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Reserva no encontrada');
    }

    // Obtener los 14 inscritos
    $stmt_inscritos = $pdo->prepare("
        SELECT s.id_socio, s.alias, s.habilidad, i.posicion_jugador
        FROM inscritos i
        JOIN socios s ON i.id_socio = s.id_socio
        WHERE i.id_evento = ? AND i.tipo_actividad = 'reserva'
        ORDER BY s.alias
        LIMIT 14
    ");
    $stmt_inscritos->execute([$id_reserva]);
    $inscritos = $stmt_inscritos->fetchAll(PDO::FETCH_ASSOC);

    if (count($inscritos) < 14) {
        throw new Exception('Se requieren 14 jugadores para armar equipos');
    }

    // Agrupar por habilidad
    $malos = [];
    $intermedios = [];
    $cracks = [];

    foreach ($inscritos as $socio) {
        switch ($socio['habilidad']) {
            case 'Básica': $malos[] = $socio; break;
            case 'Intermedia': $intermedios[] = $socio; break;
            case 'Avanzada': $cracks[] = $socio; break;
            default: $malos[] = $socio; break;
        }
    }

    // Mezclar aleatoriamente
    shuffle($malos);
    shuffle($intermedios);
    shuffle($cracks);

    // Distribuir alternadamente
    $equipoA = [];
    $equipoB = [];

    $distribuir = function(&$grupo, &$eqA, &$eqB) {
        foreach ($grupo as $i => $jugador) {
            if ($i % 2 === 0) $eqA[] = $jugador;
            else $eqB[] = $jugador;
        }
    };

    $distribuir($cracks, $equipoA, $equipoB);
    $distribuir($intermedios, $equipoB, $equipoA); // Invertir para balancear
    $distribuir($malos, $equipoA, $equipoB);

    // Ajustar si hay desbalance
    while (count($equipoA) > 7) {
        $equipoB[] = array_pop($equipoA);
    }
    while (count($equipoB) > 7) {
        $equipoA[] = array_pop($equipoB);
    }

    // Guardar equipos
    $pdo->beginTransaction();
    
    // Limpiar equipos anteriores
    $stmt_clean = $pdo->prepare("
        DELETE je FROM jugadores_equipo je
        JOIN equipos_partido ep ON je.id_equipo = ep.id_equipo
        WHERE ep.id_reserva = ?
    ");
    $stmt_clean->execute([$id_reserva]);

    // Crear equipo Rojos
    $stmt_rojos = $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Rojos')");
    $stmt_rojos->execute([$id_reserva]);
    $id_rojos = $pdo->lastInsertId();

    // Crear equipo Blancos
    $stmt_blancos = $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Blancos')");
    $stmt_blancos->execute([$id_reserva]);
    $id_blancos = $pdo->lastInsertId();

    // Asignar jugadores a Rojos
    foreach ($equipoA as $jugador) {
        $stmt_jug = $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)");
        $stmt_jug->execute([$id_rojos, $jugador['id_socio']]);
    }

    // Asignar jugadores a Blancos
    foreach ($equipoB as $jugador) {
        $stmt_jug = $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)");
        $stmt_jug->execute([$id_blancos, $jugador['id_socio']]);
    }

    $pdo->commit();

    // Devolver equipos para el modal
    echo json_encode([
        'success' => true,
        'equipos' => [
            'rojos' => $equipoA,
            'blancos' => $equipoB
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>