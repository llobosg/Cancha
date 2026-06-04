<?php
// api/gestion_reservas.php
header('Content-Type: application/json; charset=utf-8');
// Limpieza extrema de buffer para evitar JSON roto por warnings/espacios
while (ob_get_level()) { ob_end_clean(); }

error_log("🚀 [API] Inicio gestion_reservas.php");

require_once __DIR__ . '/../includes/config.php';

// Cargar bitácora SOLO si existe el archivo
if (file_exists(__DIR__ . '/../includes/bitacora.php')) {
    require_once __DIR__ . '/../includes/bitacora.php';
    error_log("✅ [API] Bitácora cargada");
} else {
    error_log("⚠️ [API] Archivo bitacora.php NO encontrado");
}

try {
    // 1. Verificar autenticación básica
    if (!isset($_SESSION['id_recinto'])) {
        error_log("❌ [API] Sesión no iniciada");
        throw new Exception('Sesión no iniciada', 401);
    }

    // 2. Verificar Rol
    $rol_actual = $_SESSION['recinto_rol'] ?? '';
    $roles_permitidos = ['admin', 'asistente'];
    
    if (!in_array($rol_actual, $roles_permitidos)) {
        error_log("❌ [API] Acceso denegado. Rol: '$rol_actual'");
        throw new Exception('Acceso no autorizado: Rol inválido', 401);
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    error_log("📥 [API] Acción recibida: $action | Usuario: " . ($_SESSION['recinto_usuario'] ?? 'Desconocido'));

    switch ($action) {
        case 'procesar_pago':
            echo json_encode(procesarPagoReserva($pdo, $_POST));
            break;
            
        case 'procesar_pago_parcial':
            echo json_encode(procesarPagoParcial($pdo, $_POST));
            break;
            
        case 'crear_manual':
            try {
                error_log("🔍 [API] Iniciando crear_manual");
                
                // 1. Extraer datos básicos
                $id_cancha = (int)($_POST['id_cancha'] ?? 0);
                $fecha = $_POST['fecha'] ?? '';
                $hora_inicio = $_POST['hora_inicio'] ?? '';
                $hora_fin = $_POST['hora_fin'] ?? '';
                $monto_total = floatval($_POST['monto_total'] ?? 0);
                
                error_log("📝 [API] Datos extraídos: Cancha=$id_cancha, Fecha=$fecha, Monto=$monto_total");
                
                // 2. Lógica de Socio / Convenio
                $id_socio = isset($_POST['id_socio']) && !empty($_POST['id_socio']) ? (int)$_POST['id_socio'] : null;
                $id_convenio = isset($_POST['id_convenio']) && !empty($_POST['id_convenio']) ? (int)$_POST['id_convenio'] : null;
                
                $nombre_cliente = $_POST['nombre_cliente'] ?? null;
                $email_cliente = $_POST['email_cliente'] ?? null;
                $telefono_cliente = $_POST['telefono_cliente'] ?? null;
                
                $es_socio_nuevo = false; // Flag para controlar emails

                // === A. VALIDAR SOCIO O CONVENIO ===
                if (!$id_socio) {
                    if (!$id_convenio) {
                        throw new Exception("Debe seleccionar un socio o aplicar un Convenio.");
                    }
                    // Si hay convenio, obtener datos
                    $stmt_conv = $pdo->prepare("SELECT nombre_empresa, contacto_email, contacto_telefono FROM convenios WHERE id_convenio = ? AND id_recinto = ?");
                    $stmt_conv->execute([$id_convenio, $_SESSION['id_recinto']]);
                    $conv = $stmt_conv->fetch();
                    if ($conv) {
                        $nombre_cliente = $conv['nombre_empresa'];
                        $email_cliente = $email_cliente ?: $conv['contacto_email'];
                        $telefono_cliente = $telefono_cliente ?: $conv['contacto_telefono'];
                    } else {
                        throw new Exception("Convenio no válido.");
                    }
                } else {
                    // Si hay socio, obtener sus datos completos
                    // ✅ CORRECCIÓN: Usar password_hash en lugar de password
                    $stmt_s = $pdo->prepare("SELECT nombre, email, celular, password_hash FROM socios WHERE id_socio = ?");
                    $stmt_s->execute([$id_socio]);
                    $s = $stmt_s->fetch();
                    if ($s) {
                        $nombre_cliente = $s['nombre'];
                        $email_cliente = $email_cliente ?: $s['email'];
                        $telefono_cliente = $telefono_cliente ?: $s['celular'];
                        
                        // ✅ DETECTAR SI ES NUEVO: Si no tiene password_hash, es recién creado
                        if (empty($s['password_hash'])) {
                            $es_socio_nuevo = true;
                            error_log("🆕 [API] Socio nuevo detectado (sin password_hash): ID $id_socio");
                        }
                    }
                }

                // 3. Obtener ID Club del Socio
                // ✅ CORRECCIÓN: Usar tabla intermedia socio_club
                $id_club_reserva = null;
                if ($id_socio) {
                    try {
                        $stmt_club = $pdo->prepare("SELECT id_club FROM socio_club WHERE id_socio = ? AND estado = 'activo' LIMIT 1");
                        $stmt_club->execute([$id_socio]);
                        $socio_club = $stmt_club->fetch(PDO::FETCH_ASSOC);
                        if ($socio_club) {
                            $id_club_reserva = $socio_club['id_club'];
                        }
                    } catch (Exception $e) {
                        error_log("⚠️ [API] Error obteniendo club del socio: " . $e->getMessage());
                    }
                }

                // 4. Insertar Reserva
                error_log("💾 [API] Intentando insertar reserva...");
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
                error_log("✅ [API] Reserva creada con ID: $id_reserva");

                // 5. Registrar Bitácora (SOLO si la función existe)
                if (function_exists('registrarLogReserva')) {
                    try {
                        registrarLogReserva(
                            $pdo,
                            $id_reserva,
                            'creada',
                            "Reserva manual creada por Admin",
                            $_SESSION['recinto_usuario'] ?? 'Admin',
                            null,
                            $monto_total
                        );
                        error_log("📝 [API] Bitácora registrada");
                    } catch (Exception $logErr) {
                        error_log("⚠️ [API] Error en bitácora (no crítico): " . $logErr->getMessage());
                    }
                } else {
                    error_log("⚠️ [API] Función registrarLogReserva NO existe");
                }

                // 6. Enviar Email de Bienvenida/Activación (SIMPLIFICADO - SIN TOKEN EN BD)
                if ($es_socio_nuevo && $email_cliente) {
                    try {
                        error_log("📧 [API] Enviando email de bienvenida a: $email_cliente");
                        require_once __DIR__ . '/../includes/brevo_mailer.php';
                        
                        $link_login = "https://canchasport.com/index.php";
                       
                        $mail = new BrevoMailer();
                        $mail->setTo($email_cliente, $nombre_cliente);
                        $mail->setSubject("🎉 ¡Bienvenido a CanchaSport! Tu cuenta ha sido creada");
                        
                        $htmlBody = "
                            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #071289;'>¡Hola {$nombre_cliente}!</h2>
                                <p>Te hemos registrado como socio en <strong>CanchaSport</strong>.</p>
                                <p>Tu reserva ha sido confirmada exitosamente.</p>
                                <p>Para acceder a la app y gestionar tus futuras reservas, puedes iniciar sesión con tu email.</p>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='{$link_login}' 
                                    style='background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                                        🚀 Ir a CanchaSport
                                    </a>
                                </div>
                                <p style='font-size: 0.9rem; color: #666;'>Si es tu primera vez, usa la opción '¿Olvidaste tu contraseña?' para establecer una clave segura.</p>
                            </div>
                        ";
                        
                        $mail->setHtmlBody($htmlBody);
                        $mail->send();
                        error_log("✅ [API] Email bienvenida enviado");
                        
                    } catch (Exception $e) {
                        error_log("❌ [API] Error email bienvenida: " . $e->getMessage());
                        // No lanzamos excepción para no romper la reserva
                    }
                }

                // 7. Enviar Email de Confirmación de Reserva (A TODOS los socios)
                if ($id_socio && $email_cliente) {
                    try {
                        error_log("📧 [API] Enviando email de confirmación de reserva");
                        require_once __DIR__ . '/../includes/brevo_mailer.php';
                        // Asumiendo que tienes esta función en tu clase BrevoMailer
                        if (method_exists('BrevoMailer', 'enviarConfirmacion')) {
                            BrevoMailer::enviarConfirmacion($pdo, $id_reserva);
                            error_log("✅ [API] Email confirmación reserva enviado");
                        } else {
                            error_log("⚠️ [API] Método enviarConfirmacion NO existe en BrevoMailer");
                        }
                    } catch (Exception $e) {
                        error_log("❌ [API] Error email confirmación: " . $e->getMessage());
                    }
                }
                
                // ✅ LIMPIAR BUFFER ANTES DE RESPONDER
                ob_clean();
                error_log("🏁 [API] Respondiendo éxito JSON");
                echo json_encode(['success' => true, 'id_reserva' => $id_reserva]);
                exit; // Importante salir aquí
                
            } catch (Exception $e) {
                ob_clean();
                error_log("❌ [API] Excepción en crear_manual: " . $e->getMessage());
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
} catch (Exception $e) {
    ob_clean();
    error_log("❌ [API] Excepción Global: " . $e->getMessage());
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// ============================================================================
// FUNCIONES AUXILIARES (procesarPagoReserva, procesarPagoParcial, etc.)
// ============================================================================

function procesarPagoReserva($pdo, $data) {
    // ... (Tu código existente de procesarPagoReserva) ...
    // Asegúrate de que esta función esté completa y correcta
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
        try {
            registrarLogReserva(
                $pdo,
                $id_reserva,
                ($nuevo_estado_pago === 'pagado') ? 'cobro_total' : 'cobro_parcial',
                "Pago registrado vía $metodo_pago. Monto: $" . number_format($monto_total, 0, ',', '.'),
                $_SESSION['recinto_usuario'] ?? 'Admin',
                $reserva['monto_recaudacion'],
                $nuevo_monto_recaudado
            );
        } catch (Exception $e) {
            error_log("Error en log de pago: " . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'estado_nuevo' => $nuevo_estado_pago
    ];
}

function procesarPagoParcial($pdo, $data) {
    // ... (Tu código existente de procesarPagoParcial) ...
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
        try {
            registrarLogReserva(
                $pdo,
                $id_reserva,
                'cobro_parcial',
                "Abono parcial: $" . number_format($monto_pagado, 0, ',', '.'),
                $_SESSION['recinto_usuario'] ?? 'Admin',
                $monto_recaudado_actual,
                $nuevo_monto_recaudado
            );
        } catch (Exception $e) {
            error_log("Error en log de pago parcial: " . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'message' => 'Pago registrado correctamente. Estado: ' . $nuevo_estado_pago,
        'estado_nuevo' => $nuevo_estado_pago,
        'monto_recaudado' => $nuevo_monto_recaudado
    ];
}
?>