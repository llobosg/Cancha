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
    $picture = $payload['picture'] ?? '';

    // Buscar socio por email
    $stmt = $pdo->prepare("
        SELECT s.id_socio, s.id_club, s.alias, c.nombre as club_nombre, c.email_responsable
        FROM socios s
        JOIN clubs c ON s.id_club = c.id_club
        WHERE s.email = ? AND s.email_verified = 1
    ");
    $stmt->execute([$email]);
    $socio = $stmt->fetch();

    if (!$socio) {
        // Socio no existe → redirigir al landing con mensaje
        echo json_encode([
            'success' => false,
            'message' => 'Primero debes inscribirte en un club',
            'redirect' => 'https://cancha-web.up.railway.app/pages/index.php?error=not_registered'
        ]);
        exit;
    }

    // Generar slug del club
    $club_slug = substr(md5($socio['id_club'] . $socio['email_responsable']), 0, 8);

    echo json_encode([
        'success' => true,
        'club_slug' => $club_slug,
        'alias' => $socio['alias'] ?: $name
    ]);

} catch (Exception $e) {
    error_log("Google login error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>