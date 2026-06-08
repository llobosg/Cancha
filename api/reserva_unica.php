<?php
// api/reserva_unica.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/bitacora.php')) require_once __DIR__ . '/../includes/bitacora.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id_cancha']) || empty($input['fecha'])) {
        throw new Exception("Datos incompletos");
    }

    $id_cancha = (int)$input['id_cancha'];
    $fecha = $input['fecha'];
    $hora_inicio = $input['hora_inicio'];
    $hora_fin = $input['hora_fin'] ?? '';
    $id_socio = $input['id_socio'] ?? null;
    $monto_total = floatval($input['monto_total'] ?? 0);
    $duracion = intval($input['duracion_bloque'] ?? 60);
    
    // Calcular hora fin si no viene
    if (!$hora_fin) {
        $h_ini_parts = explode(':', $hora_inicio);
        $minutos_ini = ($h_ini_parts[0] * 60) + $h_ini_parts[1];
        $minutos_fin = $minutos_ini + $duracion;
        $hora_fin = sprintf("%02d:%02d", floor($minutos_fin / 60), $minutos_fin % 60);
    }

    // Validar disponibilidad
    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $stmt_chk->execute([$id_cancha, $fecha, $hora_inicio]);
    if ($stmt_chk->fetchColumn() > 0) {
        throw new Exception("La cancha ya está reservada en ese horario");
    }

    // === LÓGICA DE RESPONSABLE ===
    $id_club_final = null;
    $nombre_cliente = ''; $email_cliente = ''; $telefono_cliente = '';

    if ($id_socio) {
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio]);
        $s = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nombre_cliente = $s['nombre']; 
            $email_cliente = $s['email']; 
            $telefono_cliente = $s['celular'];
        }

        // Validar si es responsable
        if (!empty($input['id_club_reserva']) && ($input['tipo_reserva'] ?? '') === 'club') {
            $id_club_intentado = (int)$input['id_club_reserva'];
            $stmt_rol = $pdo->prepare("SELECT COUNT(*) FROM socio_club WHERE id_socio = ? AND id_club = ? AND es_responsable = 1");
            $stmt_rol->execute([$id_socio, $id_club_intentado]);
            
            if ($stmt_rol->fetchColumn() > 0) {
                $id_club_final = $id_club_intentado;
                error_log("[RESERVA] Socio $id_socio es RESPONSABLE del Club $id_club_final");
            } else {
                error_log("[RESERVA] Socio $id_socio NO es responsable del Club $id_club_intentado. Se guarda como individual.");
            }
        }
    }

    // === INSERTAR RESERVA ===
    // Usamos nombres de columna exactos según tu DESCRIBE
    $sql = "INSERT INTO reservas (
                id_cancha, 
                id_club, 
                id_socio, 
                nombre_cliente, 
                email_cliente, 
                telefono_cliente, 
                fecha, 
                hora_inicio, 
                hora_fin, 
                monto_total, 
                jugadores_esperados, 
                estado_pago, 
                estado, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4, 'pendiente', 'confirmada', NOW())";
            
    error_log("[SQL] Ejecutando: " . $sql);
    error_log("[PARAMS] Club: $id_club_final, Socio: $id_socio");

    $stmt_ins = $pdo->prepare($sql);
    $stmt_ins->execute([
        $id_cancha,
        $id_club_final, // NULL o ID
        $id_socio, 
        $nombre_cliente, 
        $email_cliente, 
        $telefono_cliente,
        $fecha, 
        $hora_inicio, 
        $hora_fin, 
        $monto_total
    ]);
    
    $id_res = $pdo->lastInsertId();
    
    // Bitácora
    if (function_exists('registrarLogReserva')) {
        registrarLogReserva($pdo, $id_res, 'creada', "Reserva manual desde dashboard", $_SESSION['nombre_completo'] ?? 'Socio', null, $monto_total);
    }

    echo json_encode(['success' => true, 'id_reserva' => $id_res]);

} catch (Exception $e) {
    error_log("[ERROR CRÍTICO] " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>