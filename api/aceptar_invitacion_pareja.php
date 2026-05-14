<?php
// api/aceptar_invitacion_pareja.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);
if (ob_get_level()) ob_clean();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // Tu mailer existente

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code_pareja = $data['codigo_pareja'] ?? '';
$id_socio_2 = $_SESSION['id_socio'];

try {
    if (!$code_pareja) throw new Exception('Código de invitación requerido');

    // 1. Buscar pareja y datos del torneo
    $stmt = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_torneo, pt.id_socio_1, t.nombre as torneo_nombre, t.fecha_inicio, r.nombre as recinto_nombre
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        JOIN recintos_deportivos r ON t.id_recinto = r.id_recinto
        WHERE pt.codigo_pareja = ? AND pt.id_socio_2 IS NULL
    ");
    $stmt->execute([$code_pareja]);
    $pareja = $stmt->fetch();
    if (!$pareja) throw new Exception('Invitación inválida, expirada o ya completada');

    // 2. Obtener datos de ambos socios
    $stmt_s1 = $pdo->prepare("SELECT nombre, email FROM socios WHERE id_socio = ?");
    $stmt_s1->execute([$pareja['id_socio_1']]);
    $socio_1 = $stmt_s1->fetch();

    $stmt_s2 = $pdo->prepare("SELECT nombre, email FROM socios WHERE id_socio = ?");
    $stmt_s2->execute([$id_socio_2]);
    $socio_2 = $stmt_s2->fetch();

    // 3. Actualizar estado de la pareja
    $stmt_update = $pdo->prepare("UPDATE parejas_torneo SET id_socio_2 = ?, estado = 'completa' WHERE id_pareja = ?");
    $stmt_update->execute([$id_socio_2, $pareja['id_pareja']]);

    // 4. ✅ ENVÍO DE CORREOS (envuelto en try-catch para no romper el flujo si falla)
    try {
        if (class_exists('BrevoMailer')) {
            $mail = new BrevoMailer();
            $fecha_torneo = date('d/m/Y H:i', strtotime($pareja['fecha_inicio']));
            $torneo_nombre = $pareja['torneo_nombre'];

            // 📩 Correo al Jugador 1 (Principal)
            $mail->setTo($socio_1['email'], $socio_1['nombre'])
                 ->setSubject("🎉 ¡Tu pareja se unió al torneo $torneo_nombre!")
                 ->setHtmlBody("
                    <h2>¡Hola {$socio_1['nombre']}!</h2>
                    <p>Tu pareja <strong>{$socio_2['nombre']}</strong> ha aceptado tu invitación.</p>
                    <p>✅ <strong>Pareja completa</strong> para el torneo <strong>{$torneo_nombre}</strong>.</p>
                    <p>📅 Fecha: $fecha_torneo<br>📍 Recinto: {$pareja['recinto_nombre']}</p>
                    <p>¡Mucha suerte en la competencia!</p>
                 ")->send();

            // 📩 Correo al Jugador 2 (Invitado)
            $mail->setTo($socio_2['email'], $socio_2['nombre'])
                 ->setSubject("✅ ¡Confirmado: Eres pareja de {$socio_1['nombre']}!")
                 ->setHtmlBody("
                    <h2>¡Bienvenido/a {$socio_2['nombre']}!</h2>
                    <p>Te has unido exitosamente a la pareja de <strong>{$socio_1['nombre']}</strong>.</p>
                    <p>🏆 Estás oficialmente inscrito en <strong>{$torneo_nombre}</strong>.</p>
                    <p>📅 Fecha: $fecha_torneo<br>📍 Recinto: {$pareja['recinto_nombre']}</p>
                    <p>Prepárate para competir.</p>
                 ")->send();

            // 📩 Correo al Admin del Recinto
            // Usamos el email del recinto o un fallback seguro
            $admin_email = $pareja['email_responsable'] ?? 'admin@canchasport.com'; 
            // Si tu estructura guarda el admin en otra tabla, ajusta aquí.
            
            $mail->setTo($admin_email, 'Administrador')
                 ->setSubject("📢 Nueva pareja inscrita en $torneo_nombre")
                 ->setHtmlBody("
                    <h3>Nueva inscripción completada</h3>
                    <p><strong>Torneo:</strong> {$torneo_nombre}</p>
                    <p><strong>Pareja:</strong> {$socio_1['nombre']} & {$socio_2['nombre']}</p>
                    <p><strong>Fecha:</strong> $fecha_torneo</p>
                    <p>Revisa el panel de administración para actualizar el bracket.</p>
                 ")->send();
        }
    } catch (Exception $e_mail) {
        error_log("Error envío correos torneo: " . $e_mail->getMessage());
        // No lanzamos excepción para no romper la inscripción del usuario
    }

    echo json_encode(['success' => true, 'message' => 'Pareja completada']);
    exit;

} catch (Exception $e) {
    error_log("Error aceptar invitación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>