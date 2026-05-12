<?php
// api/aceptar_invitacion_pareja.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start(); // Asegurar que la sesión esté activa

if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado. Debes estar logueado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code_pareja = $data['codigo_pareja'] ?? '';
$id_socio_2 = $_SESSION['id_socio'];

try {
    if (!$code_pareja) {
        throw new Exception('Código de invitación requerido');
    }

    // 1. Buscar la pareja existente con ese código
    $stmt_pareja = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_torneo, pt.id_socio_1, t.nombre as torneo_nombre
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE pt.codigo_pareja = ? AND pt.id_socio_2 IS NULL
    ");
    $stmt_pareja->execute([$code_pareja]);
    $pareja = $stmt_pareja->fetch(PDO::FETCH_ASSOC);

    if (!$pareja) {
        throw new Exception('Invitación inválida, expirada o ya completada');
    }

    // 2. Verificar que el socio no esté ya inscrito en otro torneo activo
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_socio_1 = ? OR id_socio_2 = ?");
    $stmt_check->execute([$id_socio_2, $id_socio_2]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception('Ya estás inscrito en otra pareja');
    }

    // 3. Actualizar la pareja agregando el segundo socio
    $stmt_update = $pdo->prepare("
        UPDATE parejas_torneo 
        SET id_socio_2 = ?, estado = 'completa'
        WHERE id_pareja = ?
    ");
    $stmt_update->execute([$id_socio_2, $pareja['id_pareja']]);

    // 4. Enviar correo de confirmación (opcional)
    require_once __DIR__ . '/../includes/reserva_mailer.php';
    
    $stmt_s1 = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
    $stmt_s1->execute([$pareja['id_socio_1']]);
    $nombre_s1 = $stmt_s1->fetchColumn();

    $stmt_s2 = $pdo->prepare("SELECT nombre, email FROM socios WHERE id_socio = ?");
    $stmt_s2->execute([$id_socio_2]);
    $socio_2 = $stmt_s2->fetch(PDO::FETCH_ASSOC);

    if (class_exists('BrevoMailer')) {
        $mail = new BrevoMailer();
        $mail->setTo($socio_2['email'], $socio_2['nombre'])
             ->setSubject("✅ ¡Pareja Completa en {$pareja['torneo_nombre']}!")
             ->setHtmlBody("
                <h2>¡Hola {$socio_2['nombre']}!</h2>
                <p>Te has unido correctamente a la pareja de <strong>{$nombre_s1}</strong> en el torneo <strong>{$pareja['torneo_nombre']}</strong>.</p>
                <p>¡Buena suerte en el torneo!</p>
             ")
             ->send();
    }

    echo json_encode(['success' => true, 'message' => 'Pareja completada']);

} catch (Exception $e) {
    error_log("Error aceptar invitación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>