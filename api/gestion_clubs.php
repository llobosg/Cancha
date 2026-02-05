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
            $nombre = $_POST['nombre'] ?? '';
            $pais = $_POST['pais'] ?? '';
            $ciudad = $_POST['ciudad'] ?? '';
            $comuna = $_POST['comuna'] ?? '';
            $email = $_POST['email_responsable'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            if (empty($nombre) || empty($pais) || empty($ciudad) || empty($comuna) || empty($email)) {
                throw new Exception('Todos los campos son requeridos');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            if ($action === 'insert') {
                $stmt = $pdo->prepare("INSERT INTO clubs (nombre, pais, ciudad, comuna, email_responsable, telefono) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $pais, $ciudad, $comuna, $email, $telefono]);
            } else {
                $id_club = $_POST['id_club'] ?? null;
                if (!$id_club) {
                    throw new Exception('ID de club requerido');
                }
                $stmt = $pdo->prepare("UPDATE clubs SET nombre = ?, pais = ?, ciudad = ?, comuna = ?, email_responsable = ?, telefono = ? WHERE id_club = ?");
                $stmt->execute([$nombre, $pais, $ciudad, $comuna, $email, $telefono, $id_club]);
            }
            break;
            
        case 'delete':
            $id_club = $_POST['id_club'] ?? null;
            if (!$id_club) {
                throw new Exception('ID de club requerido');
            }
            $stmt = $pdo->prepare("DELETE FROM clubs WHERE id_club = ?");
            $stmt->execute([$id_club]);
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>