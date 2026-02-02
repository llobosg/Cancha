<?php
// Enviar notificación a todos los socios del club
function enviarNotificacionEvento($club_id, $titulo, $mensaje) {
    // Obtener tokens de notificación de todos los socios
    $stmt = $pdo->prepare("
        SELECT token_notificacion 
        FROM socios 
        WHERE id_club = ? AND token_notificacion IS NOT NULL
    ");
    $stmt->execute([$club_id]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Enviar a Firebase Cloud Messaging (FCM)
    foreach ($tokens as $token) {
        enviarPushFCM($token, $titulo, $mensaje);
    }
}

function enviarPushFCM($token, $titulo, $mensaje) {
    $data = [
        'to' => $token,
        'notification' => [
            'title' => $titulo,
            'body' => $mensaje,
            'icon' => '/assets/icons/icon-192x192.png'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $_ENV['FCM_SERVER_KEY'],
        'Content-Type: application/json'
    ]);
    curl_exec($ch);
    curl_close($ch);
}
?>