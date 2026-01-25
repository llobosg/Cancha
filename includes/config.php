<?php
// config.php - Conexión a la base de datos en Railway (texto plano en desarrollo)

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = 'metro.proxy.rlwy.net';
        $port = 42951;
        $dbname = 'railway';
        $user = 'root';
        $pass = 'BJfdOmZzlcQSgEUUOMTHvSqTSmcltSUT';

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión a DB: " . $e->getMessage());
            die("Error al conectar con la base de datos.");
        }
    }

    return $pdo;
}
?>