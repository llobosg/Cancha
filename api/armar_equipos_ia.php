<?php
// === SALIDA LIMPIA: evitar cualquier salida previa ===
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

session_start();

try {
    // Validar sesión y parámetros
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

    // Obtener los inscritos (mínimo 10, máximo 14)
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

    if (count($inscritos) < 10) {
        throw new Exception('Se requieren al menos 10 jugadores para armar equipos');
    }

    // Agrupar por habilidad
    $malos = [];
    $intermedios = [];
    $cracks = [];

    foreach ($inscritos as $socio) {
        switch ($socio['habilidad']) {
            case 'Avanzada': $cracks[] = $socio; break;
            case 'Intermedia': $intermedios[] = $socio; break;
            case 'Básica':
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

    // Ajustar a 7 jugadores por equipo
    while (count($equipoA) > 7) {
        $equipoB[] = array_pop($equipoA);
    }
    while (count($equipoB) > 7) {
        $equipoA[] = array_pop($equipoB);
    }

    // Guardar en base de datos
    $pdo->beginTransaction();

    // Limpiar equipos anteriores
    $stmt_clean = $pdo->prepare("
        DELETE je FROM jugadores_equipo je
        JOIN equipos_partido ep ON je.id_equipo = ep.id_equipo
        WHERE ep.id_reserva = ?
    ");
    $stmt_clean->execute([$id_reserva]);

    // Crear equipos
    $stmt_rojos = $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Rojos')");
    $stmt_rojos->execute([$id_reserva]);
    $id_rojos = $pdo->lastInsertId();

    $stmt_blancos = $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Blancos')");
    $stmt_blancos->execute([$id_reserva]);
    $id_blancos = $pdo->lastInsertId();

    // Asignar jugadores
    foreach ($equipoA as $jugador) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")
             ->execute([$id_rojos, $jugador['id_socio']]);
    }

    foreach ($equipoB as $jugador) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")
             ->execute([$id_blancos, $jugador['id_socio']]);
    }

    $pdo->commit();

    // Devolver respuesta JSON
    echo json_encode([
        'success' => true,
        'equipos' => [
            'rojos' => $equipoA,
            'blancos' => $equipoB
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Limpiar buffer de salida
ob_end_flush();
?>