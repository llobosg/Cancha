<?php
header('Content-Type: application/json');

// Logging temprano
error_log("🔍 [GENERAR_FIXTURE] Inicio del script");

// Cargar configuración
require_once __DIR__ . '/../includes/config.php';

// Iniciar sesión
session_start();

error_log("🔍 [GENERAR_FIXTURE] Sesión iniciada. ID recinto: " . ($_SESSION['id_recinto'] ?? 'NO DEFINIDO'));

try {
    if (!isset($_SESSION['id_recinto'])) {
        error_log("❌ [GENERAR_FIXTURE] Acceso no autorizado: sesión sin id_recinto");
        throw new Exception('Acceso no autorizado');
    }

    $id_torneo = $_POST['id_torneo'] ?? null;
    if (!$id_torneo) {
        error_log("❌ [GENERAR_FIXTURE] ID de torneo no proporcionado");
        throw new Exception('ID de torneo requerido');
    }

    // Verificar pertenencia
    $stmt = $pdo->prepare("SELECT id_torneo FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
    $stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        throw new Exception('Torneo no encontrado');
    }

    // === MODO PRUEBA: Forzar 6 parejas genéricas ===
    $num_parejas = 6; // ← Fijar 6 parejas para testing
    error_log("🧪 [GENERAR_FIXTURE] Modo prueba: forzando 6 parejas genéricas");

    // === GENERAR PAREJAS GENÉRICAS ===
    $parejas = [];
    for ($i = 1; $i <= $num_parejas; $i++) {
        $parejas[] = [
            'id' => $i,
            'nombre' => "#{$i}"
        ];
    }

    // === ALGORITMO ROUND-ROBIN SIMPLE ===
    $n = count($parejas);
    $esImpar = ($n % 2 !== 0);
    if ($esImpar) {
        $parejas[] = ['id' => null, 'nombre' => 'Descanso'];
        $n++;
    }

    $fixtures = [];
    for ($ronda = 0; $ronda < $n - 1; $ronda++) {
        $partidos = [];
        for ($i = 0; $i < $n / 2; $i++) {
            $a = $parejas[$i];
            $b = $parejas[$n - 1 - $i];
            if ($a['id'] && $b['id']) {
                $partidos[] = [$a, $b];
            }
        }
        $fixtures[] = $partidos;

        // Rotar (excepto la primera)
        $temp = $parejas[1];
        for ($i = 1; $i < $n - 1; $i++) {
            $parejas[$i] = $parejas[$i + 1];
        }
        $parejas[$n - 1] = $temp;
    }

    // === GUARDAR EN BASE DE DATOS ===
    $pdo->beginTransaction();
    try {
        // Limpiar partidos anteriores
        $pdo->prepare("DELETE FROM partidos_torneo WHERE id_torneo = ?")->execute([$id_torneo]);

        $fecha_base = new DateTime();
        foreach ($fixtures as $ronda_index => $partidos) {
            $fecha_partido = clone $fecha_base;
            $fecha_partido->modify("+" . ($ronda_index + 1) . " weeks");
            $fecha_str = $fecha_partido->format('Y-m-d');

            foreach ($partidos as $partido) {
                [$pareja1, $pareja2] = $partido;
                $pdo->prepare("
                    INSERT INTO partidos_torneo (id_torneo, id_pareja_1, id_pareja_2, fecha_hora_programada, estado)
                    VALUES (?, ?, ?, ?, 'pendiente')
                ")->execute([
                    $id_torneo,
                    $pareja1['id'],
                    $pareja2['id'],
                    $fecha_str . ' 19:00:00'
                ]);
            }
        }

        $pdo->prepare("UPDATE torneos SET estado = 'en_progreso' WHERE id_torneo = ?")
             ->execute([$id_torneo]);
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => '✅ Fixture generado']);

    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception('Error al guardar: ' . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>