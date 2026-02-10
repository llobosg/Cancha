<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'test' => 'API funcionando',
    'session_id' => session_id(),
    'time' => date('Y-m-d H:i:s')
]);
?>