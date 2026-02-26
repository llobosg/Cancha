<?php
// api/cron/bienvenida.php
if (!isset($_GET['token']) || $_GET['token'] !== getenv('CRON_TOKEN')) {
    http_response_code(403);
    exit('Acceso denegado');
}

require_once __DIR__ . '/../../cron/bienvenida_club.php';
?>