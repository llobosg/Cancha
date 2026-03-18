<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';

try {
    // Validar permisos (solo admin de recinto)
    session_start();
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_torneo = $_POST['id_torneo'] ?? null;
    if (!$id_torneo) {
        throw new Exception('ID de torneo requerido');
    }

    // Verificar que el torneo pertenece al recinto
    $stmt_check = $pdo->prepare("
        SELECT t.nombre, t.valor 
        FROM torneos t 
        WHERE t.id_torneo = ? AND t.id_recinto = ?
    ");
    $stmt_check->execute([$id_torneo, $_SESSION['id_recinto']]);
    $torneo = $stmt_check->fetch();
    if (!$torneo) {
        throw new Exception('Torneo no encontrado');
    }

    // Obtener parejas completas
    $stmt_parejas = $pdo->prepare("
        SELECT 
            pt.id_pareja,
            COALESCE(s1.alias, jt1.nombre) AS nombre1,
            COALESCE(s2.alias, jt2.nombre) AS nombre2,
            COALESCE(s1.email, jt1.email) AS email1,
            COALESCE(s2.email, jt2.email) AS email2
        FROM parejas_torneo pt
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        LEFT JOIN jugadores_temporales jt1 ON pt.id_jugador_temp_1 = jt1.id_jugador
        LEFT JOIN jugadores_temporales jt2 ON pt.id_jugador_temp_2 = jt2.id_jugador
        WHERE pt.id_torneo = ? AND pt.estado = 'completa'
        ORDER BY pt.id_pareja
    ");
    $stmt_parejas->execute([$id_torneo]);
    $parejas = $stmt_parejas->fetchAll(PDO::FETCH_ASSOC);

    if (count($parejas) < 2) {
        throw new Exception('Se necesitan al menos 2 parejas para generar el fixture');
    }

    // === ALGORITMO ROUND-ROBIN ===
    $n = count($parejas);
    $esImpar = ($n % 2 !== 0);
    if ($esImpar) {
        $parejas[] = [
            'id_pareja' => null,
            'nombre1' => 'Descanso',
            'nombre2' => '',
            'email1' => null,
            'email2' => null
        ];
        $n++;
    }

    $fixtures = [];
    for ($ronda = 0; $ronda < $n - 1; $ronda++) {
        $partidos_ronda = [];
        for ($i = 0; $i < $n / 2; $i++) {
            $a = $parejas[$i];
            $b = $parejas[$n - 1 - $i];
            if ($a['id_pareja'] && $b['id_pareja']) {
                $partidos_ronda[] = [$a, $b];
            }
        }
        $fixtures[] = $partidos_ronda;

        // Rotar (excepto la primera pareja)
        $temp = $parejas[1];
        for ($i = 1; $i < $n - 1; $i++) {
            $parejas[$i] = $parejas[$i + 1];
        }
        $parejas[$n - 1] = $temp;
    }

    // === INSERTAR PARTIDOS EN BASE DE DATOS ===
    $pdo->beginTransaction();
    try {
        // Limpiar partidos anteriores (por si se regenera)
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
                    $pareja1['id_pareja'],
                    $pareja2['id_pareja'],
                    $fecha_str . ' 19:00:00'
                ]);
            }
        }

        // Actualizar estado del torneo
        $pdo->prepare("UPDATE torneos SET estado = 'en_progreso' WHERE id_torneo = ?")
             ->execute([$id_torneo]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        throw new Exception('Error al guardar el fixture: ' . $e->getMessage());
    }

    // === ENVIAR CORREOS A CADA PAREJA ===
    foreach ($fixtures as $ronda_index => $partidos) {
        foreach ($partidos as $partido) {
            [$pareja1, $pareja2] = $partido;
            $fecha = date('d/m/Y', strtotime("+" . ($ronda_index + 1) . " weeks"));
            $hora = "19:00";
            $cancha = "Cancha por definir";

            // Correo a pareja 1
            if ($pareja1['email1']) {
                enviarCorreoFixture(
                    $pareja1['email1'],
                    $pareja1['nombre1'] . ' + ' . $pareja1['nombre2'],
                    $pareja2['nombre1'] . ' + ' . $pareja2['nombre2'],
                    $fecha,
                    $hora,
                    $cancha,
                    $torneo['nombre']
                );
            }

            // Correo a pareja 2
            if ($pareja2['email1']) {
                enviarCorreoFixture(
                    $pareja2['email1'],
                    $pareja2['nombre1'] . ' + ' . $pareja2['nombre2'],
                    $pareja1['nombre1'] . ' + ' . $pareja1['nombre2'],
                    $fecha,
                    $hora,
                    $cancha,
                    $torneo['nombre']
                );
            }
        }
    }

    echo json_encode(['success' => true, 'message' => '✅ Fixture generado y correos enviados']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enviarCorreoFixture($email, $tuPareja, $rival, $fecha, $hora, $cancha, $torneo) {
    $mailer = new BrevoMailer();
    $mailer->setTo($email);
    $mailer->setSubject('🎾 Tu fixture en ' . $torneo);
    $mailer->setHtmlBody("
        <h2>¡Hola!</h2>
        <p>Tu pareja: <strong>{$tuPareja}</strong></p>
        <p>📅 <strong>Fecha:</strong> {$fecha}</p>
        <p>⏰ <strong>Hora:</strong> {$hora}</p>
        <p>🏟️ <strong>Cancha:</strong> {$cancha}</p>
        <p>⚔️ <strong>Rival:</strong> {$rival}</p>
        <p>¡Éxito en el torneo <strong>{$torneo}</strong>!</p>
    ");
    $mailer->send();
}
?>