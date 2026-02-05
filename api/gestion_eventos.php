<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Logging detallado
error_log("=== DEBUG GESTION EVENTOS ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("POST  " . print_r($_POST, true));

try {
    $action = $_POST['action'] ?? '';
    // Limpiar espacios en blanco y caracteres invisibles
    $action = trim($action);
    
    error_log("Acción recibida (limpia): '$action'");
    error_log("Longitud de acción: " . strlen($action));
    
    // Validar acción primero
    if (!in_array($action, ['insert', 'update', 'delete'])) {
        // Mostrar todos los caracteres para debug
        $action_debug = '';
        for ($i = 0; $i < strlen($action); $i++) {
            $action_debug .= ord($action[$i]) . ' ';
        }
        error_log("Códigos ASCII de acción: $action_debug");
        
        $error_msg = "Acción no válida: '$action'";
        error_log("ERROR: " . $error_msg);
        throw new Exception($error_msg);
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
    error_log("EXCEPCIÓN LANZADA: " . $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}
?>