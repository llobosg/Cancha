<?php
// api/mover_reserva.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success'=>false, 'message'=>'Acceso no autorizado']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_reserva = $data['id_reserva'] ?? null;
    $nueva_cancha = $data['id_cancha'] ?? null;
    $nueva_hora = $data['hora_inicio'] ?? null;
    
    if (!$id_reserva || !$nueva_cancha || !$nueva_hora) {
        throw new Exception('Datos incompletos para mover reserva');
    }
    
    // Obtener reserva actual para comparar cambios
    $stmt = $pdo->prepare("SELECT id_cancha, hora_inicio, hora_fin, fecha FROM reservas WHERE id_reserva = ?");
    $stmt->execute([$id_reserva]);
    $actual = $stmt->fetch();
    if (!$actual) throw new Exception('Reserva no encontrada');
    
    // Calcular nueva hora fin (asumimos misma duración)
    $duracion = strtotime($actual['hora_fin']) - strtotime($actual['hora_inicio']);
    $nueva_hora_fin = date('H:i:s', strtotime($nueva_hora) + $duracion);
    
    // Actualizar reserva
    $stmt = $pdo->prepare("UPDATE reservas SET id_cancha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW() WHERE id_reserva = ?");
    $stmt->execute([$nueva_cancha, $nueva_hora, $nueva_hora_fin, $id_reserva]);
    
    // Preparar descripción de cambios para el correo
    $cambios = [];
    if ($actual['id_cancha'] != $nueva_cancha) {
        $stmt_c = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
        $stmt_c->execute([$nueva_cancha]);
        $cambios['cancha'] = $stmt_c->fetchColumn();
    }
    if ($actual['hora_inicio'] != $nueva_hora) {
        $cambios['hora'] = substr($nueva_hora,0,5) . ' - ' . substr($nueva_hora_fin,0,5);
    }
    
    // Enviar correo de actualización
    ReservaMailer::enviarActualizacion($pdo, $id_reserva, $cambios);
    
    echo json_encode(['success'=>true, 'message'=>'Reserva movida y correo enviado']);
    
} catch (Exception $e) {
    error_log("[Mover Reserva] Error: " . $e->getMessage());
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>