<?php
require_once __DIR__ . '/includes/config.php';

// Buscar jugadores temporales no migrados
$stmt = $pdo->prepare("
    SELECT jt.*
    FROM jugadores_temporales jt
    LEFT JOIN socios s ON jt.email = s.email
    WHERE s.id_socio IS NULL
");
$stmt->execute();
$temporales = $stmt->fetchAll();

foreach ($temporales as $t) {
    // Insertar en socios
    $pdo->prepare("
        INSERT INTO socios (nombre, alias, email, celular, genero, deporte, datos_completos, activo, email_verified)
        VALUES (?, ?, ?, ?, ?, 'padel', 0, 'Si', 1)
    ")->execute([
        $t['nombre'],
        $t['nombre'],
        $t['email'],
        $t['telefono'] ?? '',
        'Otro'
    ]);

    // Enviar correo
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    $mail->setFrom('contacto@canchasport.com', 'CanchaSport');
    $mail->addAddress($t['email']);
    $mail->Subject = '🎾 ¡Gracias por jugar en CanchaSport!';
    $mail->Body = "
        <h2>¡Hola {$t['nombre']}!</h2>
        <p>Gracias por participar en nuestros torneos de Pádel.</p>
        <p>Para que puedas ver tus resultados, ranking y recibir invitaciones a futuros torneos, completa tu perfil aquí:</p>
        <p><a href='https://canchasport.com/pages/completar_perfil.php?modo=individual&email=" . urlencode($t['email']) . "' style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;'>Completar mi perfil</a></p>
        <p>¡Nos vemos en la próxima cancha! 🎾</p>
    ";
    $mail->send();
}

echo "✅ Migración completada: " . count($temporales) . " jugadores convertidos.\n";
?>