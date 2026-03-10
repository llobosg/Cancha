<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
session_start();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if (!$data['id_reserva'] || !$data['rojos'] || !$data['blancos']) {
        throw new Exception('Datos incompletos');
    }
    
    $pdo->beginTransaction();
    
    // Limpiar equipos anteriores
    $stmt = $pdo->prepare("
        DELETE je FROM jugadores_equipo je
        JOIN equipos_partido ep ON je.id_equipo = ep.id_equipo
        WHERE ep.id_reserva = ?
    ");
    $stmt->execute([$data['id_reserva']]);
    
    // Crear equipos
    $stmt = $pdo->prepare("INSERT INTO equipos_partido (id_reserva, nombre_equipo) VALUES (?, ?)");
    $stmt->execute([$data['id_reserva'], 'Rojos']);
    $id_rojos = $pdo->lastInsertId();
    
    $stmt->execute([$data['id_reserva'], 'Blancos']);
    $id_blancos = $pdo->lastInsertId();
    
    // Asignar jugadores
    foreach ($data['rojos'] as $id_socio) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")
             ->execute([$id_rojos, $id_socio]);
    }
    
    foreach ($data['blancos'] as $id_socio) {
        $pdo->prepare("INSERT INTO jugadores_equipo (id_equipo, id_socio) VALUES (?, ?)")
             ->execute([$id_blancos, $id_socio]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>