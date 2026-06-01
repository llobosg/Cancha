<?php
// includes/config.php
// Configuración centralizada - Compatible con Railway + Local

// 1. Zona Horaria Global (Antes de cualquier lógica de fecha)
date_default_timezone_set('America/Santiago');

// 2. Manejo de Sesión Seguro (Solo si no está activa)
if (session_status() === PHP_SESSION_NONE) {
    // session_name('CANCHASPORT_SESSION'); // Descomenta si necesitas nombre específico
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

// 3. Función para obtener credenciales de BD
function getDbCredentials() {
    // Prioridad: Variables de entorno de Railway (MYSQL*) > DATABASE_URL > Local
    if (getenv('MYSQLHOST') || getenv('RAILWAY_ENVIRONMENT')) {
        return [
            'host' => getenv('MYSQLHOST') ?: '127.0.0.1',
            'port' => getenv('MYSQLPORT') ?: '3306',
            'dbname' => getenv('MYSQLDATABASE') ?: 'canchasport',
            'user' => getenv('MYSQLUSER') ?: 'root',
            'pass' => getenv('MYSQLPASSWORD') ?: ''
        ];
    }
    
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
    
    // Default Local
    return [
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => 'canchasport',
        'user' => 'root',
        'pass' => ''
    ];
}

// 4. Obtener credenciales y construir DSN
$db = getDbCredentials();
$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// 5. Conexión PDO y Configuración de Zona Horaria MySQL
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    
    // ✅ CRÍTICO: Setear zona horaria en MySQL inmediatamente después de conectar
    // Esto evita el error "exec() on null" porque $pdo ya existe aquí
    $pdo->exec("SET time_zone = '-03:00'");
    
    // Log opcional para debug en Railway
    // error_log("[CONFIG] ✅ BD Conectada y TZ seteada a -03:00");
    
} catch (PDOException $e) {
    error_log("[CONFIG] ❌ Error BD: " . $e->getMessage());
    // En producción, no muestres el error real al usuario
    die('Error de conexión a la base de datos.');
}

// 6. Constantes y Funciones de Utilidad
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?? '');
}

if (!function_exists('esAdmin')) {
    function esAdmin() {
        return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'admin';
    }
}

if (!function_exists('esAsistente')) {
    function esAsistente() {
        return isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'asistente';
    }
}

if (!function_exists('estaAutenticado')) {
    function estaAutenticado() {
        return isset($_SESSION['id_recinto']);
    }
}
?>