<?php
// api/gestion_reservas.php
// === LIMPIEZA DE OUTPUT PARA EVITAR JSON ROTO ===
if (ob_get_level() > 0) { ob_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Manejar errores de PHP para que no rompan el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);  // ❌ No mostrar errores en pantalla
ini_set('log_errors', 1);      // ✅ Loguear errores en archivo
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';

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

    // Logging para debug (remover en producción)
    error_log("🔍 [API] Action: " . ($action ?? 'NULL'));
    error_log("🔍 [API] POST keys: " . implode(', ', array_keys($_POST)));
    error_log("🔍 [API] Session: id_recinto=" . ($_SESSION['id_recinto'] ?? 'NULL'));

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
// === FUNCION UNIFICADA: Crear reserva manual (socio existente o nuevo) ===
function crearReservaManualUnificada($pdo, $data) {
    $id_cancha = isset($data['id_cancha']) ? (int)$data['id_cancha'] : 0;
    $fecha = isset($data['fecha']) ? $data['fecha'] : '';
    $hora_inicio = isset($data['hora_inicio']) ? $data['hora_inicio'] : '';
    $hora_fin = isset($data['hora_fin']) ? $data['hora_fin'] : '';
    $monto_total = isset($data['monto_total']) ? (float)$data['monto_total'] : 0;
    
    // Validaciones basicas
    if (!$id_cancha || !$fecha || !$hora_inicio) {
        throw new Exception('Datos incompletos para crear reserva');
    }
    
    // Verificar cancha pertenece al recinto
    $stmt = $pdo->prepare("SELECT id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt->execute([$id_cancha]);
    if ($stmt->fetchColumn() != $_SESSION['id_recinto']) {
        throw new Exception('Cancha no valida');
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
        // SOCIO EXISTENTE: Obtener datos de la tabla socios
        $stmt_s = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
        $stmt_s->execute([$id_socio]);
        $socio_data = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($socio_data) {
            $nombre_cliente = $socio_data['nombre'] ? $socio_data['nombre'] : '';
            $email_cliente = $socio_data['email'] ? $socio_data['email'] : '';
            $telefono_cliente = $socio_data['celular'] ? $socio_data['celular'] : '';
        }
    } else {
        // NUEVO SOCIO: Crear registro y usar datos del formulario
        $email_nuevo = trim(isset($data['emailNuevoSocio']) ? $data['emailNuevoSocio'] : '');
        $nombre_nuevo = trim(isset($data['nombreNuevoSocio']) ? $data['nombreNuevoSocio'] : 'Nuevo Socio');
        $tel_nuevo = trim(isset($data['telNuevoSocio']) ? $data['telNuevoSocio'] : '');
        
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
        } else {
            // Crear nuevo socio
            $alias = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email_nuevo)[0]));
            $stmt = $pdo->prepare("INSERT INTO socios (email, nombre, alias, celular, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$email_nuevo, $nombre_nuevo, $alias, $tel_nuevo]);
            $id_socio = $pdo->lastInsertId();
            
            $nombre_cliente = $nombre_nuevo;
            $email_cliente = $email_nuevo;
            $telefono_cliente = $tel_nuevo;
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
    
    // === 3. REGISTRAR LOG DE BITACORA ===
    $usuario = isset($_SESSION['recinto_usuario']) ? $_SESSION['recinto_usuario'] : (isset($_SESSION['recinto_rol']) ? $_SESSION['recinto_rol'] : 'Sistema');
    $stmt_log = $pdo->prepare("INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) VALUES (?, ?, 'creada', 'Reserva manual', NOW())");
    $stmt_log->execute([$id_reserva, $usuario]);
    
        // ... (Código anterior de la función se mantiene igual hasta el INSERT de la reserva) ...

    // === 4. EMAILS (SOLO SI ES SOCIO NUEVO) ===
    if (empty($data['id_socio']) && $email_cliente) {
        require_once __DIR__ . '/../includes/reserva_mailer.php';
        require_once __DIR__ . '/../includes/email_helper.php'; // Importamos nuestra plantilla nueva

        // A. Generar Token de Seguridad para crear contraseña
        $token = bin2hex(random_bytes(32));
        $link_registro = "https://" . $_SERVER['HTTP_HOST'] . "/pages/completar_registro.php?token=" . $token;
        
        // Guardar token en la BD
        $stmt_token = $pdo->prepare("UPDATE socios SET registro_token = ? WHERE id_socio = ?");
        $stmt_token->execute([$token, $id_socio]);

        // B. Configurar contenido del correo
        $titulo = "¡Bienvenido a CanchaSport, {$nombre_cliente}! 🎾";
        $mensaje = "<p>Hemos creado tu cuenta para la reserva de hoy.</p>
                    <p>Para gestionar tus reservas, historial y datos, solo necesitas definir una contraseña:</p>";
        $texto_boton = "Crear mi contraseña";
        
        // C. Enviar
        $html = generarEmailHTML($titulo, $mensaje, $texto_boton, $link_registro);

        $mail = new BrevoMailer();
        $mail->setTo($email_cliente, $nombre_cliente)
             ->setSubject("👋 ¡Bienvenido! Completa tu registro en CanchaSport")
             ->setHtmlBody($html)
             ->send();
    }
    
    return ['success' => true, 'id_reserva' => $id_reserva, 'id_socio' => $id_socio];
}
?>