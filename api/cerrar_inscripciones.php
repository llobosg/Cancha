<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $id_torneo = (int)($_POST['id_torneo'] ?? 0);
    if (!$id_torneo) throw new Exception('ID de torneo requerido');

    // Obtener parejas
    $stmt_parejas = $pdo->prepare("SELECT id_pareja FROM parejas_torneo WHERE id_torneo = ?");
    $stmt_parejas->execute([$id_torneo]);
    $parejas = $stmt_parejas->fetchAll(PDO::FETCH_COLUMN);

    if (count($parejas) < 2) throw new Exception('Se necesitan al menos 2 parejas');

    // Generar fixture: todos contra todos
    $pdo->beginTransaction();
    for ($i = 0; $i < count($parejas); $i++) {
        for ($j = $i + 1; $j < count($parejas); $j++) {
            $pdo->prepare("
                INSERT INTO partidos_torneo (id_torneo, id_pareja_1, id_pareja_2)
                VALUES (?, ?, ?)
            ")->execute([$id_torneo, $parejas[$i], $parejas[$j]]);
        }
    }

    // Cerrar inscripciones
    $pdo->prepare("UPDATE torneos SET estado = 'cerrado' WHERE id_torneo = ?")->execute([$id_torneo]);
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>