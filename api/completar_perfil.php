<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos del formulario
    $id_socio = $_POST['id_socio'] ?? null;
    $alias = trim($_POST['alias'] ?? '');
    $fecha_nac = $_POST['fecha_nac'] ?? '';
    $celular = trim($_POST['celular'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol = trim($_POST['rol'] ?? '');
    $id_puesto = (int)($_POST['id_puesto'] ?? 0);
    $genero = $_POST['genero'] ?? '';
    $habilidad = $_POST['habilidad'] ?? '';
    $password = $_POST['password'] ?? ''; // ← NUEVO: Contraseña opcional
    
    // Validaciones
    if (empty($alias) || empty($fecha_nac) || empty($celular) || empty($direccion) || 
        empty($rol) || empty($id_puesto) || empty($genero) || empty($habilidad)) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    // Verificar que el socio existe
    if (!$id_socio) {
        throw new Exception('ID de socio no proporcionado');
    }
    
    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE id_socio = ?");
    $stmt_check->execute([$id_socio]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Socio no encontrado');
    }
    
    // ← NUEVO: Procesar contraseña si se proporciona
    $password_hash = null;
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Actualizar el perfil del socio
    if ($password_hash !== null) {
        $stmt = $pdo->prepare("
            UPDATE socios SET 
                alias = ?, fecha_nac = ?, celular = ?, direccion = ?, 
                rol = ?, id_puesto = ?, genero = ?, habilidad = ?, 
                password_hash = ?, datos_completos = 1
            WHERE id_socio = ?
        ");
        $stmt->execute([
            $alias, $fecha_nac, $celular, $direccion,
            $rol, $id_puesto, $genero, $habilidad,
            $password_hash, $id_socio
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE socios SET 
                alias = ?, fecha_nac = ?, celular = ?, direccion = ?, 
                rol = ?, id_puesto = ?, genero = ?, habilidad = ?, 
                datos_completos = 1
            WHERE id_socio = ?
        ");
        $stmt->execute([
            $alias, $fecha_nac, $celular, $direccion,
            $rol, $id_puesto, $genero, $habilidad,
            $id_socio
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>