<?php
// api/mover_reserva.php
header('Content-Type: application/json');
if (ob_get_level() > 0) ob_clean(); // Limpiar buffer previo para evitar "basura" en la respuesta

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos JSON inválidos');
    }
    
    $id_reserva = $data['id_reserva'] ?? null;
    $nueva_fecha = $data['fecha'] ?? null;
    $nueva_hora_inicio = $data['hora_inicio'] ?? null;
    $nueva_cancha = $data['id_cancha'] ?? null;
    
    if (!$id_reserva || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception('Datos incompletos: id_reserva, fecha y hora_inicio son requeridos');
    }
    
    // 1. Obtener datos originales
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_reserva, $_SESSION['id_recinto']]);
    $original = $stmt->fetch();
    
    if (!$original) {
        throw new Exception('Reserva no encontrada o no pertenece a este recinto');
    }
    
    // 2. Calcular nueva hora fin (mantener duración original)
    $inicio_orig = strtotime($original['hora_inicio']);
    $fin_orig = strtotime($original['hora_fin']);
    $duracion_seg = $fin_orig - $inicio_orig;
    
    $nueva_hora_fin = date('H:i:s', strtotime($nueva_hora_inicio) + $duracion_seg);
    
    // 3. Actualizar en BD
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt->execute([
        $nueva_cancha ?? $original['id_cancha'],
        $nueva_fecha,
        $nueva_hora_inicio,
        $nueva_hora_fin,
        $id_reserva
    ]);
    
    // 4. Preparar descripción de cambios
    $cambios = [];
    $id_cancha_final = $nueva_cancha ?? $original['id_cancha'];
    
    if ($id_cancha_final != $original['id_cancha']) {
        $stmt_c = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
        $stmt_c->execute([$id_cancha_final]);
        $cambios['cancha'] = $stmt_c->fetchColumn() ?: "Cancha ID $id_cancha_final";
    }
    if ($nueva_fecha != $original['fecha']) {
        $cambios['fecha'] = date('d/m/Y', strtotime($nueva_fecha));
    }
    if ($nueva_hora_inicio != $original['hora_inicio']) {
        $cambios['hora'] = substr($nueva_hora_inicio, 0, 5) . ' - ' . substr($nueva_hora_fin, 0, 5);
    }
    
    // 5. Obtener datos actualizados para el correo
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre,
               s.email, s.nombre as nombre_socio, s.alias
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $actualizada = $stmt->fetch();
    
    // 6. Enviar correo si hay datos actualizados y email
    if ($actualizada && ($actualizada['email'] || $actualizada['email_cliente'])) {
        ReservaMailer::enviarActualizacionConDatos($pdo, $actualizada, $cambios);
    }
    
    // 7. Respuesta JSON limpia
    echo json_encode([
        'success' => true, 
        'message' => 'Reserva movida correctamente',
        'nueva_fecha' => $nueva_fecha,
        'nueva_hora' => substr($nueva_hora_inicio,0,5) . '-' . substr($nueva_hora_fin,0,5)
    ]);
    
} catch (Exception $e) {
    error_log("[Mover Reserva] Error: " . $e->getMessage());
    // Respuesta de error en JSON
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Asegurar que no haya salida extra
if (ob_get_level() > 0) ob_end_flush();
?>