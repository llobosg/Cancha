<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $slug = $_POST['slug'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$slug || strlen($slug) !== 8 || !$nombre || !$email) {
        throw new Exception('Datos incompletos');
    }

    // Buscar torneo
    $stmt = $pdo->prepare("SELECT id_torneo FROM torneos WHERE slug = ?");
    $stmt->execute([$slug]);
    $torneo = $stmt->fetch();
    if (!$torneo) throw new Exception('Torneo no encontrado');

    // Buscar en socios
    $stmt_socio = $pdo->prepare("
        SELECT s.id_socio 
        FROM socios s
        JOIN parejas_torneo pt ON s.id_socio = pt.id_socio_1
        WHERE pt.id_torneo = ? AND s.nombre = ? AND s.email = ?
          AND pt.estado = 'esperando_pareja'
    ");
    $stmt_socio->execute([$torneo['id_torneo'], $nombre, $email]);
    $socio = $stmt_socio->fetch();

    if ($socio) {
        // Recuperar código
        $stmt_code = $pdo->prepare("
            SELECT codigo_pareja 
            FROM parejas_torneo 
            WHERE id_torneo = ? AND id_socio_1 = ? AND estado = 'esperando_pareja'
        ");
        $stmt_code->execute([$torneo['id_torneo'], $socio['id_socio']]);
        $code = $stmt_code->fetchColumn();
        if ($code) {
            echo json_encode([
                'success' => true,
                'redirect' => "/pages/torneo_pair.php?slug={$slug}&code={$code}"
            ]);
            exit;
        }
    }

    // Buscar en temporales
    $stmt_temp = $pdo->prepare("
        SELECT jt.id_jugador 
        FROM jugadores_temporales jt
        JOIN parejas_torneo pt ON jt.id_jugador = pt.id_jugador_temp_1
        WHERE pt.id_torneo = ? AND jt.nombre = ? AND jt.email = ?
          AND pt.estado = 'esperando_pareja'
    ");
    $stmt_temp->execute([$torneo['id_torneo'], $nombre, $email]);
    $temp = $stmt_temp->fetch();

    if ($temp) {
        $stmt_code = $pdo->prepare("
            SELECT codigo_pareja 
            FROM parejas_torneo 
            WHERE id_torneo = ? AND id_jugador_temp_1 = ? AND estado = 'esperando_pareja'
        ");
        $stmt_code->execute([$torneo['id_torneo'], $temp['id_jugador']]);
        $code = $stmt_code->fetchColumn();
        if ($code) {
            echo json_encode([
                'success' => true,
                'redirect' => "/pages/torneo_pair.php?slug={$slug}&code={$code}"
            ]);
            exit;
        }
    }

    throw new Exception('No estás inscrito como primer jugador en este torneo');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>