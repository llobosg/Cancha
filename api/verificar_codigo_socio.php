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
        throw new Exception('Código inválido. Debe tener 4 dígitos.');
    }

    // Buscar socio pendiente con ese código
    $stmt = $pdo->prepare("
        SELECT id_socio, email, id_club 
        FROM socios 
        WHERE verification_code = ? AND email_verified = 0
    ");
    $stmt->execute([$codigo]);
    $socio = $stmt->fetch();

    if (!$socio) {
        throw new Exception('Código incorrecto o ya verificado.');
    }

    // Activar cuenta
    $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?")
         ->execute([$socio['id_socio']]);

    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['id_socio'] = $socio['id_socio'];
    $_SESSION['user_email'] = $socio['email'];
    $_SESSION['modo_individual'] = ($socio['id_club'] === null);

    if ($socio['id_club']) {
        // Recuperar slug del club
        $stmt = $pdo->prepare("SELECT email_responsable FROM clubs WHERE id_club = ?");
        $stmt->execute([$socio['id_club']]);
        $email_resp = $stmt->fetchColumn();

        if ($email_resp) {
            $club_slug = substr(md5($socio['id_club'] . $email_resp), 0, 8);
            $_SESSION['club_id'] = $socio['id_club'];
            $_SESSION['current_club'] = $club_slug;
        }
    } else {
        $_SESSION['club_id'] = null;
        $_SESSION['current_club'] = null;
    }

    // Verificar contexto post-registro (torneo)
    $response = ['success' => true];

    if (isset($_SESSION['torneo_slug_post_registro'])) {
        $response['torneo_slug'] = $_SESSION['torneo_slug_post_registro'];
        unset($_SESSION['torneo_slug_post_registro']);
        unset($_SESSION['torneo_code_post_registro']);
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Verificación código socio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>