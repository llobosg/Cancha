<?php
// api/login_google.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';

    if (!$token) {
        throw new Exception('Token no proporcionado');
    }

    // Verificar token con Google
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/tokeninfo?id_token=$token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Token inválido');
    }

    $payload = json_decode($response, true);
    if (!$payload || !isset($payload['email'])) {
        throw new Exception('Payload inválido');
    }

    $email = $payload['email'];
    $name = $payload['name'] ?? '';

    // Buscar PRIMERO en clubs como responsable
    $stmt_club = $pdo->prepare("
        SELECT id_club, email_responsable, nombre 
        FROM clubs 
        WHERE email_responsable = ? AND email_verified = 1
    ");
    $stmt_club->execute([$email]);
    $club_responsable = $stmt_club->fetch();

    if ($club_responsable) {
        // Es responsable de un club - buscar o crear socio
        $stmt_socio = $pdo->prepare("
            SELECT id_socio, es_responsable FROM socios 
            WHERE email = ? AND id_club = ?
        ");
        $stmt_socio->execute([$email, $club_responsable['id_club']]);
        $socio = $stmt_socio->fetch();

        if (!$socio) {
            // Crear socio automático para responsable
            $stmt_create = $pdo->prepare("
                INSERT INTO socios (id_club, email, nombre, alias, es_resemplazable, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt_create->execute([
                $club_responsable['id_club'],
                $email,
                $name,
                'Responsable'
            ]);
        }

        $club_slug = substr(md5($club_responsable['id_club'] . $club_responsable['email_responsable']), 0, 8);
        
        echo json_encode([
            'success' => true,
            'action' => 'redirect_existing',
            'club_slug' => $club_slug,
            'is_responsable' => true,
            'redirect' => 'pages/dashboard_socio.php?id_club=' . $club_slug
        ]);
        exit;
    }

    // Buscar en socios como miembro normal
    $stmt_socio = $pdo->prepare("
        SELECT s.id_socio, s.id_club, s.alias, s.es_responsable, c.nombre as club_nombre, c.email_responsable
        FROM socios s
        JOIN clubs c ON s.id_club = c.id_club
        WHERE s.email = ? AND c.email_verified = 1
    ");
    $stmt_socio->execute([$email]);
    $socio = $stmt_socio->fetch();

    if ($socio) {
        // Socio existente
        $club_slug = substr(md5($socio['id_club'] . $socio['email_responsable']), 0, 8);
        
        echo json_encode([
            'success' => true,
            'action' => 'redirect_existing',
            'club_slug' => $club_slug,
            'is_responsable' => (bool)$socio['es_responsable'],
            'redirect' => 'pages/dashboard_socio.php?id_club=' . $club_slug
        ]);
        exit;
    }

    // Usuario completamente nuevo
    echo json_encode([
        'success' => false,
        'action' => 'welcome_new',
        'email' => $email,
        'message' => 'Bienvenido a Cancha'
    ]);

} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'action' => 'error', 'message' => $e->getMessage()]);
}
?>