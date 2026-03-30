<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $codigo = $input['codigo'] ?? '';

    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido');
    }

    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Buscar socio por código (sin id_club)
    $stmt = $pdo->prepare("
        SELECT id_socio, email, email_verified 
        FROM socios 
        WHERE verification_code = ? AND email_verified = 0
    ");
    $stmt->execute([$codigo]);
    $socio = $stmt->fetch();

    if (!$socio) {
        throw new Exception('Código incorrecto o ya verificado');
    }

    // Activar cuenta
    $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?")
         ->execute([$socio['id_socio']]);

    // Guardar en sesión
    $_SESSION['id_socio'] = $socio['id_socio'];
    $_SESSION['user_email'] = $socio['email'];

    // Verificar si hay contexto de torneo o club
    $response = ['success' => true];

    if (isset($_SESSION['torneo_slug_post_registro'])) {
        $response['torneo_slug'] = $_SESSION['torneo_slug_post_registro'];
        unset($_SESSION['torneo_slug_post_registro']);
        unset($_SESSION['torneo_code_post_registro']);
    } elseif (isset($_SESSION['club_slug_post_registro'])) {
        $response['club_slug'] = $_SESSION['club_slug_post_registro'];
        unset($_SESSION['club_slug_post_registro']);
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Verificación código socio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>