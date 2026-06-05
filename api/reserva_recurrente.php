<?php
// api/reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/bitacora.php')) require_once __DIR__ . '/../includes/bitacora.php';

$input = json_decode(file_get_contents('php://input'), true);
error_log("[Recurrente] Input recibido: " . json_encode($input));

$id_recinto_admin = $_SESSION['id_recinto'] ?? null;
if (!$id_recinto_admin && !isset($_SESSION['id_socio'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    // Validaciones
    if (empty($input['id_cancha']) || empty($input['start_date']) || empty($input['end_date'])) {
        throw new Exception("Datos incompletos");
    }

    $id_cancha = (int)$input['id_cancha'];
    $hora_inicio = $input['hora_inicio'];
    $duracion_minutos = intval($input['duracion_bloque'] ?? 60);
    $repeat_day = (int)$input['repeat_day']; 
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $monto_unitario = floatval($input['monto_total'] ?? 0);
    
    // Resolver Socio
    $id_socio_final = $input['id_socio'] ?? $_SESSION['id_socio'] ?? null;
    $nombre_cliente = ''; $email_cliente = ''; $telefono_cliente = ''; $id_club_reserva = null;

    if ($id_socio_final) {
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular, id_club FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio_final]);
        $s = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nombre_cliente = $s['nombre']; $email_cliente = $s['email']; 
            $telefono_cliente = $s['celular']; $id_club_reserva = $s['id_club'];
        }
    } elseif (!empty($input['emailNuevoSocio'])) {
        $email_nuevo = $input['emailNuevoSocio'];
        $nombre_nuevo = $input['nombreNuevoSocio'] ?? 'Nuevo Socio';
        $stmt_chk = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt_chk->execute([$email_nuevo]);
        $ex = $stmt_chk->fetch();
        if ($ex) {
            $id_socio_final = $ex['id_socio'];
            $email_cliente = $email_nuevo;
        } else {
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $stmt_ins = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_ins->execute([$email_nuevo, $nombre_nuevo, $alias, $input['telNuevoSocio'] ?? '']);
            $id_socio_final = $pdo->lastInsertId();
            $email_cliente = $email_nuevo; $nombre_cliente = $nombre_nuevo;
        }
    }

    // Generar Fechas
    $fechas_disponibles = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    $day_names = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    
    while ($current <= $end) {
        $php_day = ((int)$current->format('N') % 7); // 0=Dom, 1=Lun...
        if ($php_day === $repeat_day) {
            $fecha_str = $current->format('Y-m-d');
            // Check disponibilidad
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $stmt_chk->execute([$id_cancha, $fecha_str, $hora_inicio]);
            if ($stmt_chk->fetchColumn() == 0) {
                $fechas_disponibles[] = ['fecha' => $fecha_str, 'dia_nombre' => $day_names[$current->format('w')], 'dia_num' => $current->format('d')];
            }
        }
        $current->modify('+1 day');
    }

    if (empty($fechas_disponibles)) {
        throw new Exception("No hay fechas disponibles");
    }

    error_log("[Recurrente] Fechas a procesar: " . count($fechas_disponibles));

    // Transacción
    $pdo->beginTransaction();
    $created = 0;

    foreach ($fechas_disponibles as $item) {
        $fecha = $item['fecha'];
        
        // Calcular Hora Fin
        $h_ini_parts = explode(':', $hora_inicio);
        $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
        $minutos_fin = $minutos_ini + $duracion_minutos;
        $hora_fin_calc = sprintf("%02d:%02d", floor($minutos_fin / 60), $minutos_fin % 60);

        // Insertar
        $stmt_ins = $pdo->prepare("
            INSERT INTO reservas (id_cancha, id_club, id_socio, nombre_cliente, email_cliente, telefono_cliente, fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, estado_pago, estado, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4, 'pendiente', 'confirmada', NOW())
        ");
        $stmt_ins->execute([
            $id_cancha, $id_club_reserva, $id_socio_final, $nombre_cliente, $email_cliente, $telefono_cliente,
            $fecha, $hora_inicio, $hora_fin_calc, $monto_unitario
        ]);
        
        $created++;
        
        // Bitácora individual
        if (function_exists('registrarLogReserva')) {
            registrarLogReserva($pdo, $pdo->lastInsertId(), 'creada_recurrente', "Reserva recurrente ($fecha)", $_SESSION['recinto_usuario'] ?? 'Admin', null, $monto_unitario);
        }
    }

    $pdo->commit();
    error_log("[Recurrente] Commit exitoso. Creadas: $created");

    echo json_encode(['success' => true, 'created' => $created]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Recurrente] ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>