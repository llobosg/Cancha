<?php
// api/mover_reserva.php
header('Content-Type: application/json; charset=utf-8');
if (ob_get_level() > 0) { ob_clean(); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // ← CLAVE: cargar la clase

error_log("[Mover Reserva] === INICIO ===");

try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    if (!isset($_SESSION['id_recinto'])) {
        error_log("[Mover Reserva] ❌ Sesión inválida");
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    
    $id_recinto = (int)$_SESSION['id_recinto'];
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    $id_reserva = $data['id_reserva'] ?? null;
    $nueva_fecha = $data['fecha'] ?? null;
    $nueva_hora_inicio = $data['hora_inicio'] ?? null;
    $nueva_cancha = $data['id_cancha'] ?? null;
    
    if (!$id_reserva || !$nueva_fecha || !$nueva_hora_inicio) {
        throw new Exception('Datos incompletos');
    }
    
    // Obtener reserva original (validando recinto)
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_deporte, rec.nombre as recinto_nombre
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_reserva, $id_recinto]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original) {
        throw new Exception('Reserva no encontrada o no pertenece a este recinto');
    }
    
    // Calcular nueva hora fin (mantener duración)
    $duracion_seg = strtotime($original['hora_fin']) - strtotime($original['hora_inicio']);
    $nueva_hora_fin = date('H:i:s', strtotime($nueva_hora_inicio) + $duracion_seg);
    
    // Actualizar en BD
    $id_cancha_final = $nueva_cancha ?? $original['id_cancha'];
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ?, updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt->execute([$id_cancha_final, $nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin, $id_reserva]);
    
    // Preparar cambios para correo
    $cambios = [];
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
    
    // Obtener datos actualizados para correo
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, rec.nombre as recinto_nombre, c.id_deporte,
               s.email, s.nombre as nombre_socio, s.alias
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $actualizada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ ENVIAR CORREO CON NOMBRE DE CLASE CORRECTO
    if ($actualizada && ($actualizada['email'] ?? $actualizada['email_cliente'] ?? null)) {
        // Verificar que la clase y método existen antes de llamar
        if (class_exists('BrevoMailer') && method_exists('BrevoMailer', 'enviarActualizacionConDatos')) {
            error_log("[Mover Reserva] 📧 Enviando correo con BrevoMailer...");
            BrevoMailer::enviarActualizacionConDatos($pdo, $actualizada, $cambios);
        } else {
            error_log("[Mover Reserva] ⚠️ BrevoMailer o método no encontrado. Saltando correo.");
        }
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true, 
        'message' => 'Reserva movida correctamente',
        'nueva_fecha' => $nueva_fecha,
        'nueva_hora' => substr($nueva_hora_inicio, 0, 5) . '-' . substr($nueva_hora_fin, 0, 5)
    ]);
    
} catch (Exception $e) {
    error_log("[Mover Reserva] ❌ ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (ob_get_level() > 0) { ob_end_flush(); }
?>