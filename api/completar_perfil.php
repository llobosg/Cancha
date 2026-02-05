<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    $id_socio = $_POST['id_socio'] ?? null;
    
    if (!$id_socio) {
        throw new Exception('ID de socio requerido');
    }
    
    // Verificar que el socio existe
    $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ?");
    $stmt->execute([$id_socio]);
    if (!$stmt->fetch()) {
        throw new Exception('Tu perfil no se encontró en el sistema');
    }
    
    // Validar campos requeridos
    $campos_requeridos = ['alias', 'fecha_nac', 'celular', 'direccion', 'rol', 'id_puesto', 'genero', 'habilidad'];
    foreach ($campos_requeridos as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception('El campo ' . $campo . ' es requerido');
        }
    }
    
    // Actualizar perfil
    $stmt = $pdo->prepare("
        UPDATE socios 
        SET alias = ?, fecha_nac = ?, celular = ?, direccion = ?, rol = ?, 
            id_puesto = ?, genero = ?, habilidad = ?, datos_completos = 1
        WHERE id_socio = ?
    ");
    
    $result = $stmt->execute([
        $_POST['alias'],
        $_POST['fecha_nac'],
        $_POST['celular'],
        $_POST['direccion'],
        $_POST['rol'],
        $_POST['id_puesto'],
        $_POST['genero'],
        $_POST['habilidad'],
        $id_socio
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        error_log("ADVERTENCIA: Ninguna fila actualizada - socio_id=$id_socio");
        throw new Exception('No se pudo actualizar tu perfil');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>