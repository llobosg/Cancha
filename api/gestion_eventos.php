<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

error_log("=== DEBUG GESTION EVENTOS ===");
error_log("POST  " . print_r($_POST, true));

try {
    $action_raw = $_POST['action'] ?? '';
    
    // Mostrar la acción cruda con todos sus caracteres
    error_log("Acción RAW: '$action_raw'");
    error_log("Longitud RAW: " . strlen($action_raw));
    
    // Convertir cada caracter a su código ASCII
    $ascii_codes = [];
    for ($i = 0; $i < strlen($action_raw); $i++) {
        $ascii_codes[] = ord($action_raw[$i]);
    }
    error_log("Códigos ASCII: " . implode(', ', $ascii_codes));
    
    // Limpiar la acción: eliminar espacios, retornos, tabs, etc.
    $action_clean = preg_replace('/[\x00-\x1F\x7F]/', '', trim($action_raw));
    error_log("Acción LIMPIA: '$action_clean'");
    error_log("Longitud LIMPIA: " . strlen($action_clean));
    
    // Verificar contra acciones válidas
    $valid_actions = ['insert', 'update', 'delete'];
    $is_valid = in_array($action_clean, $valid_actions);
    
    error_log("¿Es válido? " . ($is_valid ? 'SÍ' : 'NO'));
    error_log("Acciones válidas: " . implode(', ', $valid_actions));
    
    if (!$is_valid) {
        throw new Exception("Acción no válida: '$action_clean' (raw: '$action_raw')");
    }
    
    // Resto del código igual...
    switch ($action_clean) {
        case 'insert':
        case 'update':
            $tipoevento = $_POST['tipoevento'] ?? '';
            $players = $_POST['players'] ?? '';
            
            if (empty($tipoevento) || empty($players)) {
                throw new Exception('Todos los campos son requeridos');
            }
            
            if ($action_clean === 'insert') {
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