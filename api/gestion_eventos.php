<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $action = $_POST['action'] ?? '';
    $id_evento = $_POST['id_evento'] ?? null;
    $tipoevento = $_POST['tipoevento'] ?? '';
    $players = $_POST['players'] ?? ''; 
    if (empty($tipoevento) || empty($players)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    if (!is_numeric($players) || $players < 1) {
        throw new Exception('Jugadores debe ser un número positivo');
    }
    switch ($action) {
        case 'insert':
            $stmt = $pdo->prepare("INSERT INTO tipoeventos (tipoevento, players) VALUES (?, ?)");
            $stmt->execute([$tipoevento, $players]);
            break;
            
        case 'update':
            if (!$id_evento) {
                throw new Exception('ID de evento requerido');
            }
            $stmt = $pdo->prepare("UPDATE tipoeventos SET tipoevento = ?, players = ? WHERE id_evento = ?");
            $stmt->execute([$tipoevento, $players, $id_evento]);
            break;
            
        case 'delete':
            if (!$id_evento) {
                throw new Exception('ID de evento requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM tipoeventos WHERE id_evento = ?");
            $stmt->execute([$id_evento]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    echo json_encode(['success' => true]);  
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>