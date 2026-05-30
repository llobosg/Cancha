<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/bitacora.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 Evitar que warnings rompan JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$input = json_decode(file_get_contents('php://input'), true);

$id_reserva = (int)($input['id_reserva'] ?? 0);
$nueva_cancha_id = (int)($input['id_cancha'] ?? 0);
$nueva_fecha = $input['fecha'] ?? '';
$nueva_hora_inicio = $input['hora_inicio'] ?? '';

try {

    if (!$id_reserva || !$nueva_cancha_id || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception("Faltan datos obligatorios");
    }

    // ================================
    // 1. Obtener reserva actual + datos completos
    // ================================
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nombre_cancha, c.id_deporte,
               rec.nombre AS recinto_nombre,
               s.email, s.nombre AS nombre_socio
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        throw new Exception("Reserva no encontrada");
    }

    // ================================
    // 2. Validar mismo deporte
    // ================================
    $stmt = $pdo->prepare("SELECT id_deporte, nombre_cancha FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$nueva_cancha_id]);
    $canchaNueva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$canchaNueva) {
        throw new Exception("Cancha destino no válida");
    }

    if ($canchaNueva['id_deporte'] != $original['id_deporte']) {
        throw new Exception("❌ No puedes mover a una cancha de otro deporte");
    }

    // ================================
    // 3. Calcular duración
    // ================================
    $inicio_actual = strtotime($original['hora_inicio']);
    $fin_actual = strtotime($original['hora_fin']);
    $duracion = ($fin_actual - $inicio_actual) / 60;

    $nuevo_inicio = date('H:i:s', strtotime($nueva_fecha . ' ' . $nueva_hora_inicio));
    $nuevo_fin = date('H:i:s', strtotime($nuevo_inicio . " +{$duracion} minutes"));

    // ================================
    // 4. Validar colisión
    // ================================
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

    $stmt->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $id_reserva,
        $nuevo_fin,
        $nuevo_inicio
    ]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception("⚠️ Ya existe una reserva en ese horario");
    }

    // ================================
    // 5. Ejecutar UPDATE
    // ================================
    $stmt = $pdo->prepare("
        UPDATE reservas
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");

    $stmt->execute([
        $nueva_cancha_id,
        $nueva_fecha,
        $nuevo_inicio,
        $nuevo_fin,
        $id_reserva
    ]);

    // ================================
    // 6. BITÁCORA
    // ================================
    registrarLogReserva(
        $pdo,
        $id_reserva,
        'movida',
        "🔄 Reprogramación de reserva",
        $_SESSION['nombre_completo'] ?? 'Admin',
        null,
        null,
        [
            'antes' => [
                'fecha' => $original['fecha'],
                'hora_inicio' => $original['hora_inicio'],
                'hora_fin' => $original['hora_fin'],
                'cancha' => $original['nombre_cancha']
            ],
            'despues' => [
                'fecha' => $nueva_fecha,
                'hora_inicio' => $nuevo_inicio,
                'hora_fin' => $nuevo_fin,
                'cancha' => $canchaNueva['nombre_cancha']
            ]
        ]
    );

    // ================================
    // 7. EMAIL
    // ================================
    try {
        BrevoMailer::enviarActualizacionConDatos(
            $pdo,
            [
                ...$original,
                'fecha' => $nueva_fecha,
                'hora_inicio' => $nuevo_inicio,
                'hora_fin' => $nuevo_fin,
                'nombre_cancha' => $canchaNueva['nombre_cancha']
            ],
            $original
        );
    } catch (Exception $e) {
        error_log("⚠️ Error enviando correo: " . $e->getMessage());
    }

    // ================================
    // 8. RESPUESTA FINAL LIMPIA
    // ================================
    echo json_encode([
        'success' => true,
        'message' => 'Reserva movida correctamente'
    ]);
    exit;

} catch (Exception $e) {

    error_log("❌ Error mover reserva: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}