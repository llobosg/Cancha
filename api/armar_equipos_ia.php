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

    // Agrupar jugadores por posición y habilidad
    $posiciones = [
        'Arquero' => [],
        'Defensa' => [],      // Incluye "Atrás", "Central"
        'Medio' => [],
        'Lateral' => [],      // Incluye "Lateral izq", "Lateral der"
        'Delantero' => [],    // Incluye "Delantero izq", "Delantero der", "Adelante"
        'Jugador' => []       // Puede ir en cualquier posición
    ];

    $habilidades = ['Básica' => [], 'Intermedia' => [], 'Avanzada' => []];

    foreach ($inscritos as $socio) {
        // Clasificar por posición
        $pos = $socio['posicion_jugador'] ?? 'Jugador';
        if (strpos($pos, 'Arquero') !== false) {
            $posiciones['Arquero'][] = $socio;
        } elseif (strpos($pos, 'Defensa') !== false || strpos($pos, 'Atrás') !== false || strpos($pos, 'Central') !== false) {
            $posiciones['Defensa'][] = $socio;
        } elseif (strpos($pos, 'Medio') !== false) {
            $posiciones['Medio'][] = $socio;
        } elseif (strpos($pos, 'Lateral') !== false) {
            $posiciones['Lateral'][] = $socio;
        } elseif (strpos($pos, 'Delantero') !== false || strpos($pos, 'Adelante') !== false) {
            $posiciones['Delantero'][] = $socio;
        } else {
            $posiciones['Jugador'][] = $socio;
        }
        
        // Clasificar por habilidad
        $habilidades[$socio['habilidad']][] = $socio;
    }

    // Mezclar aleatoriamente
    foreach ($posiciones as &$grupo) shuffle($grupo);
    foreach ($habilidades as &$grupo) shuffle($grupo);

    // Distribuir arqueros primero (mínimo 1 por equipo)
    $equipoA = [];
    $equipoB = [];

    // Asignar arqueros
    if (!empty($posiciones['Arquero'])) {
        $equipoA[] = array_shift($posiciones['Arquero']);
        if (!empty($posiciones['Arquero'])) {
            $equipoB[] = array_shift($posiciones['Arquero']);
        }
    }

    // Si falta arquero en algún equipo, usar otras posiciones
    if (count($equipoA) == 0 && !empty($posiciones['Defensa'])) {
        $equipoA[] = array_shift($posiciones['Defensa']);
    }
    if (count($equipoB) == 0 && !empty($posiciones['Defensa'])) {
        $equipoB[] = array_shift($posiciones['Defensa']);
    }

    // Completar con otras posiciones en orden específico
    $orden_posiciones = ['Defensa', 'Medio', 'Lateral', 'Delantero', 'Jugador'];
    foreach ($orden_posiciones as $pos) {
        while (!empty($posiciones[$pos]) && (count($equipoA) < 7 || count($equipoB) < 7)) {
            if (count($equipoA) < 7) {
                $equipoA[] = array_shift($posiciones[$pos]);
            }
            if (!empty($posiciones[$pos]) && count($equipoB) < 7) {
                $equipoB[] = array_shift($posiciones[$pos]);
            }
        }
    }

    // Balancear habilidades (máximo 3 cracks por equipo)
    $cracksA = array_filter($equipoA, fn($j) => $j['habilidad'] === 'Avanzada');
    $cracksB = array_filter($equipoB, fn($j) => $j['habilidad'] === 'Avanzada');

    if (count($cracksA) > 3 && count($cracksB) < 3) {
        // Mover un crack de A a B
        foreach ($equipoA as $i => $jugador) {
            if ($jugador['habilidad'] === 'Avanzada') {
                $equipoB[] = $jugador;
                unset($equipoA[$i]);
                break;
            }
        }
        $equipoA = array_values($equipoA);
    }

    // Asegurar al menos 1 "Básico" por equipo
    $bajosA = array_filter($equipoA, fn($j) => $j['habilidad'] === 'Básica');
    $bajosB = array_filter($equipoB, fn($j) => $j['habilidad'] === 'Básica');

    if (empty($bajosA) && !empty($habilidades['Básica'])) {
        // Reemplazar un jugador de A con uno "Básico"
        $equipoA[0] = array_shift($habilidades['Básica']);
    }
    if (empty($bajosB) && !empty($habilidades['Básica'])) {
        $equipoB[0] = array_shift($habilidades['Básica']);
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