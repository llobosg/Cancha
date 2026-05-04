<?php
// api/guardar_convenio.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id_recinto'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? ''; // 'create' | 'update' | 'delete'

try {
    switch ($action) {
        case 'create':
            $nombre = trim($input['nombre_empresa'] ?? '');
            $contacto = trim($input['contacto_nombre'] ?? '');
            $email = trim($input['contacto_email'] ?? '');
            $telefono = trim($input['contacto_telefono'] ?? '');
            $porc_dscto = (float)($input['porc_dscto'] ?? 0);
            $vigente_desde = $input['vigente_desde'] ?? null;
            $vigente_hasta = $input['vigente_hasta'] ?? null;
            
            if (!$nombre || $porc_dscto < 0 || $porc_dscto > 100) {
                throw new Exception('Nombre de empresa y descuento válido (0-100%) son requeridos');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO convenios (
                    id_recinto, nombre_empresa, contacto_nombre, contacto_email, 
                    contacto_telefono, porc_dscto, vigente_desde, vigente_hasta, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            $stmt->execute([
                $id_recinto, $nombre, $contacto, $email, $telefono, 
                $porc_dscto, $vigente_desde ?: null, $vigente_hasta ?: null
            ]);
            
            echo json_encode(['success' => true, 'id_convenio' => $pdo->lastInsertId(), 'message' => 'Convenio creado']);
            break;
            
        case 'update':
            $id_convenio = (int)($input['id_convenio'] ?? 0);
            
            // Validar que el convenio pertenece a este recinto
            $stmt_check = $pdo->prepare("SELECT 1 FROM convenios WHERE id_convenio = ? AND id_recinto = ?");
            $stmt_check->execute([$id_convenio, $id_recinto]);
            if (!$stmt_check->fetch()) {
                throw new Exception('Convenio no encontrado o no pertenece a este recinto');
            }
            
            $nombre = trim($input['nombre_empresa'] ?? '');
            $contacto = trim($input['contacto_nombre'] ?? '');
            $email = trim($input['contacto_email'] ?? '');
            $telefono = trim($input['contacto_telefono'] ?? '');
            $porc_dscto = (float)($input['porc_dscto'] ?? 0);
            $vigente_desde = $input['vigente_desde'] ?? null;
            $vigente_hasta = $input['vigente_hasta'] ?? null;
            $estado = $input['estado'] ?? 'activo';
            
            if (!$nombre || $porc_dscto < 0 || $porc_dscto > 100) {
                throw new Exception('Nombre y descuento válido son requeridos');
            }
            
            $stmt = $pdo->prepare("
                UPDATE convenios SET
                    nombre_empresa = ?, contacto_nombre = ?, contacto_email = ?,
                    contacto_telefono = ?, porc_dscto = ?, vigente_desde = ?,
                    vigente_hasta = ?, estado = ?, updated_at = NOW()
                WHERE id_convenio = ? AND id_recinto = ?
            ");
            $stmt->execute([
                $nombre, $contacto, $email, $telefono, $porc_dscto,
                $vigente_desde ?: null, $vigente_hasta ?: null, $estado,
                $id_convenio, $id_recinto
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Convenio actualizado']);
            break;
            
        case 'delete':
            $id_convenio = (int)($input['id_convenio'] ?? 0);
            
            // Validar pertenencia
            $stmt_check = $pdo->prepare("SELECT 1 FROM convenios WHERE id_convenio = ? AND id_recinto = ?");
            $stmt_check->execute([$id_convenio, $id_recinto]);
            if (!$stmt_check->fetch()) {
                throw new Exception('Convenio no válido');
            }
            
            // Soft delete: marcar como inactivo en lugar de borrar
            $stmt = $pdo->prepare("UPDATE convenios SET estado = 'inactivo', updated_at = NOW() WHERE id_convenio = ? AND id_recinto = ?");
            $stmt->execute([$id_convenio, $id_recinto]);
            
            echo json_encode(['success' => true, 'message' => 'Convenio desactivado']);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    error_log("❌ guardar_convenio error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>