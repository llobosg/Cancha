<?php
// api/aceptar_invitacion_pareja.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$data = json_decode(file_get_contents('php://input'), true);
$code_pareja = $data['codigo_pareja'] ?? '';

// Validar sesión
if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_socio_actual = $_SESSION['id_socio'];

try {
    if (!$code_pareja) {
        throw new Exception('Código de invitación requerido');
    }

    // 1. Buscar la pareja
    $stmt = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_torneo, pt.id_socio_1 
        FROM parejas_torneo pt 
        WHERE pt.codigo_pareja = ? AND pt.id_socio_2 IS NULL
    ");
    $stmt->execute([$code_pareja]);
    $pareja = $stmt->fetch();

    if (!$pareja) {
        throw new Exception('Invitación inválida, expirada o ya completada');
    }

    // 2. Verificar que no sea el mismo usuario aceptando su propia invitación (opcional, pero buena práctica)
    if ($pareja['id_socio_1'] == $id_socio_actual) {
        throw new Exception('No puedes unirte a tu propia pareja como segundo jugador.');
    }

    // 3. Verificar si está inscrito en OTRO torneo activo (Regla de negocio opcional)
    // Si quieres permitir múltiples inscripciones, comenta este bloque.
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE (id_socio_1 = ? OR id_socio_2 = ?) AND id_pareja != ?");
    $stmt_check->execute([$id_socio_2, $id_socio_2, $pareja['id_pareja']]);
    
    // Si encuentras otras inscripciones, decides si bloqueas o permites.
    // Aquí asumimos que SÍ puede estar en otros torneos, pero no duplicarse en el mismo.
    
    // 4. Actualizar la pareja agregando el segundo socio
       $stmt_update = $pdo->prepare("
        UPDATE parejas_torneo 
        SET id_socio_2 = ?, estado = 'completa' 
        WHERE id_pareja = ?
    ");
    $stmt_update->execute([$id_socio_actual, $pareja['id_pareja']]);

    // 5. Enviar correo de confirmación
    require_once __DIR__ . '/../includes/reserva_mailer.php';
    
    $stmt_s1 = $pdo->prepare("SELECT nombre, email FROM socios WHERE id_socio = ?");
    $stmt_s1->execute([$pareja['id_socio_1']]);
    $socio_1 = $stmt_s1->fetch(PDO::FETCH_ASSOC);

    $stmt_s2 = $pdo->prepare("SELECT nombre, email FROM socios WHERE id_socio = ?");
    $stmt_s2->execute([$id_socio_2]);
    $socio_2 = $stmt_s2->fetch(PDO::FETCH_ASSOC);

    if (class_exists('BrevoMailer')) {
        $mail = new BrevoMailer();
        $mail->setTo($socio_2['email'], $socio_2['nombre'])
             ->setSubject("✅ ¡Pareja Completa en {$pareja['torneo_nombre']}!")
             ->setHtmlBody("
                <h2>¡Hola {$socio_2['nombre']}!</h2>
                <p>Te has unido correctamente a la pareja de <strong>{$socio_1['nombre']}</strong> en el torneo <strong>{$pareja['torneo_nombre']}</strong>.</p>
                <p>¡Buena suerte en el torneo!</p>
             ")
             ->send();
             
        // Notificar al invitante
        $mail2 = new BrevoMailer();
        $mail2->setTo($socio_1['email'], $socio_1['nombre'])
              ->setSubject("🎉 ¡Tu pareja se ha unido!")
              ->setHtmlBody("
                 <h2>¡Hola {$socio_1['nombre']}!</h2>
                 <p><strong>{$socio_2['nombre']}</strong> ha aceptado tu invitación y completado la pareja para el torneo <strong>{$pareja['torneo_nombre']}</strong>.</p>
              ")
              ->send();
    }

    echo json_encode(['success' => true, 'message' => 'Pareja completada']);

} catch (Exception $e) {
    error_log("Error aceptar invitación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>