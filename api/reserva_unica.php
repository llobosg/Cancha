<?php
// api/reserva_unica.php
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';
if (file_exists(__DIR__ . '/../includes/bitacora.php')) require_once __DIR__ . '/../includes/bitacora.php';
if (file_exists(__DIR__ . '/../includes/brevo_mailer.php')) require_once __DIR__ . '/../includes/brevo_mailer.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id_cancha']) || empty($input['fecha'])) {
        throw new Exception("Datos incompletos");
    }

    // === 1. EXTRAER DATOS PRIMERO ===
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

    // === 2. VALIDAR FECHA/HORA VENCIDA (AHORA SÍ FUNCIONA) ===
    $dt_reserva = new DateTime("$fecha $hora_inicio");
    $dt_ahora = new DateTime();

    if ($dt_reserva <= $dt_ahora) {
        throw new Exception("❌ No se pueden realizar reservas en horarios vencidos.");
    }

    // === 3. OBTENER CAPACIDAD DE LA CANCHA ===
    $stmt_cap = $pdo->prepare("SELECT capacidad_jugadores FROM canchas WHERE id_cancha = ?");
    $stmt_cap->execute([$id_cancha]);
    $cap_data = $stmt_cap->fetch(PDO::FETCH_ASSOC);
    
    $jugadores_esperados = 20; 
    if ($cap_data && !empty($cap_data['capacidad_jugadores'])) {
        $jugadores_esperados = (int)$cap_data['capacidad_jugadores'];
    }

    // Validar disponibilidad
    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $stmt_chk->execute([$id_cancha, $fecha, $hora_inicio]);
    if ($stmt_chk->fetchColumn() > 0) {
        throw new Exception("La cancha ya está reservada en ese horario");
    }

    // === LÓGICA DE RESPONSABLE Y DATOS SOCIO ===
    $id_club_final = null;
    $nombre_cliente = ''; $email_cliente = ''; $telefono_cliente = '';
    $tipo_reserva_texto = "Personal"; 

    if ($id_socio) {
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio]);
        $s = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nombre_cliente = $s['nombre']; 
            $email_cliente = $s['email']; 
            $telefono_cliente = $s['celular'];
        }

        if (!empty($input['id_club_reserva']) && ($input['tipo_reserva'] ?? '') === 'club') {
            $id_club_intentado = (int)$input['id_club_reserva'];
            $stmt_rol = $pdo->prepare("SELECT COUNT(*) FROM socio_club WHERE id_socio = ? AND id_club = ? AND es_responsable = 1");
            $stmt_rol->execute([$id_socio, $id_club_intentado]);
            
            if ($stmt_rol->fetchColumn() > 0) {
                $id_club_final = $id_club_intentado;
                $tipo_reserva_texto = "Institucional (Club)";
                
                $stmt_c = $pdo->prepare("SELECT nombre FROM clubs WHERE id_club = ?");
                $stmt_c->execute([$id_club_final]);
                $nombre_club = $stmt_c->fetchColumn();
            }
        }
    }

    // === INSERTAR RESERVA ===
    $sql = "INSERT INTO reservas (
                id_cancha, id_club, id_socio, nombre_cliente, email_cliente, telefono_cliente, 
                fecha, hora_inicio, hora_fin, monto_total, jugadores_esperados, estado_pago, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada')";
            
    $stmt_ins = $pdo->prepare($sql);
    $stmt_ins->execute([
        $id_cancha, $id_club_final, $id_socio, $nombre_cliente, $email_cliente, $telefono_cliente,
        $fecha, $hora_inicio, $hora_fin, $monto_total, $jugadores_esperados
    ]);
    
    $id_res = $pdo->lastInsertId();
    
    // === BITÁCORA ===
    if (function_exists('registrarLogReserva')) {
        $descripcion = "Reserva manual creada desde Dashboard Socio.\n";
        $descripcion .= "📅 Fecha: $fecha | ⏰ Hora: $hora_inicio - $hora_fin\n";
        $descripcion .= "🏟️ Tipo: $tipo_reserva_texto";
        if ($id_club_final) $descripcion .= " ($nombre_club)";

        registrarLogReserva(
            $pdo, 
            $id_res, 
            'creada', 
            $descripcion, 
            $nombre_cliente, 
            null, 
            $monto_total
        );
    }

    // === CORREO ===
    if ($email_cliente && class_exists('BrevoMailer')) {
        try {
            $stmt_cancha = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
            $stmt_cancha->execute([$id_cancha]);
            $nombre_cancha = $stmt_cancha->fetchColumn() ?: 'Cancha';

            $html_body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                <div style='background:linear-gradient(135deg,#CE93D8,#AB47BC);color:white;padding:15px;border-radius:8px 8px 0 0;text-align:center;'>
                    <h2 style='margin:0;'>✅ Reserva Confirmada</h2>
                </div>
                <div style='background:white;padding:20px;border-radius:0 0 8px 8px;'>
                    <p>Hola <strong>$nombre_cliente</strong>,</p>
                    <p>Tu reserva ha sido registrada exitosamente:</p>
                    
                    <div style='background:#F3E5F5;padding:15px;border-radius:8px;margin:15px 0;'>
                        <p><strong>🏟️ Cancha:</strong> $nombre_cancha</p>
                        <p><strong>📅 Fecha:</strong> $fecha</p>
                        <p><strong>⏰ Hora:</strong> $hora_inicio - $hora_fin</p>
                        <p><strong>💰 Monto:</strong> $" . number_format($monto_total, 0, ',', '.') . "</p>
                        <p><strong>🏢 Tipo:</strong> $tipo_reserva_texto " . ($id_club_final ? "($nombre_club)" : "") . "</p>
                    </div>
                </div>
            </div>";

            $mail = new BrevoMailer();
            $mail->setTo($email_cliente, $nombre_cliente)
                ->setSubject("Confirmación de Reserva - CanchaSport")
                ->setHtmlBody($html_body)
                ->send();
        } catch (Exception $e) {
            error_log("[MAIL ERROR] " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'id_reserva' => $id_res]);

} catch (Exception $e) {
    error_log("[ERROR UNICA] " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>