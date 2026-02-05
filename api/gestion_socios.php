<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $action = $_POST['action'] ?? '';
    
    if ($action !== 'insert' && $action !== 'update' && $action !== 'delete') {
        throw new Exception('Acción no válida');
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $alias = $_POST['alias'] ?? '';
            $email = $_POST['email'] ?? '';
            $celular = $_POST['celular'] ?? '';
            $genero = $_POST['genero'] ?? '';
            $es_responsable = $_POST['es_responsable'] ?? '0';
            
            if (empty($alias) || empty($email)) {
                throw new Exception('Alias y email son requeridos');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            if ($action === 'insert') {
                $stmt = $pdo->prepare("INSERT INTO socios (alias, email, celular, genero, es_responsable) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$alias, $email, $celular, $genero, $es_responsable]);
            } else {
                $id_socio = $_POST['id_socio'] ?? null;
                if (!$id_socio) {
                    throw new Exception('ID de socio requerido');
                }
                $stmt = $pdo->prepare("UPDATE socios SET alias = ?, email = ?, celular = ?, genero = ?, es_responsable = ? WHERE id_socio = ?");
                $stmt->execute([$alias, $email, $celular, $genero, $es_responsable, $id_socio]);
            }
            break;
            
        case 'delete':
            $id_socio = $_POST['id_socio'] ?? null;
            if (!$id_socio) {
                throw new Exception('ID de socio requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM socios WHERE id_socio = ?");
            $stmt->execute([$id_socio]);
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>