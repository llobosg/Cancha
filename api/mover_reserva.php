<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bitacora.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id_reserva = (int)($input['id_reserva'] ?? 0);
$nueva_cancha_id = (int)($input['id_cancha'] ?? 0);
$nueva_fecha = $input['fecha'] ?? '';
$nueva_hora_inicio = $input['hora_inicio'] ?? '';

try {

    if (!$id_reserva || !$nueva_cancha_id || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception("Datos incompletos");
    }

    // 🔥 Obtener datos completos
    $stmt = $pdo->prepare("
        SELECT r.*, c.id_deporte, c.nombre_cancha, rd.nombre as recinto_nombre
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rd ON c.id_recinto = rd.id_recinto
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $original = $stmt->fetch();

    if (!$original) throw new Exception("Reserva no encontrada");

    // 🔥 Obtener nueva cancha
    $stmt = $pdo->prepare("SELECT id_deporte, nombre_cancha FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$nueva_cancha_id]);
    $nueva_cancha = $stmt->fetch();

    if (!$nueva_cancha) throw new Exception("Cancha destino inválida");

    // 🚫 VALIDACIÓN DEPORTE
    if ($original['id_deporte'] != $nueva_cancha['id_deporte']) {
        throw new Exception("❌ No puedes mover a otra cancha de distinto deporte");
    }

    // 🧠 calcular duración
    $duracion = (strtotime($original['hora_fin']) - strtotime($original['hora_inicio'])) / 60;

    $nuevo_inicio = date('H:i:s', strtotime($nueva_fecha . ' ' . $nueva_hora_inicio));
    $nuevo_fin = date('H:i:s', strtotime($nuevo_inicio . " +{$duracion} minutes"));

    // 🔥 validar colisión
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservas
        WHERE id_cancha = ?
        AND fecha = ?
        AND id_reserva != ?
        AND estado != 'cancelada'
        AND hora_inicio < ?
        AND hora_fin > ?
    ");

    $stmt->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $id_reserva,
        $nuevo_fin,
        $nuevo_inicio
    ]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Horario ocupado");
    }

    // 🔥 UPDATE
    $pdo->prepare("
        UPDATE reservas
        SET id_cancha=?, fecha=?, hora_inicio=?, hora_fin=?, updated_at=NOW()
        WHERE id_reserva=?
    ")->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $nuevo_inicio,
        $nuevo_fin,
        $id_reserva
    ]);

    // 🔥 BITÁCORA
    registrarLogReserva(
        $pdo,
        $id_reserva,
        'movida',
        "📅 {$original['fecha']} {$original['hora_inicio']} → {$nueva_fecha} {$nuevo_inicio}",
        $_SESSION['nombre_completo'] ?? 'Admin'
    );

    // 🔥 EMAIL (SI EXISTE)
    if (class_exists('BrevoMailer')) {
        BrevoMailer::enviarActualizacionConDatos(
            $pdo,
            [
                'fecha' => $nueva_fecha,
                'hora_inicio' => $nuevo_inicio,
                'hora_fin' => $nuevo_fin,
                'nombre_cancha' => $nueva_cancha['nombre_cancha'],
                'recinto_nombre' => $original['recinto_nombre'],
                'id_reserva' => $id_reserva,
                'email_cliente' => $original['email_cliente'],
                'nombre_cliente' => $original['nombre_cliente']
            ],
            $original
        );
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("❌ mover_reserva: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}