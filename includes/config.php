<?php
// includes/config.php
// Configuración centralizada de la aplicación

// 1. Definir constantes de entorno (si no existen)
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_HOST) : 'localhost');
    define('DB_NAME', getenv('DATABASE_URL') ? ltrim(parse_url(getenv('DATABASE_URL'), PHP_URL_PATH), '/') : 'canchasport');
    define('DB_USER', getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_USER) : 'root');
    define('DB_PASS', getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_PASS) : '');
    define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?? '');
}

// 2. Manejo de sesión CENTRALIZADO (UNA SOLA VEZ en toda la app)
if (session_status() === PHP_SESSION_NONE) {
    session_name('CANCHASPORT_SESSION');
    
    // Configuración segura de cookies (ajustar para producción)
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path' => '/',
        'domain' => '', // vacío = dominio actual
        'secure' => isset($_SERVER['HTTPS']), // solo HTTPS en producción
        'httponly' => true, // no accesible por JS
        'samesite' => 'Lax' // protección CSRF básica
    ]);
    
    session_start();
    
    // Logging opcional para debug (desactivar en producción)
    // error_log("[CONFIG] Sesión iniciada. ID: " . session_id());
}

// 3. Conexión a BD (PDO)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("[CONFIG] Error de conexión a BD: " . $e->getMessage());
    // No mostrar error detallado en producción
    die('Error de conexión. Contacta al administrador.');
}

// 4. Funciones de utilidad globales
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