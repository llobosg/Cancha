<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $action = $_POST['action'] ?? '';
    
    // Validar acción primero
    if (!in_array($action, ['insert', 'update', 'delete'])) {
        throw new Exception('Acción no válida');
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $tipoevento = $_POST['tipoevento'] ?? '';
            $players = $_POST['players'] ?? '';
            
            if (empty($tipoevento) || empty($players)) {
                throw new Exception('Todos los campos son requeridos');
            }
            
            // Eliminar validación numérica ya que players es VARCHAR
            // Solo aseguramos que no esté vacío (ya validado arriba)
            
            if ($action === 'insert') {
                $stmt = $pdo->prepare("INSERT INTO tipoeventos (tipoevento, players) VALUES (?, ?)");
                $stmt->execute([$tipoevento, $players]);
            } else {
                $id_tipoevento = $_POST['id_tipoevento'] ?? null;
                if (!$id_tipoevento) {
                    throw new Exception('ID de tipoevento requerido');
                }
                $stmt = $pdo->prepare("UPDATE tipoeventos SET tipoevento = ?, players = ? WHERE id_tipoevento = ?");
                $stmt->execute([$tipoevento, $players, $id_tipoevento]);
            }
            break;
            
        case 'delete':
            $id_tipoevento = $_POST['id_tipoevento'] ?? null;
            if (!$id_tipoevento) {
                throw new Exception('ID de tipoevento requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM tipoeventos WHERE id_tipoevento = ?");
            $stmt->execute([$id_tipoevento]);
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>