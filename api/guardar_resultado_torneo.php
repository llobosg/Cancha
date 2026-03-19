<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $id_partido = $_POST['id_partido'] ?? null;
    $juegos1 = (int)($_POST['juegos1'] ?? 0);
    $juegos2 = (int)($_POST['juegos2'] ?? 0);

    if (!$id_partido) throw new Exception('Partido no válido');

    $pdo->beginTransaction();

    // Actualizar partido
    $pdo->prepare("
        UPDATE partidos_torneo 
        SET juegos_pareja_1 = ?, juegos_pareja_2 = ?, estado = 'finalizado'
        WHERE id_partido = ?
    ")->execute([$juegos1, $juegos2, $id_partido]);

    // Determinar ganador
    $ganador = ($juegos1 > $juegos2) ? 'pareja_1' : 'pareja_2';

    // Sumar punto a la pareja ganadora
    $stmt = $pdo->prepare("SELECT id_pareja_1, id_pareja_2 FROM partidos_torneo WHERE id_partido = ?");
    $stmt->execute([$id_partido]);
    $partido = $stmt->fetch();
    
    $id_ganador = ($ganador === 'pareja_1') ? $partido['id_pareja_1'] : $partido['id_pareja_2'];
    $pdo->prepare("UPDATE parejas_torneo SET puntos_totales = puntos_totales + 1 WHERE id_pareja = ?")
         ->execute([$id_ganador]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>