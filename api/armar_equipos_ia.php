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

    // Agrupar jugadores por posición (respetando el orden definido)
    $posiciones_orden = [
        'Arquero' => [],
        'Defensa' => [],      // Incluye "Atrás", "Central"
        'Medio' => [],
        'Lateral' => [],      // Incluye "Lateral izq", "Lateral der"
        'Delantero' => []     // Incluye "Delantero izq", "Delantero der", "Adelante"
    ];

    $habilidades = ['Básica' => [], 'Intermedia' => [], 'Avanzada' => []];

    foreach ($inscritos as $socio) {
        // Clasificar por posición
        $pos = $socio['posicion_jugador'] ?? 'Jugador';
        
        if (strpos($pos, 'Arquero') !== false) {
            $posiciones_orden['Arquero'][] = $socio;
        } elseif (strpos($pos, 'Defensa') !== false || strpos($pos, 'Atrás') !== false || strpos($pos, 'Central') !== false) {
            $posiciones_orden['Defensa'][] = $socio;
        } elseif (strpos($pos, 'Medio') !== false) {
            $posiciones_orden['Medio'][] = $socio;
        } elseif (strpos($pos, 'Lateral') !== false) {
            $posiciones_orden['Lateral'][] = $socio;
        } elseif (strpos($pos, 'Delantero') !== false || strpos($pos, 'Adelante') !== false) {
            $posiciones_orden['Delantero'][] = $socio;
        } else {
            // Jugadores sin posición específica: asignar según necesidad
            $posiciones_orden['Delantero'][] = $socio; // Último recurso
        }
        
        // Clasificar por habilidad
        $habilidades[$socio['habilidad']][] = $socio;
    }

    // Mezclar aleatoriamente dentro de cada grupo
    foreach ($posiciones_orden as &$grupo) shuffle($grupo);
    foreach ($habilidades as &$grupo) shuffle($grupo);

    // Distribuir manteniendo el orden de posiciones
    $equipoA = [];
    $equipoB = [];

    // Asignar arqueros primero (mínimo 1 por equipo)
    if (!empty($posiciones_orden['Arquero'])) {
        $equipoA[] = array_shift($posiciones_orden['Arquero']);
        if (!empty($posiciones_orden['Arquero'])) {
            $equipoB[] = array_shift($posiciones_orden['Arquero']);
        }
    }

    // Si falta arquero en algún equipo, usar Defensa como fallback
    if (count($equipoA) == 0 && !empty($posiciones_orden['Defensa'])) {
        $equipoA[] = array_shift($posiciones_orden['Defensa']);
    }
    if (count($equipoB) == 0 && !empty($posiciones_orden['Defensa'])) {
        $equipoB[] = array_shift($posiciones_orden['Defensa']);
    }

    // Completar con el orden específico de posiciones
    $orden_posiciones = ['Defensa', 'Medio', 'Lateral', 'Delantero'];
    foreach ($orden_posiciones as $pos) {
        while (!empty($posiciones_orden[$pos]) && (count($equipoA) < 7 || count($equipoB) < 7)) {
            if (count($equipoA) < 7) {
                $equipoA[] = array_shift($posiciones_orden[$pos]);
            }
            if (!empty($posiciones_orden[$pos]) && count($equipoB) < 7) {
                $equipoB[] = array_shift($posiciones_orden[$pos]);
            }
        }
    }

    // Balancear habilidades (máximo 3 cracks por equipo, mínimo 1 básico)
    balancearHabilidades($equipoA, $equipoB, $habilidades);

    function balancearHabilidades(&$eqA, &$eqB, $habilidades) {
        // Contar cracks
        $cracksA = array_filter($eqA, fn($j) => $j['habilidad'] === 'Avanzada');
        $cracksB = array_filter($eqB, fn($j) => $j['habilidad'] === 'Avanzada');
        
        // Mover cracks si hay desbalance
        if (count($cracksA) > 3 && count($cracksB) < 3) {
            foreach ($eqA as $i => $jugador) {
                if ($jugador['habilidad'] === 'Avanzada') {
                    $eqB[] = $jugador;
                    unset($eqA[$i]);
                    break;
                }
            }
            $eqA = array_values($eqA);
        }
        
        // Asegurar al menos 1 básico por equipo
        $bajosA = array_filter($eqA, fn($j) => $j['habilidad'] === 'Básica');
        $bajosB = array_filter($eqB, fn($j) => $j['habilidad'] === 'Básica');
        
        if (empty($bajosA) && !empty($habilidades['Básica'])) {
            $eqA[0] = array_shift($habilidades['Básica']);
        }
        if (empty($bajosB) && !empty($habilidades['Básica'])) {
            $eqB[0] = array_shift($habilidades['Básica']);
        }
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