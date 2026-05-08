<?php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) ob_end_clean();

try {
    define('APP_ENTRY_POINT', true);
    
    // 🔍 RESOLUCIÓN DE RUTAS SEGURA PARA RAILWAY
    $projectRoot = dirname(dirname(__DIR__)); // public/api/ → /app/
    $configPath = $projectRoot . '/includes/config.php';
    $autoloadPath = $projectRoot . '/vendor/autoload.php';

    error_log("🔍 [Convenios] Project Root: $projectRoot");
    error_log("📦 vendor/autoload.php: " . (file_exists($autoloadPath) ? '✅' : '❌ Falta'));
    error_log("⚙️ includes/config.php: " . (file_exists($configPath) ? '✅' : '❌ Falta'));

    if (!file_exists($autoloadPath)) {
        throw new Exception("vendor/autoload.php no encontrado. Verifica composer install.");
    }
    if (!file_exists($configPath)) {
        throw new Exception("includes/config.php no encontrado en {$projectRoot}. Verifica que fue commitado y push a GitHub.");
    }

    require_once $autoloadPath;
    require_once $configPath;

    // 🔐 CONFIGURACIÓN DE SESIÓN COMPATIBLE CON RAILWAY/PHP-CLI
    ini_set('session.use_strict_mode', '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    ini_set('session.use_cookies', '1');
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 📝 LOG DE DIAGNÓSTICO (solo visible en Railway logs, nunca en el cliente)
    error_log("🔐 [Convenios API] Session ID: " . session_id());
    error_log("🔐 [Convenios API] Cookies recibidas: " . print_r($_COOKIE, true));
    error_log("🔐 [Convenios API] $_SESSION actual: " . print_r($_SESSION, true));
    
       // 🔐 VALIDACIÓN DE SESIÓN (Alineada a login_unificado.php)
    if (!isset($_SESSION['id_admin']) || !isset($_SESSION['id_recinto'])) {
        error_log("❌ [Convenios] Sesión incompleta. Keys detectadas: " . implode(', ', array_keys($_SESSION)));
        throw new Exception('No autorizado. Sesión de administrador no válida.', 401);
    }
    
    $id_recinto = $_SESSION['id_recinto'];
    $user_id = $_SESSION['id_admin']; // ✅ Usamos la clave real del login

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
    error_log("❌ [API Convenios] Fatal: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}