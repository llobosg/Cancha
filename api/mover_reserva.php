<?php
// api/mover_reserva.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success'=>false, 'message'=>'No autorizado']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_reserva = $data['id_reserva'] ?? null;
    $nueva_fecha = $data['fecha'] ?? null;
    $nueva_hora_inicio = $data['hora_inicio'] ?? null;
    $nueva_cancha = $data['id_cancha'] ?? null;
    
    if (!$id_reserva || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception('Datos incompletos para mover reserva');
    }
    
    // 1. Obtener datos ORIGINALES para comparar
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $original = $stmt->fetch();
    
    if (!$original) throw new Exception('Reserva no encontrada');
    
    // 2. Calcular nueva hora fin (mantener misma duración)
    $duracion_seg = strtotime($original['hora_fin']) - strtotime($original['hora_inicio']);
    $nueva_hora_fin = date('H:i:s', strtotime($nueva_hora_inicio) + $duracion_seg);
    
    // 3. Actualizar en BD
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt->execute([$nueva_cancha ?? $original['id_cancha'], $nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin, $id_reserva]);
    
    // 4. Preparar descripción de cambios para el correo
    $cambios = [];
    if (($nueva_cancha ?? $original['id_cancha']) != $original['id_cancha']) {
        $stmt_c = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
        $stmt_c->execute([$nueva_cancha]);
        $cambios['cancha'] = $stmt_c->fetchColumn() ?: "Cancha ID $nueva_cancha";
    }
    if ($nueva_fecha != $original['fecha']) {
        $cambios['fecha'] = date('d/m/Y', strtotime($nueva_fecha));
    }
    if ($nueva_hora_inicio != $original['hora_inicio']) {
        $cambios['hora'] = substr($nueva_hora_inicio,0,5) . ' - ' . substr($nueva_hora_fin,0,5);
    }
    
    // 5. Obtener datos ACTUALIZADOS para el correo (los nuevos)
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
    
    // 6. Enviar correo con los datos NUEVOS
    if ($actualizada) {
        ReservaMailer::enviarActualizacionConDatos($pdo, $actualizada, $cambios);
    }
    
    echo json_encode(['success'=>true, 'message'=>'Reserva movida y correo enviado']);
    
} catch (Exception $e) {
    error_log("[Mover Reserva] Error: " . $e->getMessage());
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>