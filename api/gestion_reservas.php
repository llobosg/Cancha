<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
// === EMAILS CON DISEÑO CANCHASPORT ===
require_once __DIR__ . '/../includes/reserva_mailer.php';
require_once __DIR__ . '/../includes/email_template.php';

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
        
    // ✅ UN SOLO CASE para crear reserva manual (maneja socio existente o nuevo)
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
// === FUNCIÓN UNIFICADA: Crear reserva manual (socio existente o nuevo) ===
function crearReservaManualUnificada($pdo, $data) {
    $id_cancha = (int)($data['id_cancha'] ?? 0);
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';
    $hora_fin = $data['hora_fin'] ?? '';
    $monto_total = (float)($data['monto_total'] ?? 0);
    
    // Validaciones básicas
    if (!$id_cancha || !$fecha || !$hora_inicio) {
        throw new Exception('Datos incompletos para crear reserva');
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
    
    // === 1. DETERMINAR ID_SOCIO Y DATOS DEL CLIENTE ===
    $id_socio = !empty($data['id_socio']) ? (int)$data['id_socio'] : null;
    $nombre_cliente = '';
    $email_cliente = '';
    $telefono_cliente = '';
    
    if ($id_socio) {
        // ✅ SOCIO EXISTENTE: Obtener datos de la tabla socios
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio]);
        $socio_data = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($socio_data) {
            $nombre_cliente = $socio_data['nombre'] ?? '';
            $email_cliente = $socio_data['email'] ?? '';
            $telefono_cliente = $socio_data['celular'] ?? '';
        }
    } else {
        // ✅ NUEVO SOCIO: Crear registro y usar datos del formulario
        $email_nuevo = trim($data['emailNuevoSocio'] ?? '');
        $nombre_nuevo = trim($data['nombreNuevoSocio'] ?? 'Nuevo Socio');
        $tel_nuevo = trim($data['telNuevoSocio'] ?? '');
        
        if (!$email_nuevo) throw new Exception('Email requerido para nuevo socio');
        
        // Verificar si ya existe por email
        $stmt = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
        $stmt->execute([$email_nuevo]);
        $existente = $stmt->fetch();
        
        if ($existente) {
            $id_socio = $existente['id_socio'];
            $nombre_cliente = $nombre_nuevo;
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
        // === DENTRO DEL BLOQUE "NUEVO SOCIO" ===
        } else {
            // Crear nuevo socio con token de registro
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $registro_token = bin2hex(random_bytes(32)); // Token único de 64 caracteres
            $registro_expires = date('Y-m-d H:i:s', strtotime('+7 days')); // Válido por 7 días
            
            $stmt = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, registro_token, registro_token_expires, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$email_nuevo, $nombre_nuevo, $alias, $tel_nuevo, $registro_token, $registro_expires]);
            $id_socio = $pdo->lastInsertId();
            
            $nombre_cliente = $nombre_nuevo;
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
            
            // === GENERAR LINK DE COMPLETAR REGISTRO ===
            $registro_link = "https://" . $_SERVER['HTTP_HOST'] . "/pages/completar_registro.php?token=" . $registro_token;
        }
    }
    
    // === 2. INSERTAR RESERVA CON DATOS DE CLIENTE ===
    $stmt = $pdo->prepare("
        INSERT INTO reservas (
            id_cancha, id_socio, nombre_cliente, email_cliente, telefono_cliente,
            fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())
    ");
    $stmt->execute([
        $id_cancha, $id_socio, $nombre_cliente, $email_cliente, $telefono_cliente,
        $fecha, $hora_inicio, $hora_fin, $monto_total
    ]);
    $id_reserva = $pdo->lastInsertId();
    
    // === 3. REGISTRAR LOG DE BITÁCORA ===
    $usuario = $_SESSION['recinto_usuario'] ?? $_SESSION['recinto_rol'] ?? 'Sistema';
    $stmt_log = $pdo->prepare("INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) VALUES (?, ?, 'creada', 'Reserva manual', NOW())");
    $stmt_log->execute([$id_reserva, $usuario]);
    
    // === 4. EMAILS (solo si es socio NUEVO) ===
    if (empty($data['id_socio']) && $email_cliente) {
        require_once __DIR__ . '/../includes/reserva_mailer.php';
        $link_perfil = "https://" . $_SERVER['HTTP_HOST'] . "/pages/completar_perfil.php?id=" . $id_socio;
        
        // === Email 1: Confirmación de Reserva ===
        $cuerpo_reserva = "
            <p>Hola <strong>{$nombre_cliente}</strong>,</p>
            <p>Tu reserva ha sido confirmada exitosamente:</p>
            <div class='info-box' style='background: #F3E5F5; border-left: 4px solid #AB47BC; padding: 1rem; border-radius: 8px; margin: 1.5rem 0;'>
                <p style='margin: 0.3rem 0'><strong>📅 Fecha:</strong> {$fecha}</p>
                <p style='margin: 0.3rem 0'><strong>⏰ Hora:</strong> {$hora_inicio} - {$hora_fin}</p>
                <p style='margin: 0.3rem 0'><strong>🏟️ Cancha:</strong> {$cancha_nombre ?? 'Consultar en app'}</p>
            </div>
            <p style='color: #666; font-size: 0.95rem;'>Puedes ver todas tus reservas en tu perfil de CanchaSport.</p>
        ";
        
        $email_reserva = generarEmailCanchaSport(
            '✅ Reserva Confirmada',
            'Tu cancha te espera',
            $cuerpo_reserva,
            'Ver mis reservas',
            'https://' . $_SERVER['HTTP_HOST'] . '/pages/dashboard_socio.php'
        );
        
        $mail1 = new BrevoMailer();
        $mail1->setTo($email_cliente, $nombre_cliente)
            ->setSubject("✅ Reserva confirmada - CanchaSport")
            ->setHtmlBody($email_reserva)
            ->send();
        
        // === Email 2: Bienvenida + Completar Perfil (SOLO si es socio nuevo) ===
        if (empty($data['id_socio']) && isset($registro_link)) {
            $cuerpo_bienvenida = "
                <p>¡Bienvenido a <strong>CanchaSport</strong>! 🎉</p>
                <p>Para disfrutar de todos los beneficios, completa tu perfil en menos de 1 minuto:</p>
                <ul style='color: #4A4A4A; padding-left: 1.2rem;'>
                    <li>Establece tu contraseña segura</li>
                    <li>Agrega tu número de teléfono</li>
                    <li>Personaliza tus preferencias deportivas</li>
                </ul>
                <p style='color: #888; font-size: 0.9rem; margin-top: 1.5rem;'><strong>⏰ Este enlace expira en 7 días</strong> por seguridad.</p>
            ";
            
            $email_bienvenida = generarEmailCanchaSport(
                '🎉 ¡Bienvenido a CanchaSport!',
                'Completa tu perfil en 1 minuto',
                $cuerpo_bienvenida,
                '✨ Completar mi perfil',
                $registro_link,
                "<p style='color: #888; font-size: 0.85rem; margin-top: 1.5rem; border-top: 1px solid #eee; padding-top: 1rem;'>Si no creaste esta cuenta, ignora este mensaje.</p>"
            );
            
            $mail2 = new BrevoMailer();
            $mail2->setTo($email_cliente, $nombre_cliente)
                ->setSubject("🎉 ¡Bienvenido! Completa tu perfil - CanchaSport")
                ->setHtmlBody($email_bienvenida)
                ->send();
        }
    }   
    
    return ['success' => true, 'id_reserva' => $id_reserva, 'id_socio' => $id_socio];
}
?>