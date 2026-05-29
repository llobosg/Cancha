<?php
// includes/config.php

session_start();

// 1. Configuración de la BD
$host = 'tu_host';
$db   = 'tu_db';
$user = 'tu_user';
$pass = 'tu_pass';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 2. Aquí es donde SE CREA $pdo
    $pdo = new PDO($dsn, $user, $pass, $options);

    // ✅ 3. PEGA LA LÍNEA AQUÍ, justo después de crear la conexión
    $pdo->exec("SET time_zone = '-03:00'");

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
// 1. Manejo de sesión CENTRALIZADO Y SEGURO
// Solo iniciar sesión si NO está activa. Esto evita warnings y conflictos.
if (session_status() === PHP_SESSION_NONE) {
    // Opcional: Si necesitas un nombre específico de sesión, hazlo AQUÍ, antes de start
    // session_name('CANCHASPORT_SESSION'); 
    
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
    if (getenv('MYSQLHOST') || getenv('RAILWAY_ENVIRONMENT')) {
        return [
            'host' => getenv('MYSQLHOST') ?: getenv('RAILWAY_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('MYSQLPORT') ?: getenv('RAILWAY_MYSQL_PORT') ?: '3306',
            'dbname' => getenv('MYSQLDATABASE') ?: getenv('RAILWAY_MYSQL_DATABASE') ?: 'canchasport',
            'user' => getenv('MYSQLUSER') ?: getenv('RAILWAY_MYSQL_USER') ?: 'root',
            'pass' => getenv('MYSQLPASSWORD') ?: getenv('RAILWAY_MYSQL_PASSWORD') ?: ''
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
    
    return [
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => 'canchasport',
        'user' => 'root',
        'pass' => ''
    ];
}

$db = getDbCredentials();
error_log("[CONFIG] DB Credentials: host={$db['host']}, port={$db['port']}, db={$db['dbname']}");

$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    error_log("[CONFIG] ✅ Conexión BD exitosa");
} catch (PDOException $e) {
    error_log("[CONFIG] ❌ Error BD: " . $e->getMessage());
    die('Error de conexión.');
}

define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?? '');

if (!function_exists('esAdmin')) {
    function esAdmin() { return (isset($_SESSION['recinto_rol']) && $_SESSION['recinto_rol'] === 'admin'); }
}
if (!function_exists('estaAutenticado')) {
    function estaAutenticado() { return isset($_SESSION['id_recinto']); }
}
?>