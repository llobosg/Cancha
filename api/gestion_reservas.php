<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    
    // 1. Verificar autenticación básica
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Sesión no iniciada', 401);
    }

    // 2. Verificar Rol (ACTUALIZADO PARA NUEVOS ROLES)
    $rol_actual = $_SESSION['recinto_rol'] ?? '';
    $roles_permitidos = ['admin', 'asistente']; // Aceptamos ambos roles

    if (!in_array($rol_actual, $roles_permitidos)) {
        error_log("❌ [API GESTIÓN RESERVAS] Acceso denegado. Rol actual: '$rol_actual'. Roles permitidos: " . implode(', ', $roles_permitidos));
        throw new Exception('Acceso no autorizado: Rol inválido', 401);
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Log de auditoría para saber qué acción se intenta
    error_log("🎯 [API GESTIÓN RESERVAS] Acción: $action | Usuario: " . ($_SESSION['recinto_usuario'] ?? 'Desconocido'));

    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
        case 'crear_manual':
            echo json_encode(crearReservaManualConLog($pdo, $_POST));
            break;

        case 'crear_manual':
            echo json_encode(crearReservaManualConSocioNuevo($pdo, $_POST));
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function procesarPagoReserva($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    $monto_total = (float)($data['monto_total'] ?? 0); // Asumimos pago total
    
    if (!$id_reserva || !$metodo_pago) {
        throw new Exception('Datos incompletos para procesar pago');
    }
    
    // Verificar que la reserva pertenece al recinto del admin
    $stmt_check = $pdo->prepare("
        SELECT r.id_reserva, r.estado_pago, r.monto_total, c.id_recinto
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt_check->execute([$id_reserva, $_SESSION['id_recinto']]);
    $reserva = $stmt_check->fetch();
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada o no pertenece a este recinto');
    }
    
    if ($reserva['estado_pago'] === 'pagado') {
        throw new Exception('Esta reserva ya está pagada');
    }
    
    // === AGREGAR EXTRAS A NOTAS (formato estructurado) ===
    $extras = floatval($data['extras'] ?? 0);
    if ($extras > 0) {
        // Obtener notas actuales
        $stmt_notas = $pdo->prepare("SELECT notas FROM reservas WHERE id_reserva = ?");
        $stmt_notas->execute([$id_reserva]);
        $notas_actuales = $stmt_notas->fetchColumn() ?? '';
        
        // Verificar si ya hay extras registrados (evitar duplicados)
        if (!preg_match('/\[EXTRAS:\d+(\.\d+)?\]/', $notas_actuales)) {
            $nota_extras = "\n[EXTRAS:$extras]";
            $notas_finales = trim($notas_actuales . $nota_extras);
        } else {
            $notas_finales = $notas_actuales; // Ya tiene extras, no duplicar
        }
    } else {
        $notas_finales = $data['notas'] ?? '';
    }

    // Luego, en el UPDATE, usa $notas_finales en lugar de $notas_actuales
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?,
            notas = ?,  -- ← Aquí van las notas con extras si aplica
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt_update->execute([$nuevo_estado_pago, $metodo_pago, $transaccion_id ?: null, $nuevo_monto_recaudado, $notas_finales, $id_reserva]);
    
    // NOTA: Función enviarEmailConfirmacionPago eliminada porque no existe.
    // Si necesitas enviar emails, debes crear esa función o usar mail() directamente.
    
    return [
        'success' => true,
        'message' => 'Pago total registrado correctamente',
        'id_reserva' => $id_reserva
    ];
}

function procesarPagoParcial($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $monto_pagado = (float)($data['monto_pagado'] ?? 0);
    $monto_total_original = (float)($data['monto_total_original'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    $notas_pago = trim($data['notas_pago'] ?? '');
    
    if (!$id_reserva || !$metodo_pago || $monto_pagado <= 0) {
        throw new Exception('Datos incompletos o inválidos');
    }
    
    // 1. Obtener datos actuales (incluyendo monto_recaudacion actual si hubo pagos previos)
    $stmt_check = $pdo->prepare("SELECT id_reserva, estado_pago, monto_total, monto_recaudacion, notas FROM reservas WHERE id_reserva = ?");
    $stmt_check->execute([$id_reserva]);
    $reserva = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Si ya está pagado completamente, bloquear
    if ($reserva['estado_pago'] === 'pagado') {
        throw new Exception('Esta reserva ya está marcada como PAGADA.');
    }
    
    // Calcular nuevo monto recaudado acumulado
    $monto_recaudado_actual = (float)($reserva['monto_recaudacion'] ?? 0);
    $nuevo_monto_recaudado = $monto_recaudado_actual + $monto_pagado;
    
    // 2. Determinar nuevo estado
    $nuevo_estado_pago = 'parcial'; // Por defecto es parcial
    
    // Si el acumulado cubre el total, marcamos como pagado
    if ($nuevo_monto_recaudado >= $monto_total_original) {
        $nuevo_estado_pago = 'pagado';
        // Ajustar al monto exacto si se pasó (opcional)
        // $nuevo_monto_recaudado = $monto_total_original; 
    }
    
    // 3. Construir el texto de notas
    $fecha_hoy = date('d/m/Y H:i');
    $nota_nueva = "\n[PAGO {$fecha_hoy}]: $" . number_format($monto_pagado, 0, ',', '.') . " vía {$metodo_pago}";
    
    if (!empty($notas_pago)) {
        $nota_nueva .= " - Obs: {$notas_pago}";
    }
    
    $notas_finales = !empty($reserva['notas']) ? $reserva['notas'] . $nota_nueva : ltrim($nota_nueva);
    
    // 4. Actualizar la reserva
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?, -- ACTUALIZADO: Guardamos el acumulado
            notas = ?,
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    
    $stmt_update->execute([
        $nuevo_estado_pago,
        $metodo_pago,
        $transaccion_id,
        $nuevo_monto_recaudado,
        $notas_finales,
        $id_reserva
    ]);
    
    return [
        'success' => true,
        'message' => 'Pago registrado correctamente. Estado: ' . $nuevo_estado_pago,
        'estado_nuevo' => $nuevo_estado_pago,
        'monto_recaudado' => $nuevo_monto_recaudado
    ];
}
// === FUNCIÓN NUEVA: Crear reserva + registrar log (mínimo código) ===
function crearReservaManualConLog($pdo, $data) {
    $id_cancha = (int)($data['id_cancha'] ?? 0);
    $id_socio = !empty($data['id_socio']) ? (int)$data['id_socio'] : null;
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';
    $hora_fin = $data['hora_fin'] ?? '';
    $monto_total = (float)($data['monto_total'] ?? 0);
    
    if (!$id_cancha || !$fecha || !$hora_inicio) {
        throw new Exception('Datos incompletos');
    }
    
    // Verificar cancha pertenece al recinto
    $stmt = $pdo->prepare("SELECT id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    if ($stmt->fetchColumn() != $_SESSION['id_recinto']) {
        throw new Exception('Cancha no válida');
    }
    
    // Verificar disponibilidad
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Horario ocupado');
    }
    
    // Insertar reserva
    $stmt = $pdo->prepare("INSERT INTO reservas (id_cancha, id_socio, fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())");
    $stmt->execute([$id_cancha, $id_socio, $fecha, $hora_inicio, $hora_fin, $monto_total]);
    
    $id_reserva = $pdo->lastInsertId();
    
    // ✅ Registrar log (solo 1 query extra)
    if ($id_reserva) {
        $usuario = $_SESSION['recinto_usuario'] ?? $_SESSION['recinto_rol'] ?? 'Sistema';
        $stmt_log = $pdo->prepare("INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) VALUES (?, ?, 'creada', 'Reserva manual creada', NOW())");
        $stmt_log->execute([$id_reserva, $usuario]);
    }
    
    return ['success' => true, 'id_reserva' => $id_reserva];
}
function crearReservaManualConSocioNuevo($pdo, $data) {
    $id_cancha = (int)($data['id_cancha'] ?? 0);
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';
    $hora_fin = $data['hora_fin'] ?? '';
    $monto_total = (float)($data['monto_total'] ?? 0);
    $id_socio = !empty($data['id_socio']) ? (int)$data['id_socio'] : null;
    
    // 1. Si no viene id_socio, crear nuevo socio
    if (!$id_socio) {
        $email = trim($data['emailNuevoSocio'] ?? '');
        $nombre = trim($data['nombreNuevoSocio'] ?? 'Nuevo Socio');
        $tel = trim($data['telNuevoSocio'] ?? '');
        if (!$email) throw new Exception('Email requerido para nuevo socio');
        
        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existente = $stmt->fetch();
        
        if ($existente) {
            $id_socio = $existente['id_socio'];
        } else {
            $alias = strtolower(explode('@', $email)[0]);
            $stmt = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$email, $nombre, $alias, $tel]);
            $id_socio = $pdo->lastInsertId();
        }
    }
    
    // 2. Crear reserva
    $stmt = $pdo->prepare("INSERT INTO reservas (id_cancha, id_socio, fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())");
    $stmt->execute([$id_cancha, $id_socio, $fecha, $hora_inicio, $hora_fin, $monto_total]);
    $id_reserva = $pdo->lastInsertId();
    
    // 3. Log de creación (bitácora)
    $usuario = $_SESSION['recinto_usuario'] ?? $_SESSION['recinto_rol'] ?? 'Sistema';
    $pdo->prepare("INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) VALUES (?, ?, 'creada', 'Reserva manual + nuevo socio', NOW())")
        ->execute([$id_reserva, $usuario]);
    
    // 4. Emails (Reserva + Bienvenida)
    require_once __DIR__ . '/../includes/reserva_mailer.php';
    $stmt_socio = $pdo->prepare("SELECT email, nombre FROM socios WHERE id_socio = ?");
    $stmt_socio->execute([$id_socio]);
    $socio = $stmt_socio->fetch();
    
    if ($socio && $socio['email']) {
        $link_perfil = "https://" . $_SERVER['HTTP_HOST'] . "/pages/completar_perfil.php?id=" . $id_socio; // Ajusta ruta si es distinta
        
        // Email 1: Reserva confirmada
        $mail1 = new BrevoMailer();
        $mail1->setTo($socio['email'], $socio['nombre'])
              ->setSubject("✅ Reserva confirmada - CanchaSport")
              ->setHtmlBody("<p>Reserva para el <strong>$fecha $hora_inicio-$hora_fin</strong> confirmada.</p>")
              ->send();
              
        // Email 2: Bienvenida + completar perfil
        $mail2 = new BrevoMailer();
        $mail2->setTo($socio['email'], $socio['nombre'])
              ->setSubject("🎉 ¡Bienvenido a CanchaSport! Completa tu perfil")
              ->setHtmlBody("<p>Gracias por registrarte. <a href='$link_perfil'>Haz clic aquí para completar tu perfil</a> y disfrutar de todos los beneficios.</p>")
              ->send();
    }
    
    return ['success' => true, 'id_reserva' => $id_reserva, 'id_socio' => $id_socio];
}
?>