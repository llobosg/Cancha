<?php
// api/verificar_codigo_socio.php - Versión Blindada

// Configuración estricta para evitar errores de salida
ini_set('display_errors', 0);
error_reporting(0); // Silenciar warnings temporalmente para producción
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

// Respuesta por defecto
$response = ['success' => false, 'message' => 'Error interno del servidor'];

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Leer JSON crudo
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input || !isset($input['codigo'])) {
        throw new Exception('Datos inválidos. Se espera {"codigo": "1234"}');
    }

    $codigo = trim($input['codigo']);

    // Validar formato (4 dígitos numéricos)
    if (strlen($codigo) !== 4 || !ctype_digit($codigo)) {
        throw new Exception('Código inválido. Debe tener 4 dígitos.');
    }

    // Iniciar sesión si no está activa
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Buscar socio
    $stmt = $pdo->prepare("SELECT id_socio, email, email_verified FROM socios WHERE verification_code = ? AND email_verified = 0 LIMIT 1");
    $stmt->execute([$codigo]);
    $socio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$socio) {
        throw new Exception('Código incorrecto o ya utilizado.');
    }

    // Activar cuenta
    $updateStmt = $pdo->prepare("UPDATE socios SET email_verified = 1 WHERE id_socio = ?");
    $updateStmt->execute([$socio['id_socio']]);

    // Guardar sesión
    $_SESSION['id_socio'] = $socio['id_socio'];
    $_SESSION['user_email'] = $socio['email'];
    $_SESSION['verified'] = true;

    // Preparar respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Cuenta activada correctamente',
        'id_socio' => $socio['id_socio']
    ];

    // Limpiar sesiones temporales si existen
    if (isset($_SESSION['torneo_slug_post_registro'])) {
        $response['torneo_slug'] = $_SESSION['torneo_slug_post_registro'];
        unset($_SESSION['torneo_slug_post_registro']);
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

// Limpiar cualquier buffer previo y enviar JSON limpio
while (@ob_end_clean());
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>