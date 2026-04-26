<?php
// api/verificar_codigo_socio.php
// Eliminar cualquier output previo inmediatamente
if (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || !isset($data['codigo'])) {
        throw new Exception('Falta el código de verificación');
    }

    $codigo = trim($data['codigo']);

    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido (debe ser 4 dígitos)');
    }
    $stmt = $pdo->prepare("SELECT id_socio, email FROM socios WHERE verification_code = ? AND email_verified = 0 LIMIT 1");
    $stmt->execute([$codigo]);
    $socio = $stmt->fetch();

    if (!$socio) {
        throw new Exception('Código incorrecto o ya utilizado');
    }

    // Activar
    $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?")->execute([$socio['id_socio']]);
    
    $_SESSION['id_socio'] = $socio['id_socio'];
    $_SESSION['user_email'] = $socio['email'];

    echo json_encode(['success' => true, 'message' => 'Cuenta activada']);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    // Asegurar que la respuesta sea JSON válido incluso en error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>