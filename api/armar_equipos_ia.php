<?php
// === SALIDA LIMPIA ===
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
session_start();

// Función para escribir logs
function logMessage($message) {
    $logFile = __DIR__ . '/logs/equipos_ia.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

try {
    logMessage("=== INICIO ARMADO EQUIPOS IA ===");
    logMessage("SESSION: " . json_encode($_SESSION));
    logMessage("POST: " . json_encode($_POST));

    // Validar sesión
    if (!isset($_SESSION['club_id']) || empty($_POST['id_reserva'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_reserva = (int)$_POST['id_reserva'];
    $club_id = $_SESSION['club_id'];

    logMessage("ID Reserva: {$id_reserva}, Club ID: {$club_id}");

    // Verificar reserva
    $stmt = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_club = ?");
    $stmt->execute([$id_reserva, $club_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Reserva no encontrada');
    }

    // Obtener inscritos
    $stmt = $pdo->prepare("
        SELECT 
            s.id_socio, 
            s.alias, 
            s.habilidad,
            COALESCE(i.posicion_jugador, 'Jugador') as posicion_jugador
        FROM inscritos i
        JOIN socios s ON i.id_socio = s.id_socio
        WHERE i.id_evento = ? AND i.tipo_actividad = 'reserva'
        ORDER BY s.alias
        LIMIT 14
    ");
    $stmt->execute([$id_reserva]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage("Inscritos encontrados: " . count($inscritos));

    if (count($inscritos) < 10) {
        throw new Exception('Se requieren al menos 10 jugadores');
    }

    // Clasificar por posición
    $arqueros = [];
    $defensas = [];
    $medios = [];
    $laterales = [];
    $delanteros = [];
    $jugadores = [];

    foreach ($inscritos as $socio) {
        $pos = $socio['posicion_jugador'];
        if (strpos($pos, 'Arquero') !== false) {
            $arqueros[] = $socio;
        } elseif (strpos($pos, 'Defensa') !== false || strpos($pos, 'Atrás') !== false || strpos($pos, 'Central') !== false) {
            $defensas[] = $socio;
        } elseif (strpos($pos, 'Medio') !== false) {
            $medios[] = $socio;
        } elseif (strpos($pos, 'Lateral') !== false) {
            $laterales[] = $socio;
        } elseif (strpos($pos, 'Delantero') !== false || strpos($pos, 'Adelante') !== false) {
            $delanteros[] = $socio;
        } else {
            $jugadores[] = $socio;
        }
    }

    logMessage("Clasificación: Arqueros=" . count($arqueros) . ", Defensas=" . count($defensas) . ", Medios=" . count($medios) . ", Laterales=" . count($laterales) . ", Delanteros=" . count($delanteros) . ", Jugadores=" . count($jugadores));

    // Mezclar
    shuffle($arqueros);
    shuffle($defensas);
    shuffle($medios);
    shuffle($laterales);
    shuffle($delanteros);
    shuffle($jugadores);

    // Iniciar equipos
    $equipoA = [];
    $equipoB = [];

    // Asignar arqueros
    if (!empty($arqueros)) {
        $equipoA[] = array_shift($arqueros);
        if (!empty($arqueros)) {
            $equipoB[] = array_shift($arqueros);
        }
    }

    // Si falta arquero, usar defensa
    if (empty($equipoA) && !empty($defensas)) {
        $equipoA[] = array_shift($defensas);
    }
    if (empty($equipoB) && !empty($defensas)) {
        $equipoB[] = array_shift($defensas);
    }

    // Completar con orden específico
    $grupos = [$defensas, $medios, $laterales, $delanteros, $jugadores];
    foreach ($grupos as $grupo) {
        while (!empty($grupo) && (count($equipoA) < 7 || count($equipoB) < 7)) {
            if (count($equipoA) < 7) {
                $equipoA[] = array_shift($grupo);
            }
            if (!empty($grupo) && count($equipoB) < 7) {
                $equipoB[] = array_shift($grupo);
            }
        }
    }

    logMessage("Equipo A: " . count($equipoA) . " jugadores");
    logMessage("Equipo B: " . count($equipoB) . " jugadores");

    // Balancear habilidades
    $cracksA = array_filter($equipoA, fn($j) => $j['habilidad'] === 'Avanzada');
    $cracksB = array_filter($equipoB, fn($j) => $j['habilidad'] === 'Avanzada');

    if (count($cracksA) > 3 && count($cracksB) < 3 && !empty($cracksA)) {
        $equipoB[] = array_shift($cracksA);
    }

    // Asegurar al menos 1 básico por equipo
    $basicos = array_filter($inscritos, fn($j) => $j['habilidad'] === 'Básica');
    if (count(array_filter($equipoA, fn($j) => $j['habilidad'] === 'Básica')) == 0 && !empty($basicos)) {
        $equipoA[0] = array_shift($basicos);
    }
    if (count(array_filter($equipoB, fn($j) => $j['habilidad'] === 'Básica')) == 0 && !empty($basicos)) {
        $equipoB[0] = array_shift($basicos);
    }

    // Guardar en DB
    $pdo->beginTransaction();

    // Limpiar equipos anteriores
    $pdo->prepare("
        DELETE je FROM jugadores_equipo je
        JOIN equipos_partido ep ON je.id_equipo = ep.id_equipo
        WHERE ep.id_reserva = ?
    ")->execute([$id_reserva]);

    // Crear equipos
    $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Rojos')")->execute([$id_reserva]);
    $id_rojos = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, 'Blancos')")->execute([$id_reserva]);
    $id_blancos = $pdo->lastInsertId();

    // Asignar jugadores
    foreach ($equipoA as $jugador) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")->execute([$id_rojos, $jugador['id_socio']]);
    }
    foreach ($equipoB as $jugador) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")->execute([$id_blancos, $jugador['id_socio']]);
    }

    $pdo->commit();

    logMessage("✅ Equipos guardados exitosamente");
    echo json_encode(['success' => true, 'equipos' => ['rojos' => $equipoA, 'blancos' => $equipoB]]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    $error_msg = 'Error: ' . $e->getMessage();
    logMessage($error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
?>