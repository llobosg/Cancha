<?php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // ✅ RUTAS SEGURAS (Resuelve desde la ubicación real del script)
    $basePath = dirname(__DIR__, 2); // /app/public/api/ -> /app
    
    $autoload = $basePath . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception("Composer autoload no encontrado en: {$autoload}");
    }
    require_once $autoload;
    
    $config = $basePath . '/config.php';
    if (!file_exists($config)) {
        throw new Exception("Config.php no encontrado en: {$config}");
    }
    require_once $config;

    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_recinto'])) {
        throw new Exception('No autorizado. Sesión inválida.', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.', 405);
    }

    // Extraer y sanitizar datos
    $action     = $_POST['action'] ?? '';
    $id_recinto = $_SESSION['id_recinto'];
    $nombre     = trim($_POST['nombre_empresa'] ?? '');
    $contacto   = trim($_POST['contacto_nombre'] ?? '');
    $email      = trim($_POST['contacto_email'] ?? '');
    $telefono   = trim($_POST['contacto_telefono'] ?? '');
    $dscto      = floatval($_POST['porc_dscto'] ?? 0);
    $desde      = !empty($_POST['vigente_desde']) ? $_POST['vigente_desde'] : null;
    $hasta      = !empty($_POST['vigente_hasta']) ? $_POST['vigente_hasta'] : null;
    $estado     = $_POST['estado'] ?? 'activo';

    if (empty($nombre)) {
        throw new Exception('El nombre de la empresa es obligatorio.');
    }

    if ($action === 'create') {
        $sql = "INSERT INTO convenios (id_recinto, nombre_empresa, contacto_nombre, contacto_email, contacto_telefono, porc_dscto, vigente_desde, vigente_hasta, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_recinto, $nombre, $contacto, $email, $telefono, $dscto, $desde, $hasta, $estado]);
        
    } elseif ($action === 'update') {
        $id_convenio = $_POST['id_convenio'] ?? null;
        if (!$id_convenio) throw new Exception('ID de convenio requerido para actualización.');
        
        $sql = "UPDATE convenios SET nombre_empresa=?, contacto_nombre=?, contacto_email=?, contacto_telefono=?, porc_dscto=?, vigente_desde=?, vigente_hasta=?, estado=? 
                WHERE id_convenio=? AND id_recinto=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $contacto, $email, $telefono, $dscto, $desde, $hasta, $estado, $id_convenio, $id_recinto]);
        
    } else {
        throw new Exception('Acción no válida.');
    }

    echo json_encode(['success' => true, 'message' => 'Convenio guardado correctamente.']);
    exit;

} catch (\Throwable $e) {
    error_log("❌ API convenios Fatal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}