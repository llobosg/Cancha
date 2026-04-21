<?php
// includes/config.php

// 1. CONFIGURACIÓN DE SESIÓN (DEBE SER LO PRIMERO ABSOLUTO)
// Estas líneas solo funcionan si se ejecutan ANTES de session_start() en cualquier script.
// Como config.php se incluye al principio, aquí es el lugar correcto.

// Configuración de cookies seguras para producción
if (isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Parámetros avanzados de la cookie de sesión
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '', 
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Ocultar errores en pantalla (pero guardarlos en log)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 2. CABECERAS CORS (Opcional, si necesitas acceso desde otro dominio)
header("Access-Control-Allow-Origin: https://canchasport.com");
header("Access-Control-Allow-Credentials: true"); // Importante para cookies

// 3. CARGAR VARIABLES DE ENTORNO
function loadEnvVars() {
    if (php_sapi_name() === 'cli') {
        if (file_exists(__DIR__ . '/.env')) {
            $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    return [
        'BREVO_API_KEY' => $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: ($_SERVER['BREVO_API_KEY'] ?? ''),
        'MYSQLHOST'      => $_ENV['MYSQLHOST']      ?? getenv('MYSQLHOST')      ?: ($_SERVER['MYSQLHOST']      ?? '127.0.0.1'),
        'MYSQLUSER'      => $_ENV['MYSQLUSER']      ?? getenv('MYSQLUSER')      ?: ($_SERVER['MYSQLUSER']      ?? 'root'),
        'MYSQLPASSWORD'  => $_ENV['MYSQLPASSWORD']  ?? getenv('MYSQLPASSWORD')  ?: ($_SERVER['MYSQLPASSWORD']  ?? ''),
        'MYSQLDATABASE'  => $_ENV['MYSQLDATABASE']  ?? getenv('MYSQLDATABASE')  ?: ($_SERVER['MYSQLDATABASE']  ?? 'cancha')
    ];
}

$env = loadEnvVars();

// Constantes
define('VAPID_PUBLIC_KEY', 'BCypaxoOeME13Nmd9GpOXoWAHtg7PbShVfJahjEd8CwfUZ18jtTK8yN36DYsKF9iwEdnUn_liiljZ8VM_7Mpwl0');
define('VAPID_PRIVATE_KEY', 'LfRtDHMIWYhXODl9qSSVOBmKl6nN1qqU36UlOHT8ZEQ');
define('BREVO_API_KEY', $env['BREVO_API_KEY']);

// Validación DB
if (empty($env['MYSQLHOST']) || empty($env['MYSQLUSER']) || empty($env['MYSQLDATABASE'])) {
    error_log("❌ Faltan variables de entorno DB");
    if (php_sapi_name() !== 'cli') http_response_code(500);
    die("Error de configuración DB");
}

// Conexión PDO
try {
    $pdo = new PDO(
        "mysql:host={$env['MYSQLHOST']};dbname={$env['MYSQLDATABASE']};charset=utf8mb4",
        $env['MYSQLUSER'],
        $env['MYSQLPASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("❌ Error DB: " . $e->getMessage());
    if (php_sapi_name() !== 'cli') http_response_code(500);
    die("No se pudo conectar a la BD");
}
?>