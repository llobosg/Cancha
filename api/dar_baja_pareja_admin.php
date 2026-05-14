<?php
// api/dar_baja_pareja_admin.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

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
    // Obtener datos antes de borrar
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
    
    if (!$pareja) throw new Exception('Pareja no encontrada');
    
    // Eliminar pareja
    $pdo->prepare("DELETE FROM parejas_torneo WHERE id_pareja = ?")->execute([$id_pareja]);
    
    // ✅ Enviar correos
    try {
        if (class_exists('BrevoMailer')) {
            $mail = new BrevoMailer();
            $fecha = date('d/m/Y', strtotime($pareja['fecha_inicio']));
            $asunto = "📢 Información sobre tu inscripción en {$pareja['torneo_nombre']}";
            
            $cuerpo = "
                <h2>Hola {NOMBRE}</h2>
                <p>Te informamos que, por decisión administrativa del recinto <strong>{$pareja['recinto_nombre']}</strong>, se ha tramitado la baja de tu pareja en:</p>
                <p>🏆 <strong>{$pareja['torneo_nombre']}</strong><br>📅 Fecha: $fecha</p>
                <p>Agradecemos tu interés y te invitamos a participar en futuros torneos.</p>
                <p>Para dudas, contáctanos: {$pareja['recinto_nombre']} - admin@recinto.com</p>
                <hr><small style='color:#888;'>Mensaje automático de CanchaSport.</small>
            ";
            
            if (!empty($pareja['email1'])) {
                $mail->setTo($pareja['email1'], $pareja['nombre1'])
                     ->setSubject($asunto)
                     ->setHtmlBody(str_replace('{NOMBRE}', $pareja['nombre1'], $cuerpo))
                     ->send();
            }
            if (!empty($pareja['email2']) && !empty($pareja['nombre2'])) {
                $mail->setTo($pareja['email2'], $pareja['nombre2'])
                     ->setSubject($asunto)
                     ->setHtmlBody(str_replace('{NOMBRE}', $pareja['nombre2'], $cuerpo))
                     ->send();
            }
        }
    } catch (Exception $e_mail) {
        error_log("Error envío correo baja: " . $e_mail->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Baja registrada']);
    
} catch (Exception $e) {
    error_log("Error dar_baja_pareja: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>