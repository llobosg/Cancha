<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $action = $_POST['action'] ?? '';
    $id_puesto = $_POST['id_puesto'] ?? null;
    $puesto = $_POST['puesto'] ?? '';
    
    if (empty($puesto) && $action !== 'delete') {
        throw new Exception('El nombre del puesto es requerido');
    }
    
    switch ($action) {
        case 'insert':
            $stmt = $pdo->prepare("INSERT INTO puestos (puesto) VALUES (?)");
            $stmt->execute([$puesto]);
            break;
            
        case 'update':
            if (!$id_puesto) {
                throw new Exception('ID de puesto requerido');
            }
            $stmt = $pdo->prepare("UPDATE puestos SET puesto = ? WHERE id_puesto = ?");
            $stmt->execute([$puesto, $id_puesto]);
            break;
            
        case 'delete':
            if (!$id_puesto) {
                throw new Exception('ID de puesto requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM puestos WHERE id_puesto = ?");
            $stmt->execute([$id_puesto]);
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