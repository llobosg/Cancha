<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/brevo_mailer.php';
session_start();

try {
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Acceso denegado');
    }

    $id_pareja = (int)($_POST['id_pareja'] ?? 0);
    if (!$id_pareja) {
        throw new Exception('ID de pareja inválido');
    }

    // Obtener datos de la pareja y torneo
    $stmt = $pdo->prepare("
        SELECT 
            pt.id_socio_1, pt.id_socio_2,
            pt.id_jugador_temp_1, pt.id_jugador_temp_2,
            t.nombre AS torneo_nombre,
            t.slug
        FROM parejas_torneo pt
        JOIN torneos t ON pt.id_torneo = t.id_torneo
        WHERE pt.id_pareja = ? AND t.id_recinto = ?
    ");
    $stmt->execute([$id_pareja, $_SESSION['id_recinto']]);
    $pareja = $stmt->fetch();

    if (!$pareja) {
        throw new Exception('Pareja no encontrada o no pertenece a tu recinto');
    }

    // === Eliminar solo del torneo (no del sistema de socios) ===
    $pdo->prepare("DELETE FROM parejas_torneo WHERE id_pareja = ?")->execute([$id_pareja]);

    // === Enviar correos a ambos miembros ===
    $emails_enviados = [];

    // Socio 1
    if ($pareja['id_socio_1']) {
        $stmt_socio = $pdo->prepare("SELECT email, alias FROM socios WHERE id_socio = ?");
        $stmt_socio->execute([$pareja['id_socio_1']]);
        $socio1 = $stmt_socio->fetch();
        if ($socio1 && !in_array($socio1['email'], $emails_enviados)) {
            enviarCorreoEliminacion($socio1['email'], $socio1['alias'], $pareja['torneo_nombre']);
            $emails_enviados[] = $socio1['email'];
        }
    } elseif ($pareja['id_jugador_temp_1']) {
        $stmt_temp = $pdo->prepare("SELECT email, nombre FROM jugadores_temporales WHERE id_jugador = ?");
        $stmt_temp->execute([$pareja['id_jugador_temp_1']]);
        $temp1 = $stmt_temp->fetch();
        if ($temp1 && !in_array($temp1['email'], $emails_enviados)) {
            enviarCorreoEliminacion($temp1['email'], $temp1['nombre'], $pareja['torneo_nombre']);
            $emails_enviados[] = $temp1['email'];
        }
    }

    // Socio 2
    if ($pareja['id_socio_2']) {
        $stmt_socio = $pdo->prepare("SELECT email, alias FROM socios WHERE id_socio = ?");
        $stmt_socio->execute([$pareja['id_socio_2']]);
        $socio2 = $stmt_socio->fetch();
        if ($socio2 && !in_array($socio2['email'], $emails_enviados)) {
            enviarCorreoEliminacion($socio2['email'], $socio2['alias'], $pareja['torneo_nombre']);
            $emails_enviados[] = $socio2['email'];
        }
    } elseif ($pareja['id_jugador_temp_2']) {
        $stmt_temp = $pdo->prepare("SELECT email, nombre FROM jugadores_temporales WHERE id_jugador = ?");
        $stmt_temp->execute([$pareja['id_jugador_temp_2']]);
        $temp2 = $stmt_temp->fetch();
        if ($temp2 && !in_array($temp2['email'], $emails_enviados)) {
            enviarCorreoEliminacion($temp2['email'], $temp2['nombre'], $pareja['torneo_nombre']);
            $emails_enviados[] = $temp2['email'];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '✅ Pareja eliminada del torneo y notificada por correo.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    error_log("Error en eliminar_pareja_torneo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enviarCorreoEliminacion($email, $nombre, $torneo) {
    $mailer = new BrevoMailer();
    $mailer->setTo($email, $nombre);
    $mailer->setSubject('ℹ️ Actualización sobre tu participación en ' . $torneo);
    $mailer->setHtmlBody("
        <h2>¡Hola {$nombre}!</h2>
        <p>Gracias por tu interés en participar en el torneo <strong>{$torneo}</strong>.</p>
        <p>Por razones organizativas, tu inscripción ha sido ajustada y ya no forma parte del fixture actual.</p>
        <p>¡No te preocupes! Sigue siendo parte de la comunidad CanchaSport y estaremos encantados de tenerte en futuros eventos.</p>
        <p>¿Tienes dudas? Responde a este correo o escríbenos en WhatsApp.</p>
        <p>Con cariño,<br><strong>El equipo de CanchaSport</strong> 🎾</p>
    ");
    $mailer->send();
}
?>