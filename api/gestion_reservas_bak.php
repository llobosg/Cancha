<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');

// Limpieza de buffer para evitar JSON roto
if (ob_get_level() > 0) { ob_clean(); }

require_once __DIR__ . '/../includes/config.php';
// Asegúrate de tener este archivo con la función registrarLogReserva
// Si no existe, crea includes/bitacora.php con la función que te pasé antes
if (file_exists(__DIR__ . '/../includes/bitacora.php')) {
    require_once __DIR__ . '/../includes/bitacora.php';
}

try {
    // 1. Verificar autenticación básica
    if (!isset($_SESSION['id_recinto'])) {
        throw new Exception('Sesión no iniciada', 401);
    }

    // 2. Verificar Rol
    $rol_actual = $_SESSION['recinto_rol'] ?? '';
    $roles_permitidos = ['admin', 'asistente'];
    if (!in_array($rol_actual, $roles_permitidos)) {
        error_log("[API GESTIÓN RESERVAS] Acceso denegado. Rol: '$rol_actual'");
        throw new Exception('Acceso no autorizado: Rol inválido', 401);
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Debug log
    error_log("?? [API] Acción recibida: $action | Usuario: " . ($_SESSION['recinto_usuario'] ?? 'Desconocido'));

    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
        case 'crear_manual':
            echo json_encode(crearReservaManualUnificada($pdo, $_POST));
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function procesarPagoReserva($pdo, $data) {
    $id_reserva = (int)($data['id_reserva'] ?? 0);
    $metodo_pago = trim($data['metodo_pago'] ?? '');
    $transaccion_id = trim($data['transaccion_id'] ?? '');
    $monto_total = (float)($data['monto_total'] ?? 0);
    $extras = floatval($data['extras'] ?? 0);

    if (!$id_reserva || !$metodo_pago) {
        throw new Exception('Datos incompletos para procesar pago');
    }

    // Verificar propiedad
    $stmt_check = $pdo->prepare("
        SELECT r.id_reserva, r.estado_pago, r.monto_total, r.monto_recaudacion, r.notas, c.id_recinto
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

    // Manejo de Extras en Notas
    $notas_finales = $reserva['notas'] ?? '';
    if ($extras > 0) {
        if (!preg_match('/\[EXTRAS:\d+(\.\d+)?\]/', $notas_finales)) {
            $notas_finales .= "\n[EXTRAS:$extras]";
        }
    }

    // Calcular montos
    $nuevo_monto_recaudado = ($reserva['monto_recaudacion'] ?? 0) + $monto_total;
    $nuevo_estado_pago = ($nuevo_monto_recaudado >= $reserva['monto_total']) ? 'pagado' : 'parcial';

    // Actualizar BD
    $stmt_update = $pdo->prepare("
        UPDATE reservas
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?,
            notas = ?,
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt_update->execute([
        $nuevo_estado_pago, 
        $metodo_pago, 
        $transaccion_id ?: null, 
        $nuevo_monto_recaudado, 
        $notas_finales, 
        $id_reserva
    ]);

    // Registrar Log
    if (function_exists('registrarLogReserva')) {
        registrarLogReserva(
            $pdo,
            $id_reserva,
            ($nuevo_estado_pago === 'pagado') ? 'cobro_total' : 'cobro_parcial',
            "Pago registrado vía $metodo_pago. Monto: $" . number_format($monto_total, 0, ',', '.'),
            $_SESSION['recinto_usuario'] ?? 'Admin',
            $reserva['monto_recaudacion'],
            $nuevo_monto_recaudado
        );
    }

    return [
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'estado_nuevo' => $nuevo_estado_pago
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

    // Obtener datos actuales
    $stmt_check = $pdo->prepare("SELECT id_reserva, estado_pago, monto_total, monto_recaudacion, notas FROM reservas WHERE id_reserva = ?");
    $stmt_check->execute([$id_reserva]);
    $reserva = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    if ($reserva['estado_pago'] === 'pagado') {
        throw new Exception('Esta reserva ya está marcada como PAGADA.');
    }

    // Calcular acumulado
    $monto_recaudado_actual = (float)($reserva['monto_recaudacion'] ?? 0);
    $nuevo_monto_recaudado = $monto_recaudado_actual + $monto_pagado;

    // Determinar estado
    $nuevo_estado_pago = 'parcial';
    if ($nuevo_monto_recaudado >= $monto_total_original) {
        $nuevo_estado_pago = 'pagado';
        $nuevo_monto_recaudado = $monto_total_original; // Ajuste exacto
    }

    // Construir nota
    $fecha_hoy = date('d/m/Y H:i');
    $nota_nueva = "\n[PAGO $fecha_hoy]: $" . number_format($monto_pagado, 0, ',', '.') . " vía $metodo_pago";
    if (!empty($notas_pago)) {
        $nota_nueva .= " - Obs: {$notas_pago}";
    }
    $notas_finales = !empty($reserva['notas']) ? $reserva['notas'] . $nota_nueva : ltrim($nota_nueva);

    // Actualizar BD
    $stmt_update = $pdo->prepare("
        UPDATE reservas
        SET estado_pago = ?,
            metodo_pago = ?,
            transaccion_id = ?,
            monto_recaudacion = ?,
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

    // Registrar Log
    if (function_exists('registrarLogReserva')) {
        registrarLogReserva(
            $pdo,
            $id_reserva,
            'cobro_parcial',
            "Abono parcial: $" . number_format($monto_pagado, 0, ',', '.'),
            $_SESSION['recinto_usuario'] ?? 'Admin',
            $monto_recaudado_actual,
            $nuevo_monto_recaudado
        );
    }

    return [
        'success' => true,
        'message' => 'Pago registrado correctamente. Estado: ' . $nuevo_estado_pago,
        'estado_nuevo' => $nuevo_estado_pago,
        'monto_recaudado' => $nuevo_monto_recaudado
    ];
}

function crearReservaManualUnificada($pdo, $data) {
    $id_cancha = isset($data['id_cancha']) ? (int)$data['id_cancha'] : 0;
    $fecha = isset($data['fecha']) ? $data['fecha'] : '';
    $hora_inicio = isset($data['hora_inicio']) ? $data['hora_inicio'] : '';
    $hora_fin = isset($data['hora_fin']) ? $data['hora_fin'] : '';
    $monto_total = isset($data['monto_total']) ? (float)$data['monto_total'] : 0;
    $id_socio_input = !empty($data['id_socio']) ? (int)$data['id_socio'] : null;
    
    // Datos nuevo socio
    $nombre_nuevo = trim($data['nombreNuevoSocio'] ?? '');
    $email_nuevo = trim($data['emailNuevoSocio'] ?? '');
    $tel_nuevo = trim($data['telNuevoSocio'] ?? '');

    if (!$id_cancha || !$fecha || !$hora_inicio) {
        throw new Exception('Datos incompletos para crear reserva');
    }

    // Verificar cancha pertenece al recinto
    $stmt = $pdo->prepare("SELECT id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    if ($stmt->fetchColumn() != $_SESSION['id_recinto']) {
        throw new Exception('Cancha no válida para este recinto');
    }

    // Verificar disponibilidad
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Horario ocupado');
    }

    // === DETERMINAR ID_SOCIO Y DATOS DEL CLIENTE ===
    $id_socio_final = null;
    $nombre_cliente = '';
    $email_cliente = '';
    $telefono_cliente = '';

    if ($id_socio_input) {
        // SOCIO EXISTENTE
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio_input]);
        $socio_data = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($socio_data) {
            $id_socio_final = $id_socio_input;
            $nombre_cliente = $socio_data['nombre'] ?? '';
            $email_cliente = $socio_data['email'] ?? '';
            $telefono_cliente = $socio_data['celular'] ?? '';
        }
    } elseif (!empty($email_nuevo) && !empty($nombre_nuevo)) {
        // NUEVO SOCIO O BÚSQUEDA POR EMAIL
        $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt->execute([$email_nuevo]);
        $existente = $stmt->fetch();
        
        if ($existente) {
            $id_socio_final = $existente['id_socio'];
            $nombre_cliente = $nombre_nuevo; // Actualizamos nombre por si cambió
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
        } else {
            // Crear nuevo socio
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $stmt = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$email_nuevo, $nombre_nuevo, $alias, $tel_nuevo]);
            $id_socio_final = $pdo->lastInsertId();
            $nombre_cliente = $nombre_nuevo;
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
        }
    } else {
        throw new Exception('Debe seleccionar un socio existente o ingresar datos para uno nuevo');
    }

    // === INSERTAR RESERVA ===
    $stmt = $pdo->prepare("
        INSERT INTO reservas (
            id_cancha, id_socio, nombre_cliente, email_cliente, telefono_cliente,
            fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())
    ");
    $stmt->execute([
        $id_cancha, $id_socio_final, $nombre_cliente, $email_cliente, $telefono_cliente,
        $fecha, $hora_inicio, $hora_fin, $monto_total
    ]);
    $id_reserva = $pdo->lastInsertId();

    // === REGISTRAR LOG DE BITÁCORA ===
    if (function_exists('registrarLogReserva')) {
        registrarLogReserva(
            $pdo,
            $id_reserva,
            'creada',
            "Reserva manual creada por Admin/Asistente",
            $_SESSION['recinto_usuario'] ?? 'Admin',
            null,
            $monto_total
        );
    }

    // === ENVIAR CORREO DE CONFIRMACIÓN ===
    if (!empty($email_cliente)) {
        try {
            require_once __DIR__ . '/../includes/reserva_mailer.php';
            if (class_exists('BrevoMailer')) {
                $mail = new BrevoMailer();
                $fecha_fmt = date('d/m/Y', strtotime($fecha));
                $hora_fmt = substr($hora_inicio, 0, 5) . ' - ' . substr($hora_fin, 0, 5);
                
                // Obtener nombre cancha
                $stmt_c = $pdo->prepare("SELECT nombre_cancha FROM canchas WHERE id_cancha = ?");
                $stmt_c->execute([$id_cancha]);
                $nombre_cancha = $stmt_c->fetchColumn() ?: 'Cancha';

                $html_body = "
                    <h2>Hola {$nombre_cliente}</h2>
                    <p>Tu reserva ha sido creada exitosamente.</p>
                    <ul>
                        <li><strong>Cancha:</strong> {$nombre_cancha}</li>
                        <li><strong>Fecha:</strong> {$fecha_fmt}</li>
                        <li><strong>Hora:</strong> {$hora_fmt}</li>
                        <li><strong>Monto Total:</strong> $" . number_format($monto_total, 0, ',', '.') . "</li>
                    </ul>
                    <p>Te esperamos en el recinto.</p>
                ";

                $mail->setTo($email_cliente, $nombre_cliente)
                    ->setSubject("✅ Confirmación de Reserva - CanchaSport")
                    ->setHtmlBody($html_body)
                    ->send();
            }
        } catch (Exception $e) {
            error_log("Error enviando correo confirmación: " . $e->getMessage());
            // No interrumpimos el flujo si falla el correo
        }
    }

    return ['success' => true, 'id_reserva' => $id_reserva, 'id_socio' => $id_socio_final];
}
?>