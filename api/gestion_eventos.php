<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

error_log("=== DEBUG GESTION EVENTOS ===");
error_log("POST  " . print_r($_POST, true));

try {
    $action = $_POST['action'] ?? '';
    $action = trim($action);
    
    error_log("Acción recibida: '$action'");
    
    // Usar comparación directa en lugar de in_array
    if ($action !== 'insert' && $action !== 'update' && $action !== 'delete') {
        throw new Exception("Acción no válida: '$action'");
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $tipoevento = $_POST['tipoevento'] ?? '';
            $players = $_POST['players'] ?? '';
            
            if (empty($tipoevento) || empty($players)) {
                throw new Exception('Todos los campos son requeridos');
            }
            
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
    $error_msg = $e->getMessage();
    error_log("EXCEPCIÓN: " . $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}
?>