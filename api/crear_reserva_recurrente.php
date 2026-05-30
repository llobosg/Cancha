<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ReservaService.php';

try {

    if (!isset($_SESSION['id_socio'])) {
        throw new Exception("No autorizado");
    }

    $data = [
        'id_socio' => $_SESSION['id_socio'],
        'id_cancha' => $_POST['id_cancha'],
        'fecha_base' => $_POST['fecha_base'],
        'hora_inicio' => $_POST['hora_inicio'],
        'duracion' => $_POST['duracion_minutos'] ?? 60,
        'tipo_patron' => $_POST['tipo_patron'] ?? 'simple',
        'fecha_desde' => $_POST['fecha_desde'] ?? $_POST['fecha_base'],
        'fecha_hasta' => $_POST['fecha_hasta'] ?? $_POST['fecha_base'],
        'monto' => $_POST['monto_total'] ?? 0
    ];

    $result = ReservaService::crearRecurrente($pdo, $data);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}