<?php
// api/dar_baja_pareja_admin.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // Tu mailer existente

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar admin
if (!isset($_SESSION['id_recinto']) || !in_array($_SESSION['recinto_rol'] ?? '', ['admin', 'responsable'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pareja = (int)($data['id_pareja'] ?? 0);

if (!$id_pareja) {
    echo json_encode(['success' => false, 'message' => 'ID de pareja requerido']);
    exit;
}

try {
    // 1. Obtener datos de la pareja y torneo antes de borrar
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_torneo, t.nombre as torneo_nombre, t.fecha_inicio,
            s1.nombre as nombre1, s1.email as email1,
            s2.nombre as nombre2, s2.email as email2,
            r.nombre as recinto_nombre
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
        LEFT JOIN socios s1 ON pt.id_socio_1 = s1.id_socio
        LEFT JOIN socios s2 ON pt.id_socio_2 = s2.id_socio
        WHERE pt.id_pareja = ?
    ");
    $stmt->execute([$id_pareja]);
    $pareja = $stmt->fetch();
    
    if (!$pareja) {
        throw new Exception('Pareja no encontrada');
    }
    
    // 2. Eliminar la pareja y sus inscritos asociados
    $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND tipo_actividad = 'torneo_pareja'")
        ->execute([$pareja['id_torneo']]); // Ajusta según tu estructura
    
    $pdo->prepare("DELETE FROM parejas_torneo WHERE id_pareja = ?")->execute([$id_pareja]);
    
    // 3. ✅ Enviar correos de notificación a ambos jugadores
    try {
        if (class_exists('BrevoMailer')) {
            $mail = new BrevoMailer();
            $fecha_torneo = date('d/m/Y', strtotime($pareja['fecha_inicio']));
            $asunto = "📢 Información sobre tu inscripción en {$pareja['torneo_nombre']}";
            
            $cuerpoBase = "
                <h2>Hola {NOMBRE}</h2>
                <p>Te informamos que, por decisión administrativa del recinto <strong>{$pareja['recinto_nombre']}</strong>, se ha tramitado la baja de tu pareja en el torneo:</p>
                <p>🏆 <strong>{$pareja['torneo_nombre']}</strong><br>
                📅 Fecha: $fecha_torneo</p>
                <p>Agradecemos tu interés en participar y te invitamos a estar atento/a a futuros torneos y actividades en CanchaSport.</p>
                <p>Si tienes alguna duda o quieres más información, no dudes en contactarnos directamente:</p>
                <p>📧 {$pareja['recinto_nombre']} - admin@recinto.com</p>
                <p>¡Esperamos verte pronto en la cancha!</p>
                <hr>
                <small style='color:#888;'>Este es un mensaje automático de CanchaSport.</small>
            ";
            
            // Correo al Jugador 1
            if (!empty($pareja['email1'])) {
                $mail->setTo($pareja['email1'], $pareja['nombre1'])
                     ->setSubject($asunto)
                     ->setHtmlBody(str_replace('{NOMBRE}', $pareja['nombre1'], $cuerpoBase))
                     ->send();
            }
            
            // Correo al Jugador 2 (si existe)
            if (!empty($pareja['email2']) && !empty($pareja['nombre2'])) {
                $mail->setTo($pareja['email2'], $pareja['nombre2'])
                     ->setSubject($asunto)
                     ->setHtmlBody(str_replace('{NOMBRE}', $pareja['nombre2'], $cuerpoBase))
                     ->send();
            }
        }
    } catch (Exception $e_mail) {
        error_log("Error envío correo baja pareja: " . $e_mail->getMessage());
        // No interrumpimos el flujo si falla el correo
    }
    
    echo json_encode(['success' => true, 'message' => 'Pareja dada de baja correctamente']);
    
} catch (Exception $e) {
    error_log("Error dar_baja_pareja_admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>