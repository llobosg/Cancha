<?php
// includes/config.php
header("Access-Control-Allow-Origin: https://canchasport.com");

// ✅ USAR VARIABLES DE ENTORNO
define('BREVO_API_KEY', $_ENV['BREVO_API_KEY'] ?? '');

// Usar getenv() para mayor compatibilidad
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');

// Si no están en getenv, intentar con $_SERVER (Railway las pone ahí)
if ($host === false) $host = $_SERVER['MYSQLHOST'] ?? null;
if ($user === false) $user = $_SERVER['MYSQLUSER'] ?? null;
if ($pass === false) $pass = $_SERVER['MYSQLPASSWORD'] ?? null;
if ($db   === false) $db   = $_SERVER['MYSQLDATABASE'] ?? null;

// Validación crítica
if (!$host || !$user || !$db) {
    error_log("❌ Faltan variables de entorno: MYSQLHOST, MYSQLUSER, MYSQLDATABASE");
    http_response_code(500);
    die("Error de configuración: base de datos no disponible");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("❌ Error de conexión a DB: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar a la base de datos");
}
?>