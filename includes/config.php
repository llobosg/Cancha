<?php
// includes/config.php
// Configuración centralizada - Compatible con Railway + Local

// 1. Manejo de sesión CENTRALIZADO (UNA SOLA VEZ)
if (session_status() === PHP_SESSION_NONE) {
    session_name('CANCHASPORT_SESSION');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Parseo ROBUSTO de DATABASE_URL para Railway
function parseRailwayDB() {
    $dbUrl = getenv('DATABASE_URL');
    
    if (!$dbUrl) {
        // Fallback para desarrollo local
        return [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'dbname' => getenv('DB_NAME') ?: 'canchasport',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASS') ?: ''
        ];
    }
    
    // Parsear URL estilo: mysql://user:pass@host:port/db?options
    $parsed = parse_url($dbUrl);
    
    // Railway a veces usa 'postgresql' o 'mysql' como esquema
    $scheme = $parsed['scheme'] ?? 'mysql';
    
    return [
        'host' => $parsed['host'] ?? 'localhost',
        'port' => $parsed['port'] ?? ($scheme === 'postgresql' ? '5432' : '3306'),
        'dbname' => ltrim($parsed['path'] ?? '', '/'),
        'user' => $parsed['user'] ?? '',
        'pass' => $parsed['pass'] ?? ''
    ];
}

// 3. Obtener credenciales
$db = parseRailwayDB();

// 4. Construir DSN para PDO (FORZAR TCP para evitar socket local)
$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";

// 5. Opciones PDO robustas
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false, // Importante en entornos serverless como Railway
    PDO::MYSQL_ATTR_SSL_CA => getenv('MYSQL_ATTR_SSL_CA') ?? null // SSL si Railway lo requiere
];

// 6. Conexión con manejo de errores amigable
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    // error_log("[CONFIG] ✅ Conexión BD exitosa: {$db['host']}:{$db['port']}");
} catch (PDOException $e) {
    // Logging detallado para debug (NO mostrar en producción)
    error_log("[CONFIG] ❌ Error BD: " . $e->getMessage());
    error_log("[CONFIG] DSN: $dsn");
    error_log("[CONFIG] Host: {$db['host']}, Port: {$db['port']}, DB: {$db['dbname']}");
    
    // Mensaje genérico para el usuario
    if (getenv('APP_ENV') === 'production') {
        die('Error de conexión. Intenta nuevamente en unos minutos.');
    } else {
        die('Error de conexión a BD: ' . htmlspecialchars($e->getMessage()));
    }
}

// 7. Definir constantes de API (si existen)
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?? '');
}

// 8. Funciones de utilidad globales
if (!function_exists('esAdmin')) {
    function esAdmin() {
        return (isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'admin');
    }
}
if (!function_exists('esAsistente')) {
    function esAsistente() {
        return (isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'asistente');
    }
}
if (!function_exists('estaAutenticado')) {
    function estaAutenticado() {
        return isset($_SESSION['id_recinto'], $_SESSION['recinto_rol']);
    }
}
?>