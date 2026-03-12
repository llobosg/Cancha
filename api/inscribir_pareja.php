<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $slug = $data['slug'] ?? '';
    $id_socio_1 = $_SESSION['id_socio'] ?? null;
    $id_socio_2 = $data['id_socio_2'] ?? null;

    if (!$id_socio_1 || !$id_socio_2 || !$slug) {
        throw new Exception('Datos incompletos');
    }

    // Buscar torneo
    $stmt_torneo = $pdo->prepare("SELECT * FROM torneos WHERE slug = ? AND estado = 'abierto'");
    $stmt_torneo->execute([$slug]);
    $torneo = $stmt_torneo->fetch();
    if (!$torneo) throw new Exception('Torneo no encontrado o cerrado');

    // Verificar que no estén ya inscritos
    $stmt_check = $pdo->prepare("
        SELECT 1 FROM parejas_torneo 
        WHERE id_torneo = ? AND (id_socio_1 = ? OR id_socio_2 = ? OR id_socio_1 = ? OR id_socio_2 = ?)
    ");
    $stmt_check->execute([$torneo['id_torneo'], $id_socio_1, $id_socio_1, $id_socio_2, $id_socio_2]);
    if ($stmt_check->fetch()) {
        throw new Exception('Uno de los jugadores ya está inscrito');
    }

    // Contar inscripciones actuales
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parejas_torneo WHERE id_torneo = ?");
    $stmt_count->execute([$torneo['id_torneo']]);
    $inscritos = $stmt_count->fetchColumn();
    if ($inscritos >= $torneo['num_parejas_max']) {
        throw new Exception('Cupo lleno');
    }

    // Inscribir pareja
    $pdo->prepare("
        INSERT INTO parejas_torneo (id_torneo, id_socio_1, id_socio_2, nombre_pareja)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $torneo['id_torneo'],
        $id_socio_1,
        $id_socio_2,
        $data['nombre_pareja'] ?? null
    ]);

    echo json_encode(['success' => true, 'message' => '✅ ¡Inscripción confirmada!']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>