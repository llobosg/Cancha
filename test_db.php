<?php
require_once __DIR__ . '/includes/config.php';
echo "✅ Conexión exitosa!<br>";
echo "Versión MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
?>