<?php
// api/reserva_unica.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/bitacora.php')) require_once __DIR__ . '/../includes/bitacora.php';

// Recibir datos (puede ser POST form o JSON)
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$id_recinto_admin = $_SESSION['id_recinto'] ?? null;
if (!$id_recinto_admin && !isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    $id_cancha = (int)($input['id_cancha'] ?? 0);
    $fecha = $input['fecha'] ?? '';
    $hora_inicio = $input['hora_inicio'] ?? '';
    $hora_fin = $input['hora_fin'] ?? '';
    $id_socio = $input['id_socio'] ?? null;
    $monto_total = floatval($input['monto_total'] ?? 0);
    $duracion = intval($input['duracion_bloque'] ?? 60);

    if (!$id_cancha || !$fecha || !$hora_inicio) {
        throw new Exception("Datos incompletos");
    }

    // Si no viene hora_fin, calcularla
    if (!$hora_fin) {
        $h_ini_parts = explode(':', $hora_inicio);
        $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
        $minutos_fin = $minutos_ini + $duracion;
        $hora_fin = sprintf("%02d:%02d", floor($minutos_fin / 60), $minutos_fin % 60);
    }

    // Verificar disponibilidad
    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $stmt_chk->execute([$id_cancha, $fecha, $hora_inicio]);
    if ($stmt_chk->fetchColumn() > 0) {
        throw new Exception("La cancha ya está reservada en ese horario");
    }

    // Obtener datos del socio si existe
    $nombre_cliente = ''; $email_cliente = ''; $telefono_cliente = '';
    if ($id_socio) {
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio]);
        $s = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nombre_cliente = $s['nombre']; $email_cliente = $s['email']; $telefono_cliente = $s['celular'];
        }
    }

    // Insertar Reserva
    $stmt_ins = $pdo->prepare("
        INSERT INTO reservas (
            id_cancha, id_socio, nombre_cliente, email_cliente, telefono_cliente, 
            fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, 
            estado_pago, estado, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 4, 'pendiente', 'confirmada', NOW())
    ");
    
    $stmt_ins->execute([
        $id_cancha, $id_socio, $nombre_cliente, $email_cliente, $telefono_cliente,
        $fecha, $hora_inicio, $hora_fin, $monto_total
    ]);
    
    $id_res = $pdo->lastInsertId();

    // Bitácora
    if (function_exists('registrarLogReserva')) {
        registrarLogReserva($pdo, $id_res, 'creada', "Reserva manual creada por Admin", $_SESSION['recinto_usuario'] ?? 'Admin', null, $monto_total);
    }

    echo json_encode(['success' => true, 'id_reserva' => $id_res]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>