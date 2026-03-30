<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('No autenticado');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id_pareja = (int)($input['id_pareja'] ?? 0);

    if (!$id_pareja) {
        throw new Exception('Pareja no especificada');
    }

    // Verificar que el socio pertenece a esta pareja
    $stmt = $pdo->prepare("
        SELECT id_socio_1, id_socio_2 
        FROM parejas_torneo 
        WHERE id_pareja = ? AND estado = 'completa'
    ");
    $stmt->execute([$id_pareja]);
    $pareja = $stmt->fetch();

    if (!$pareja) {
        throw new Exception('Pareja no encontrada o ya reemplazada');
    }

    $id_socio = $_SESSION['id_socio'];
    if ($pareja['id_socio_1'] != $id_socio && $pareja['id_socio_2'] != $id_socio) {
        throw new Exception('No perteneces a esta pareja');
    }

    // Generar nuevo código de reemplazo
    $nuevo_codigo = bin2hex(random_bytes(4)); // 8 caracteres hex

    // Actualizar la pareja a "esperando_reemplazo"
    $pdo->prepare("
        UPDATE parejas_torneo 
        SET codigo_pareja = ?, estado = 'esperando_reemplazo', id_socio_ausente = ?
        WHERE id_pareja = ?
    ")->execute([
        $nuevo_codigo,
        $pareja['id_socio_1'] == $id_socio ? $pareja['id_socio_2'] : $pareja['id_socio_1'],
        $id_pareja
    ]);

    $link = "https://canchasport.com/pages/registro_socio.php?torneo_reemplazo={$nuevo_codigo}";

    echo json_encode(['success' => true, 'link' => $link]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>