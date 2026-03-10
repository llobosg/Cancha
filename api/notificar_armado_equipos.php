<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;

function notificarArmadoEquipos($id_reserva, $pdo) {
    // Obtener datos del partido
    $stmt = $pdo->prepare("
        SELECT 
            r.fecha,
            r.hora_inicio,
            c.nombre_cancha,
            cl.nombre as club_nombre
        FROM reservas r
        JOIN canchas ca ON r.id_cancha = ca.id_cancha
        JOIN recintos_deportivos c ON ca.id_recinto = c.id_recinto
        JOIN clubs cl ON r.id_club = cl.id_club
        WHERE r.id_reserva = ?
    ");
    $stmt->execute([$id_reserva]);
    $partido = $stmt->fetch();

    if (!$partido) return;

    // Obtener equipos completos
    $stmt_equipos = $pdo->prepare("
        SELECT 
            ep.nombre_equipo,
            s.alias,
            s.email,
            s.id_socio
        FROM equipos_partido ep
        JOIN jugadores_equipo je ON ep.id_equipo = je.id_equipo
        JOIN socios s ON je.id_socio = s.id_socio
        WHERE ep.id_reserva = ?
        ORDER BY ep.nombre_equipo, s.alias
    ");
    $stmt_equipos->execute([$id_reserva]);
    $jugadores = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);

    // Separar equipos
    $rojos = array_filter($jugadores, fn($j) => $j['nombre_equipo'] === 'Rojos');
    $blancos = array_filter($jugadores, fn($j) => $j['nombre_equipo'] === 'Blancos');

    // Preparar listas para notificación
    $lista_rojos = implode(', ', array_column($rojos, 'alias'));
    $lista_blancos = implode(', ', array_column($blancos, 'alias'));

    // Notificar a cada jugador
    foreach ($jugadores as $jugador) {
        $equipo_propio = $jugador['nombre_equipo'];
        $equipo_rival = $equipo_propio === 'Rojos' ? 'Blancos' : 'Rojos';
        $lista_propia = $equipo_propio === 'Rojos' ? $lista_rojos : $lista_blancos;
        $lista_rival = $equipo_propio === 'Rojos' ? $lista_blancos : $lista_rojos;

        $mensaje = "⚽ ¡Equipos armados para {$partido['fecha']} {$partido['hora_inicio']}!\n\n" .
                   "🏟️ {$partido['nombre_cancha']} - {$partido['club_nombre']}\n\n" .
                   "🔴 TU EQUIPO ({$equipo_propio}):\n{$lista_propia}\n\n" .
                   "⚪ EQUIPO RIVAL ({$equipo_rival}):\n{$lista_rival}";

        // Enviar correo
        enviarCorreoEquipo($jugador['email'], $mensaje);

        // Enviar push
        enviarPushEquipo($jugador['id_socio'], $mensaje);
    }
}

function enviarCorreoEquipo($email, $mensaje) {
    $to = $email;
    $subject = '⚽ Equipos armados - CanchaSport';
    $body = nl2br(htmlspecialchars($mensaje));
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@canchasport.com\r\n";
    
    mail($to, $subject, $body, $headers);
}

function enviarPushEquipo($id_socio, $mensaje) {
    global $pdo;
    
    // Obtener suscripciones del socio
    $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM suscripciones_push WHERE id_socio = ?");
    $stmt->execute([$id_socio]);
    $suscripciones = $stmt->fetchAll();

    if (empty($suscripciones)) return;

    $webPush = new WebPush([
        'VAPID' => [
            'subject' => 'https://canchasport.com',
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ]);

    foreach ($suscripciones as $sub) {
        $webPush->queueNotification(
            $sub['endpoint'],
            json_encode([
                'title' => '⚽ Equipos armados',
                'body' => substr($mensaje, 0, 120) . '...',
                'icon' => '/assets/icons/logo2-icon-192x192.png',
                'badge' => '/assets/icons/logo2-icon-192x192.png'
            ]),
            null,
            ['TTL' => 3600]
        );
    }
    $webPush->flush();
}
?>