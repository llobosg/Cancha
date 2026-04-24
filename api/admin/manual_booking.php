<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../vendor/autoload.php'; // Si usas PHPMailer
// O usa mail() nativo si prefieres

$data = json_decode(file_get_contents('php://input'), true);
if(!$data['id_cancha'] || !$data['fecha'] || !$data['hora_inicio']) exit(json_encode(['success'=>false, 'message'=>'Datos incompletos']));

// Calcular hora fin (por defecto 60min)
$h = intval(substr($data['hora_inicio'],0,2))*60 + intval(substr($data['hora_inicio'],3,2));
$fin = date('H:i:s', mktime(0, $h+60, 0, 1, 1, 2000));

// Crear o enlazar socio
$id_socio = $data['id_socio'];
$link_registro = null;
if(!$id_socio) {
  $token = bin2hex(random_bytes(16));
  $stmt = $pdo->prepare("INSERT INTO socios (nombre, email, celular, tipo_socio, token_registro) VALUES (?,?,?,?, 'individual')");
  $stmt->execute([$data['nombre_cliente'], $data['email_cliente'], $data['celular_cliente'], $token]);
  $id_socio = $pdo->lastInsertId();
  $link_registro = "https://tucanchasport.com/registrar.php?token=$token";
}

// Insertar reserva
$stmt = $pdo->prepare("INSERT INTO reservas (id_cancha, id_socio, fecha, hora_inicio, hora_fin, estado, estado_pago, nombre_cliente, telefono_cliente, link_registro) 
                       VALUES (?, ?, ?, ?, ?, 'confirmada', 'pendiente', ?, ?, ?)");
$stmt->execute([$data['id_cancha'], $id_socio, $data['fecha'], $data['hora_inicio'], $fin, $data['nombre_cliente'], $data['celular_cliente'], $link_registro]);

// Enviar correo (Ejemplo con mail() nativo)
// Enviar correo de confirmación
require_once __DIR__ . '/../../includes/reserva_mailer.php';
ReservaMailer::enviarConfirmacion($pdo, $id_reserva);

echo json_encode(['success'=>true, 'id_reserva'=>$id_reserva, 'message'=>'Reserva creada y correo enviado']);
?>