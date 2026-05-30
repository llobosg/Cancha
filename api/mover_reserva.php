<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bitacora.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 🔒 Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {

    $input = json_decode(file_get_contents('php://input'), true);

    $id_reserva = (int)($input['id_reserva'] ?? 0);
    $nueva_cancha_id = (int)($input['id_cancha'] ?? 0);
    $nueva_fecha = $input['fecha'] ?? '';
    $nueva_hora_inicio = $input['hora_inicio'] ?? '';

    if (!$id_reserva || !$nueva_cancha_id || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception("Faltan datos obligatorios");
    }

    // 🧠 TRANSACCIÓN (CRÍTICO)
    $pdo->beginTransaction();

    // 1. Obtener reserva actual + deporte
    $stmt_actual = $pdo->prepare("
        SELECT r.*, c.id_deporte
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ?
    ");
    $stmt_actual->execute([$id_reserva]);
    $reserva_actual = $stmt_actual->fetch();

    if (!$reserva_actual) {
        throw new Exception("Reserva no encontrada");
    }

    // 2. Obtener deporte destino
    $stmt_destino = $pdo->prepare("SELECT id_deporte FROM canchas WHERE id_cancha = ?");
    $stmt_destino->execute([$nueva_cancha_id]);
    $deporte_destino = $stmt_destino->fetchColumn();

    // 🚫 VALIDACIÓN PRO DE DEPORTE
    if ($reserva_actual['id_deporte'] != $deporte_destino) {
        throw new Exception("❌ No puedes mover a una cancha de otro deporte");
    }

    // 3. Calcular duración
    $h_ini_actual = strtotime($reserva_actual['hora_inicio']);
    $h_fin_actual = strtotime($reserva_actual['hora_fin']);
    $duracion_minutos = ($h_fin_actual - $h_ini_actual) / 60;

    // 4. Calcular nuevo horario
    $nuevo_inicio = date('H:i:s', strtotime($nueva_fecha . ' ' . $nueva_hora_inicio));
    $nuevo_fin = date('H:i:s', strtotime($nuevo_inicio . " +{$duracion_minutos} minutes"));

    // 5. Validar colisiones
    $stmt_colision = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE id_cancha = ? 
        AND fecha = ? 
        AND estado != 'cancelada'
        AND id_reserva != ?
        AND hora_inicio < ?
        AND hora_fin > ?
    ");

    $stmt_colision->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $id_reserva,
        $nuevo_fin,
        $nuevo_inicio
    ]);

    if ($stmt_colision->fetchColumn() > 0) {
        throw new Exception("⚠️ Hay conflicto con otra reserva");
    }

    // 6. Guardar estado original (para bitácora)
    $original = [
        'fecha' => $reserva_actual['fecha'],
        'hora_inicio' => $reserva_actual['hora_inicio'],
        'hora_fin' => $reserva_actual['hora_fin'],
        'id_cancha' => $reserva_actual['id_cancha']
    ];

    // 7. UPDATE
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");

    $stmt_update->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $nuevo_inicio,
        $nuevo_fin,
        $id_reserva
    ]);

    // 8. BITÁCORA (ANTES DEL COMMIT)
    registrarLogReserva(
        $pdo,
        $id_reserva,
        'movida',
        "📅 {$original['fecha']} {$original['hora_inicio']} → {$nueva_fecha} {$nuevo_inicio}",
        $_SESSION['nombre_completo'] ?? 'Admin',
        null,
        null,
        [
            'antes' => $original,
            'despues' => [
                'fecha' => $nueva_fecha,
                'hora_inicio' => $nuevo_inicio,
                'hora_fin' => $nuevo_fin,
                'id_cancha' => $nueva_cancha_id
            ]
        ]
    );

    // ✅ COMMIT FINAL
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reserva movida correctamente'
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("❌ Error mover reserva: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}