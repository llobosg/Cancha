<?php
// includes/config.php
header("Access-Control-Allow-Origin: https://canchasport.com");

// === CARGAR VARIABLES DE ENTORNO ===
function loadEnvVars() {
    // Si estamos en CLI (terminal), cargar .env manualmente
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

    // Prioridad: $_ENV > getenv() > $_SERVER
    return [
        'BREVO_API_KEY' => $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?: ($_SERVER['BREVO_API_KEY'] ?? ''),
        'MYSQLHOST'      => $_ENV['MYSQLHOST']      ?? getenv('MYSQLHOST')      ?: ($_SERVER['MYSQLHOST']      ?? '127.0.0.1'),
        'MYSQLUSER'      => $_ENV['MYSQLUSER']      ?? getenv('MYSQLUSER')      ?: ($_SERVER['MYSQLUSER']      ?? 'root'),
        'MYSQLPASSWORD'  => $_ENV['MYSQLPASSWORD']  ?? getenv('MYSQLPASSWORD')  ?: ($_SERVER['MYSQLPASSWORD']  ?? ''),
        'MYSQLDATABASE'  => $_ENV['MYSQLDATABASE']  ?? getenv('MYSQLDATABASE')  ?: ($_SERVER['MYSQLDATABASE']  ?? 'cancha')
    ];
}

$env = loadEnvVars();

// Validación crítica
if (empty($env['MYSQLHOST']) || empty($env['MYSQLUSER']) || empty($env['MYSQLDATABASE'])) {
    error_log("❌ Faltan variables de entorno: MYSQLHOST, MYSQLUSER, MYSQLDATABASE");
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    die("Error de configuración: base de datos no disponible\n");
}

// Configurar Brevo
define('BREVO_API_KEY', $env['BREVO_API_KEY']);

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
    error_log("❌ Error de conexión a DB: " . $e->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    die("No se pudo conectar a la base de datos\n");
}
?>