<?php
// includes/config.php
// Configuración centralizada - Compatible con Railway (MYSQL*) + Local

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

// 2. Obtener credenciales de BD - Prioridad: Railway MYSQL* > DATABASE_URL > Local
function getDbCredentials() {
    // === OPCIÓN 1: Railway con variables MYSQL* (tu esquema probado) ===
    if (getenv('MYSQLHOST') || getenv('RAILWAY_ENVIRONMENT')) {
        return [
            'host' => getenv('MYSQLHOST') ?: getenv('RAILWAY_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('MYSQLPORT') ?: getenv('RAILWAY_MYSQL_PORT') ?: '3306',
            'dbname' => getenv('MYSQLDATABASE') ?: getenv('RAILWAY_MYSQL_DATABASE') ?: 'canchasport',
            'user' => getenv('MYSQLUSER') ?: getenv('RAILWAY_MYSQL_USER') ?: 'root',
            'pass' => getenv('MYSQLPASSWORD') ?: getenv('RAILWAY_MYSQL_PASSWORD') ?: ''
        ];
    }
    
    // === OPCIÓN 2: Railway con DATABASE_URL (parseo robusto) ===
    $dbUrl = getenv('DATABASE_URL');
    if ($dbUrl) {
        $parsed = parse_url($dbUrl);
        return [
            'host' => $parsed['host'] ?? '127.0.0.1',
            'port' => $parsed['port'] ?? '3306',
            'dbname' => ltrim($parsed['path'] ?? '', '/'),
            'user' => $parsed['user'] ?? 'root',
            'pass' => $parsed['pass'] ?? ''
        ];
    }
    
    // === OPCIÓN 3: Desarrollo local ===
    return [
        'host' => '127.0.0.1',  // Usar IP en lugar de localhost para forzar TCP
        'port' => '3306',
        'dbname' => 'canchasport',
        'user' => 'root',
        'pass' => ''
    ];
}

// 3. Obtener credenciales
$db = getDbCredentials();

// 4. Logging para debug (ver en Railway)
error_log("[CONFIG] DB Credentials: host={$db['host']}, port={$db['port']}, db={$db['dbname']}, user={$db['user']}");

// 5. Construir DSN para PDO (FORZAR TCP con 127.0.0.1 o host explícito)
$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";

// 6. Opciones PDO robustas
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false, // Importante en serverless
];

// 7. Conexión con manejo de errores
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    error_log("[CONFIG] ✅ Conexión BD exitosa");
} catch (PDOException $e) {
    error_log("[CONFIG] ❌ Error BD: " . $e->getMessage());
    error_log("[CONFIG] DSN: $dsn");
    error_log("[CONFIG] Credenciales usadas: " . json_encode(array_diff_key($db, ['pass' => true])));
    
    // Mensaje amigable
    if (getenv('APP_ENV') === 'production' || getenv('RAILWAY_ENVIRONMENT')) {
        die('Error de conexión. Intenta nuevamente.');
    } else {
        die('Error de conexión a BD: ' . htmlspecialchars($e->getMessage()));
    }
}

// 8. Definir constantes de API
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?? '');
}

// 9. Funciones de utilidad globales
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

if (!function_exists('verificarRolRecinto')) {
    function verificarRolRecinto($roles_permitidos = ['admin']) {
        if (!isset($_SESSION['id_recinto'])) {
            return false;
        }
        $rol_actual = $_SESSION['recinto_rol'] ?? '';
        return in_array($rol_actual, $roles_permitidos);
    }
}
?>