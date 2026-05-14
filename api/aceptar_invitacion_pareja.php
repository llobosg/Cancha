<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(0);
if (ob_get_level()) ob_clean(); // Limpiar buffer previo

// Suprimir warnings para devolver JSON limpio
error_reporting(0); 

require_once __DIR__ . '/../includes/config.php';

// Verificar sesión explícitamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);
$code_pareja = $data['codigo_pareja'] ?? '';

// Validar sesión
if (!isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_socio_actual = $_SESSION['id_socio'];

try {
    if (!$code_pareja) {
        throw new Exception('Código de invitación requerido');
    }

    // 1. Buscar la pareja
    $stmt = $pdo->prepare("
        SELECT pt.id_pareja, pt.id_torneo, pt.id_socio_1 
        FROM parejas_torneo pt 
        WHERE pt.codigo_pareja = ? AND pt.id_socio_2 IS NULL
    ");
    $stmt->execute([$code_pareja]);
    $pareja = $stmt->fetch();

    if (!$pareja) {
        throw new Exception('Invitación inválida, expirada o ya completada');
    }

    // 2. Verificar que no sea el mismo usuario
    if ($pareja['id_socio_1'] == $id_socio_actual) {
        throw new Exception('No puedes unirte a tu propia pareja.');
    }

    // 3. Actualizar la pareja
    $stmt_update = $pdo->prepare("
        UPDATE parejas_torneo 
        SET id_socio_2 = ?, estado = 'completa' 
        WHERE id_pareja = ?
    ");
    $stmt_update->execute([$id_socio_actual, $pareja['id_pareja']]);

    echo json_encode(['success' => true, 'message' => 'Pareja completada']);
    exit;

} catch (Exception $e) {
    error_log("Error aceptar invitación: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>