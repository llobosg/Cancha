<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $id_socio_logueado = $_SESSION['id_socio'];
    $id_socio_a_editar = (int)$_POST['id_socio'];
    $modo_individual = !isset($_SESSION['club_id']);
    
    // Validar permisos
    if ($id_socio_a_editar !== $id_socio_logueado) {
        if ($modo_individual || !isset($_SESSION['club_id'])) {
            throw new Exception('No autorizado');
        }
        // Verificar que es responsable y el socio pertenece al club
        $stmt = $pdo->prepare("SELECT es_responsable FROM socios WHERE id_socio = ? AND id_club = ?");
        $stmt->execute([$id_socio_logueado, $_SESSION['club_id']]);
        $responsable = $stmt->fetch();
        if (!$responsable || $responsable['es_responsable'] != 1) {
            throw new Exception('No autorizado');
        }
        // Verificar que el socio a editar pertenece al club
        $stmt2 = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ? AND id_club = ?");
        $stmt2->execute([$id_socio_a_editar, $_SESSION['club_id']]);
        if (!$stmt2->fetch()) {
            throw new Exception('Socio no pertenece al club');
        }
    }
    
    // Actualizar datos
    $stmt = $pdo->prepare("
        UPDATE socios SET 
            nombre = ?, alias = ?, fecha_nac = ?, celular = ?, 
            direccion = ?, email = ?" . 
            (!$modo_individual ? ", rol = ?" : "") . "
        WHERE id_socio = ?
    ");
    
    $params = [
        $_POST['nombre'],
        $_POST['alias'],
        $_POST['fecha_nac'] ?: null,
        $_POST['celular'],
        $_POST['direccion'],
        $_POST['correo']
    ];
    
    if (!$modo_individual) {
        $params[] = $_POST['rol'];
    }
    $params[] = $id_socio_a_editar;
    
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>