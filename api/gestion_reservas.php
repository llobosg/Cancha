<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');

// Limpieza de buffer segura para evitar JSON roto por warnings/espacios
while (ob_get_level()) { ob_end_clean(); }

require_once __DIR__ . '/../includes/config.php';

// Cargar bitácora si existe
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
    
    // Permitir también a admins globales si tu sistema lo usa
    if (!in_array($rol_actual, $roles_permitidos)) {
        error_log("[API GESTIÓN RESERVAS] Acceso denegado. Rol: '$rol_actual'");
        throw new Exception('Acceso no autorizado: Rol inválido', 401);
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    error_log("📝 [API] Acción recibida: $action | Usuario: " . ($_SESSION['recinto_usuario'] ?? 'Desconocido'));

    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
        case 'crear_manual':
            try {
                // 1. Extraer datos básicos
                $id_cancha = (int)($_POST['id_cancha'] ?? 0);
                $fecha = $_POST['fecha'] ?? '';
                $hora_inicio = $_POST['hora_inicio'] ?? '';
                $hora_fin = $_POST['hora_fin'] ?? '';
                $monto_total = floatval($_POST['monto_total'] ?? 0);
                
                // 2. Lógica de Socio / Convenio / Nuevo Socio
                $id_socio = isset($_POST['id_socio']) && !empty($_POST['id_socio']) ? (int)$_POST['id_socio'] : null;
                $id_convenio = isset($_POST['id_convenio']) && !empty($_POST['id_convenio']) ? (int)$_POST['id_convenio'] : null;
                
                $nombre_cliente = $_POST['nombre_cliente'] ?? null;
                $email_cliente = $_POST['email_cliente'] ?? null;
                $telefono_cliente = $_POST['telefono_cliente'] ?? null;
                
                $es_socio_nuevo = false; // Flag para controlar envío de email

                // === A. CREAR SOCIO EXPRESS SI VIENE DATO DIRECTO (Fallback) ===
                if (!$id_socio && !empty($_POST['nombreNuevoSocio'])) {
                    $nNom = trim($_POST['nombreNuevoSocio']);
                    $nMail = trim($_POST['emailNuevoSocio']);
                    $nTel = trim($_POST['telNuevoSocio'] ?? '');
                    
                    if (empty($nMail) || empty($nNom)) {
                        throw new Exception("Nombre y Email son obligatorios para nuevo socio.");
                    }
                    
                    // Verificar si ya existe
                    $stmt_check = $pdo->prepare("SELECT id_socio FROM socios WHERE email = ? LIMIT 1");
                    $stmt_check->execute([$nMail]);
                    $existente = $stmt_check->fetch();
                    
                    if ($existente) {
                        $id_socio = $existente['id_socio'];
                    } else {
                        $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $nMail)[0]));
                        // Asegurar unicidad alias
                        $base_alias = $alias;
                        $counter = 1;
                        while(true) {
                            $stmt_a = $pdo->prepare("SELECT COUNT(*) FROM socios WHERE alias = ?");
                            $stmt_a->execute([$alias]);
                            if($stmt_a->fetchColumn() == 0) break;
                            $alias = $base_alias . $counter++;
                        }
                        
                        $stmt_new = $pdo->prepare("INSERT INTO socios (nombre, email, alias, celular, created_at, email_verified) VALUES (?, ?, ?, ?, NOW(), 1)");
                        $stmt_new->execute([$nNom, $nMail, $alias, $nTel]);
                        $id_socio = $pdo->lastInsertId();
                        $es_socio_nuevo = true; // Marcamos como nuevo
                        
                        // Guardar datos para cliente
                        $nombre_cliente = $nNom;
                        $email_cliente = $nMail;
                        $telefono_cliente = $nTel;
                    }
                }
                
                // === B. VALIDAR SOCIO O CONVENIO ===
                if (!$id_socio) {
                    // Si no hay socio, validar que tengamos al menos un convenio
                    if (!$id_convenio) {
                         throw new Exception("Debe seleccionar un socio, registrar uno nuevo o aplicar un Convenio.");
                    }
                    
                    // Si hay convenio, obtener datos de contacto
                    $stmt_conv = $pdo->prepare("SELECT nombre_empresa, contacto_email, contacto_telefono FROM convenios WHERE id_convenio = ? AND id_recinto = ?");
                    $stmt_conv->execute([$id_convenio, $_SESSION['id_recinto']]);
                    $conv = $stmt_conv->fetch();
                    
                    if ($conv) {
                        $nombre_cliente = $conv['nombre_empresa'];
                        $email_cliente = $email_cliente ?: $conv['contacto_email'];
                        $telefono_cliente = $telefono_cliente ?: $conv['contacto_telefono'];
                    } else {
                        throw new Exception("Convenio no válido o no pertenece a este recinto.");
                    }
                } else {
                    // Si hay socio, obtener sus datos para llenar cliente si viene vacío
                    if (!$nombre_cliente) {
                        $stmt_s = $pdo->prepare("SELECT nombre, email, celular, password_hash FROM socios WHERE id_socio = ?");
                        $stmt_s->execute([$id_socio]);
                        $s = $stmt_s->fetch();
                        if ($s) {
                            $nombre_cliente = $s['nombre'];
                            $email_cliente = $email_cliente ?: $s['email'];
                            $telefono_cliente = $telefono_cliente ?: $s['celular'];
                            
                            // ✅ VERIFICAR SI ES SOCIO NUEVO (Sin password_hash)
                            if (empty($s['password_hash'])) {
                                $es_socio_nuevo = true;
                            }
                        }
                    }
                }
                
                // 3. Obtener ID Club del Socio (si existe)
                // ✅ CORRECCIÓN: Usar tabla intermedia socio_club en lugar de buscar en socios
                $id_club_reserva = null;
                if ($id_socio) {
                    try {
                        $stmt_club = $pdo->prepare("
                            SELECT id_club 
                            FROM socio_club 
                            WHERE id_socio = ? AND estado = 'activo' 
                            LIMIT 1
                        ");
                        $stmt_club->execute([$id_socio]);
                        $socio_club = $stmt_club->fetch(PDO::FETCH_ASSOC);
                        if ($socio_club) {
                            $id_club_reserva = $socio_club['id_club'];
                        }
                    } catch (Exception $e) {
                        error_log("Error obteniendo club del socio: " . $e->getMessage());
                    }
                }
                
                // 4. Insertar Reserva
                $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        id_cancha, id_club, id_socio, id_convenio, 
                        nombre_cliente, email_cliente, telefono_cliente,
                        fecha, hora_inicio, hora_fin, monto_total, 
                        estado_pago, estado, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', NOW())
                ");
                
                $stmt->execute([
                    $id_cancha, 
                    $id_club_reserva, 
                    $id_socio, 
                    $id_convenio,
                    $nombre_cliente, 
                    $email_cliente, 
                    $telefono_cliente,
                    $fecha, 
                    $hora_inicio, 
                    $hora_fin, 
                    $monto_total
                ]);
                
                $id_reserva = $pdo->lastInsertId();
                
                // 5. Enviar Email de Activación SI EL SOCIO ES NUEVO (Sin Password)
                if ($es_socio_nuevo && $email_cliente) {
                    try {
                        require_once __DIR__ . '/../includes/brevo_mailer.php';
                        
                        $token_activacion = bin2hex(random_bytes(32));
                        $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        
                        // Actualizar token en BD
                        $stmt_token = $pdo->prepare("UPDATE socios SET activation_token = ?, token_expires_at = ? WHERE id_socio = ?");
                        $stmt_token->execute([$token_activacion, $fecha_expiracion, $id_socio]);
                        
                        // Construir Link
                        $link_activacion = "https://canchasport.com/pages/activar_cuenta.php?token=" . $token_activacion;
                       
                        $mail = new BrevoMailer();
                        $mail->setTo($email_cliente, $nombre_cliente);
                        $mail->setSubject("🎉 ¡Bienvenido a CanchaSport! Activa tu cuenta");
                        
                        $htmlBody = "
                            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #071289;'>¡Hola {$nombre_cliente}!</h2>
                                <p>Te hemos registrado como socio en <strong>CanchaSport</strong>.</p>
                                <p>Para acceder a la app y gestionar tus reservas, crea una contraseña:</p>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='{$link_activacion}' 
                                    style='background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                                        🔐 Activar mi Cuenta
                                    </a>
                                </div>
                            </div>
                        ";
                        
                        $mail->setHtmlBody($htmlBody);
                        $mail->send();
                        error_log("✅ Email activación enviado a: $email_cliente");
                        
                    } catch (Exception $e) {
                        error_log("❌ Error email activación: " . $e->getMessage());
                    }
                }
                
                // 6. Respuesta Éxito
                echo json_encode(['success' => true, 'id_reserva' => $id_reserva]);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
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

    // Verificar propiedad y estado actual
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

    // Manejo de Extras en Notas (formato estructurado)
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
        $nuevo_monto_recaudado = $monto_total_original; // Ajuste exacto para no pasarse
    }

    // Construir nota con fecha
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
    
    // ✅ CORRECCIÓN: Asegurar que monto_total sea float, si viene 0 o vacío, usar 0.0
    $monto_total = isset($data['monto_total']) ? (float)$data['monto_total'] : 0.0;
    
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

    // === OBTENER DATOS DE LA CANCHA (Incluyendo Capacidad) ===
    $stmt_cancha = $pdo->prepare("SELECT id_recinto, capacidad_jugadores FROM canchas WHERE id_cancha = ?");
    $stmt_cancha->execute([$id_cancha]);
    $cancha_data = $stmt_cancha->fetch(PDO::FETCH_ASSOC);
    
    if (!$cancha_data || $cancha_data['id_recinto'] != $_SESSION['id_recinto']) {
        throw new Exception('Cancha no válida para este recinto');
    }
    
    // Usar capacidad de la BD, o default 4 si es null
    $jugadores_esperados = intval($cancha_data['capacidad_jugadores'] ?? 4);

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
            $nombre_cliente = $nombre_nuevo;
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

    $id_convenio = isset($data['id_convenio']) ? (int)$data['id_convenio'] : null;
    $descuento_aplicado = isset($data['monto_total']) && isset($data['admin_monto_base']) ? 0 : 0; 
    // Nota: Es más seguro recalculcar el descuento en el backend si recibes el ID del convenio.

    $monto_final = isset($data['monto_total']) ? (float)$data['monto_total'] : 0;

    // === INSERTAR RESERVA ===
    // === OBTENER ID_CLUB DEL SOCIO (Si existe) ===
    $id_club_reserva = null;
    if ($id_socio_final) {
        $stmt_club = $pdo->prepare("SELECT id_club FROM socios WHERE id_socio = ? LIMIT 1");
        $stmt_club->execute([$id_socio_final]);
        $socio_club_data = $stmt_club->fetch(PDO::FETCH_ASSOC);
        if ($socio_club_data && !empty($socio_club_data['id_club'])) {
            $id_club_reserva = $socio_club_data['id_club'];
        }
    }

    // === INSERTAR RESERVA CON JUGADORES_ESPERADOS CORRECTO ===
    $stmt = $pdo->prepare("
        INSERT INTO reservas (
            id_cancha, id_club, id_socio, nombre_cliente, email_cliente, telefono_cliente,
            fecha, hora_inicio, hora_fin, monto_total, estado_pago, estado, jugadores_esperados, created_at, id_convenio
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 'confirmada', ?, NOW(), ?)
    ");
    
    // Obtener id_club del socio si existe
    $id_club_reserva = null;
    if ($id_socio_final) {
        $stmt_club = $pdo->prepare("SELECT id_club FROM socios WHERE id_socio = ? LIMIT 1");
        $stmt_club->execute([$id_socio_final]);
        $socio_club = $stmt_club->fetch(PDO::FETCH_ASSOC);
        if ($socio_club) $id_club_reserva = $socio_club['id_club'];
    }

    $stmt->execute([
        $id_cancha, 
        $id_club_reserva, 
        $id_socio_final, 
        $nombre_cliente, 
        $email_cliente, 
        $telefono_cliente,
        $fecha, 
        $hora_inicio, 
        $hora_fin, 
        $monto_total,
        $jugadores_esperados, // <--- AQUÍ SE GUARDA LA CAPACIDAD REAL (20)
        $id_convenio
    ]);
    
    $id_reserva = $pdo->lastInsertId();

    // === REGISTRAR LOG DE BITÁCORA CON DETALLE DE CONVENIO ===
    if (function_exists('registrarLogReserva')) {
        $descripcion = "Reserva manual creada por Admin/Asistente";
        
        if ($id_convenio) {
            // Obtener nombre del convenio para el log
            $stmt_conv = $pdo->prepare("SELECT nombre_empresa, porc_dscto FROM convenios WHERE id_convenio = ?");
            $stmt_conv->execute([$id_convenio]);
            $conv_data = $stmt_conv->fetch();
            
            if ($conv_data) {
                $descripcion .= " | 🤝 CONVENIO: {$conv_data['nombre_empresa']} ({$conv_data['porc_dscto']}% OFF)";
                
                // Opcional: Si no hay socio asociado, usar el email del contacto del convenio para enviar correo
                if (empty($email_cliente) && !empty($conv_data['contacto_email'])) {
                    // Aquí podrías actualizar el email de la reserva o enviar el correo al contacto
                    // Por ahora, asumimos que el email ya está en $email_cliente si se seleccionó un socio
                }
            }
        }

        registrarLogReserva(
            $pdo,
            $id_reserva,
            'creada',
            $descripcion,
            $_SESSION['recinto_usuario'] ?? 'Admin',
            null,
            $monto_final
        );
    }

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