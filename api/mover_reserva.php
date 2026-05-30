<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bitacora.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

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

    $pdo->beginTransaction();

    // 🔎 RESERVA ORIGINAL COMPLETA
    $stmt = $pdo->prepare("
        SELECT r.*, c.id_deporte, c.nombre_cancha, d.nombre as recinto_nombre
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos d ON c.id_recinto = d.id_recinto
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $original = $stmt->fetch();

    if (!$original) throw new Exception("Reserva no encontrada");

    // 🔎 CANCHA DESTINO
    $stmt = $pdo->prepare("
        SELECT id_deporte, nombre_cancha 
        FROM canchas 
        WHERE id_cancha = ?
    ");
    $stmt->execute([$nueva_cancha_id]);
    $cancha_destino = $stmt->fetch();

    if (!$cancha_destino) throw new Exception("Cancha destino no válida");

    // 🚫 VALIDAR DEPORTE
    if ($original['id_deporte'] != $cancha_destino['id_deporte']) {
        throw new Exception("❌ No puedes mover a una cancha de otro deporte");
    }

    // ⏱️ DURACIÓN
    $duracion = (strtotime($original['hora_fin']) - strtotime($original['hora_inicio'])) / 60;

    $nuevo_inicio = date('H:i:s', strtotime($nueva_fecha . ' ' . $nueva_hora_inicio));
    $nuevo_fin = date('H:i:s', strtotime($nuevo_inicio . " +{$duracion} minutes"));

    // 🚫 COLISIÓN
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reservas 
        WHERE id_cancha = ? 
        AND fecha = ?
        AND estado != 'cancelada'
        AND id_reserva != ?
        AND hora_inicio < ?
        AND hora_fin > ?
    ");
    $stmt->execute([$nueva_cancha_id, $nueva_fecha, $id_reserva, $nuevo_fin, $nuevo_inicio]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception("⚠️ Conflicto con otra reserva");
    }

    // 📝 UPDATE
    $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ")->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $nuevo_inicio,
        $nuevo_fin,
        $id_reserva
    ]);

    // 🧾 BITÁCORA (PRO)
    registrarLogReserva(
        $pdo,
        $id_reserva,
        'movida',
        "🏟️ {$original['nombre_cancha']} → {$cancha_destino['nombre_cancha']} | 📅 {$original['fecha']} {$original['hora_inicio']} → {$nueva_fecha} {$nuevo_inicio}",
        $_SESSION['nombre_completo'] ?? 'Admin'
    );

    // 📧 CORREO (CLAVE)
    if (class_exists('BrevoMailer')) {
        BrevoMailer::enviarActualizacionConDatos(
            $pdo,
            [
                'id_reserva' => $id_reserva,
                'fecha' => $nueva_fecha,
                'hora_inicio' => $nuevo_inicio,
                'hora_fin' => $nuevo_fin,
                'nombre_cancha' => $cancha_destino['nombre_cancha'],
                'recinto_nombre' => $original['recinto_nombre'],
                'email_cliente' => $original['email_cliente'],
                'nombre_cliente' => $original['nombre_cliente'],
                'id_deporte' => $original['id_deporte']
            ],
            [
                'fecha' => $original['fecha'],
                'hora_inicio' => $original['hora_inicio'],
                'hora_fin' => $original['hora_fin'],
                'nombre_cancha' => $original['nombre_cancha']
            ]
        );
    }

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