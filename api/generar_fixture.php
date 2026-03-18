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
    if (!$id_torneo || !is_numeric($id_torneo)) {
        error_log("❌ [GENERAR_FIXTURE] ID de torneo inválido: " . print_r($id_torneo, true));
        throw new Exception('ID de torneo requerido');
    }
    $id_torneo = (int)$id_torneo;

    // Verificar pertenencia
    $stmt = $pdo->prepare("SELECT id_torneo FROM torneos WHERE id_torneo = ? AND id_recinto = ?");
    $stmt->execute([$id_torneo, $_SESSION['id_recinto']]);
    if (!$stmt->fetch()) {
        throw new Exception('Torneo no encontrado');
    }

    // === OBTENER PAREJAS REALES ===
    $stmt_parejas = $pdo->prepare("
        SELECT id_pareja 
        FROM parejas_torneo 
        WHERE id_torneo = ? AND estado = 'completa'
        ORDER BY id_pareja
    ");
    if (!$stmt_parejas) {
        error_log("❌ [GENERAR_FIXTURE] Error en prepare: " . json_encode($pdo->errorInfo()));
        throw new Exception('Error al preparar consulta');
    }
    $stmt_parejas->execute([$id_torneo]);
    $parejas_db = $stmt_parejas->fetchAll(PDO::FETCH_ASSOC);

    if (count($parejas_db) < 2) {
        throw new Exception('Se necesitan al menos 2 parejas para generar el fixture');
    }

    $parejas = [];
    foreach ($parejas_db as $p) {
        $parejas[] = ['id' => $p['id_pareja'], 'nombre' => '#' . $p['id_pareja']];
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