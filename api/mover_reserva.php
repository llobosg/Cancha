<?php
// api/mover_reserva.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_recinto'])) exit(json_encode(['success'=>false, 'message'=>'No autorizado']));

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id_reserva'] ?? null;

try {
    // Obtener datos actuales
    $stmt = $pdo->prepare("SELECT id_cancha, hora_inicio, fecha FROM reservas WHERE id_reserva = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch();
    if(!$actual) throw new Exception('Reserva no encontrada');

    $nuevaFecha = $data['fecha'] ?? $actual['fecha'];
    $nuevaHora = $data['hora_inicio'] ?? $actual['hora_inicio'];
    $nuevaCancha = $data['id_cancha'] ?? $actual['id_cancha'];

    // Calcular duración y nueva hora fin
    $dur = strtotime($actual['hora_fin']) - strtotime($actual['hora_inicio']);
    $nuevaFin = date('H:i:s', strtotime($nuevaHora) + $dur);

    // Validar disponibilidad básica (opcional pero recomendado)
    $check = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $check->execute([$nuevaCancha, $nuevaFecha, $nuevaHora]);
    if($check->fetchColumn() > 0) throw new Exception('Ya existe una reserva en ese horario');

    // Actualizar
    $pdo->prepare("UPDATE reservas SET id_cancha = ?, fecha = ?, hora_inicio = ?, hora_fin = ? WHERE id_reserva = ?")
        ->execute([$nuevaCancha, $nuevaFecha, $nuevaHora, $nuevaFin, $id]);

    // Notificar cambio
    ReservaMailer::enviarActualizacion($pdo, $id, [
        'fecha' => $nuevaFecha,
        'hora' => substr($nuevaHora,0,5).'-'.substr($nuevaFin,0,5),
        'cancha' => $nuevaCancha
    ]);

    echo json_encode(['success'=>true]);
} catch(Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>