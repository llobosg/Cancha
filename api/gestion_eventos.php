<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Logging detallado
error_log("=== DEBUG GESTION EVENTOS ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

try {
    $action = $_POST['action'] ?? '';
    error_log("Acción recibida: '$action'");
    
    // Validar acción primero
    if (!in_array($action, ['insert', 'update', 'delete'])) {
        $error_msg = "Acción no válida: '$action'";
        error_log("ERROR: " . $error_msg);
        throw new Exception($error_msg);
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $tipoevento = $_POST['tipoevento'] ?? '';
            $players = $_POST['players'] ?? '';
            
            error_log("Datos recibidos - tipoevento: '$tipoevento', players: '$players'");
            
            if (empty($tipoevento) || empty($players)) {
                $error_msg = "Campos vacíos - tipoevento: '" . (empty($tipoevento) ? 'VACIO' : 'OK') . "', players: '" . (empty($players) ? 'VACIO' : 'OK') . "'";
                error_log("ERROR: " . $error_msg);
                throw new Exception('Todos los campos son requeridos');
            }
            
            if ($action === 'insert') {
                error_log("Ejecutando INSERT");
                $stmt = $pdo->prepare("INSERT INTO tipoeventos (tipoevento, players) VALUES (?, ?)");
                $result = $stmt->execute([$tipoevento, $players]);
                error_log("INSERT resultado: " . ($result ? 'ÉXITO' : 'FALLO'));
            } else {
                $id_tipoevento = $_POST['id_tipoevento'] ?? null;
                error_log("ID tipoevento: " . ($id_tipoevento ?? 'NULL'));
                
                if (!$id_tipoevento) {
                    error_log("ERROR: ID de tipoevento requerido");
                    throw new Exception('ID de tipoevento requerido');
                }
                
                error_log("Ejecutando UPDATE");
                $stmt = $pdo->prepare("UPDATE tipoeventos SET tipoevento = ?, players = ? WHERE id_tipoevento = ?");
                $result = $stmt->execute([$tipoevento, $players, $id_tipoevento]);
                error_log("UPDATE resultado: " . ($result ? 'ÉXITO' : 'FALLO'));
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    error_log("ERROR SQL: " . implode(', ', $errorInfo));
                    throw new Exception('Error en la base de datos');
                }
            }
            break;
            
        case 'delete':
            $id_tipoevento = $_POST['id_tipoevento'] ?? null;
            error_log("DELETE - ID tipoevento: " . ($id_tipoevento ?? 'NULL'));
            
            if (!$id_tipoevento) {
                error_log("ERROR: ID de tipoevento requerido para DELETE");
                throw new Exception('ID de tipoevento requerido');
            }
            
            error_log("Ejecutando DELETE");
            $stmt = $pdo->prepare("DELETE FROM tipoeventos WHERE id_tipoevento = ?");
            $result = $stmt->execute([$id_tipoevento]);
            error_log("DELETE resultado: " . ($result ? 'ÉXITO' : 'FALLO'));
            break;
    }
    
    error_log("=== ÉXITO: Operación completada ===");
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    error_log("EXCEPCIÓN LANZADA: " . $error_msg);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}
?>