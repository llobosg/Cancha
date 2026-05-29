<?php
// api/crear_reserva_recurrente.php
header('Content-Type: application/json; charset=utf-8');
if (ob_get_level() > 0) { ob_clean(); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // ← CLAVE: cargar la clase
require_once __DIR__ . '/../includes/bitacora.php';


if (!isset($_SESSION['id_socio'])) {
    throw new Exception('Acceso no autorizado', 401);
}

try { 
    $id_socio = $_SESSION['id_socio'];
    
    // CORRECCIÓN AQUÍ: Forzar null si está vacío para evitar error de tipo entero
    $raw_club_id = $_POST['club_id'] ?? ($_SESSION['club_id'] ?? '');
    $id_club = !empty($raw_club_id) ? (int)$raw_club_id : null;
    
    $id_cancha = !empty($_POST['id_cancha']) ? (int)$_POST['id_cancha'] : 0;
    $fecha_base = $_POST['fecha_base'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $tipo_patron = $_POST['tipo_patron'] ?? 'simple';
    $fecha_desde = $_POST['fecha_desde'] ?? $fecha_base;
    $fecha_hasta = $_POST['fecha_hasta'] ?? $fecha_base;
    
    $monto_recaudacion = !empty($_POST['monto_recaudacion']) ? (float)$_POST['monto_recaudacion'] : null;
    $jugadores_esperados = !empty($_POST['jugadores_esperados']) ? (int)$_POST['jugadores_esperados'] : null;

    if (!$id_cancha || !$fecha_base || !$hora_inicio || !$hora_fin) {
        throw new Exception('Datos incompletos');
    }
    
    // Obtener datos del socio (sin depender de id_club)
    $stmt_socio = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
    $stmt_socio->execute([$id_socio]);
    $socio = $stmt_socio->fetch();
    
    if (!$socio) {
        throw new Exception('Socio no encontrado');
    }
    
    $stmt_cancha = $pdo->prepare("SELECT valor_arriendo, id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt_cancha->execute([$id_cancha]);
    $cancha = $stmt_cancha->fetch();
    
    if (!$cancha) {
        throw new Exception('Cancha no encontrada');
    }
    
    // Generar lista de fechas según el patrón
    $fechas_reservar = [];
    
    if ($tipo_patron === 'simple') {
        $fechas_reservar = [$fecha_base];
        $tipo_reserva = 'spot';
        $tipo_arriendo = 'spot';
    } else {
        $fechas_reservar = generarFechasPatron($tipo_patron, $fecha_desde, $fecha_hasta, $fecha_base);
        $tipo_reserva = $tipo_patron;
        $tipo_arriendo = $tipo_patron;
    }
    
    // Validar disponibilidad para todas las fechas
    $conflictos = validarDisponibilidad($pdo, $id_cancha, $fechas_reservar, $hora_inicio, $hora_fin);
    
    if (!empty($conflictos)) {
        throw new Exception('Algunas fechas no están disponibles: ' . implode(', ', $conflictos));
    }
    
    // ✅ PASAMOS EL MONTO ENVIADO DESDE EL FRONTEND COMO ÚLTIMO PARÁMETRO
    $monto_enviado_frontend = isset($_POST['monto_total']) ? (float)$_POST['monto_total'] : 0;

    $reservas_creadas = crearReservasReales(
        $pdo, 
        $id_socio, 
        $id_club, 
        $id_cancha, 
        $socio, 
        $cancha, 
        $fechas_reservar, 
        $hora_inicio, 
        $hora_fin, 
        $tipo_reserva, 
        $tipo_arriendo,
        $monto_recaudacion, 
        $jugadores_esperados,
        $monto_enviado_frontend // <--- NUEVO PARÁMETRO
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Reservas creadas exitosamente',
        'total_reservas' => count($reservas_creadas),
        'reservas' => $reservas_creadas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generarFechasPatron($tipo, $desde, $hasta, $fecha_base) {
    $fechas = [];
    $fecha_actual = new DateTime($desde);
    $fecha_fin = new DateTime($hasta);
    $dia_base = (int)(new DateTime($fecha_base))->format('N'); // 1=lunes, 7=domingo
    
    // Asegurar que la fecha_base esté en el rango
    $fecha_base_obj = new DateTime($fecha_base);
    if ($fecha_base_obj >= $fecha_actual && $fecha_base_obj <= $fecha_fin) {
        if ($tipo === 'semanal' && (int)$fecha_base_obj->format('N') === $dia_base) {
            $fechas[] = $fecha_base_obj->format('Y-m-d');
        } elseif ($tipo !== 'semanal') {
            $fechas[] = $fecha_base_obj->format('Y-m-d');
        }
    }
    
    if ($tipo === 'semanal') {
        while ((int)$fecha_actual->format('N') !== $dia_base && $fecha_actual <= $fecha_fin) {
            $fecha_actual->modify('+1 day');
        }
        while ($fecha_actual <= $fecha_fin) {
            if ((int)$fecha_actual->format('N') === $dia_base) {
                $fechas[] = $fecha_actual->format('Y-m-d');
            }
            $fecha_actual->modify('+1 day');
        }
    } elseif ($tipo === 'quincenal') {
        $fecha_actual = new DateTime($desde);
        while ($fecha_actual <= $fecha_fin) {
            $fechas[] = $fecha_actual->format('Y-m-d');
            $fecha_actual->modify('+15 days');
        }
    } elseif ($tipo === 'mensual') {
        $fecha_actual = new DateTime($desde);
        while ($fecha_actual <= $fecha_fin) {
            $fechas[] = $fecha_actual->format('Y-m-d');
            $fecha_actual->modify('+1 month');
        }
    }
    
    $fechas = array_unique($fechas);
    sort($fechas);
    return $fechas;
}

function validarDisponibilidad($pdo, $id_cancha, $fechas, $hora_inicio, $hora_fin) {
    $conflictos = [];
    foreach ($fechas as $fecha) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as ocupado 
            FROM disponibilidad_canchas 
            WHERE id_cancha = ? 
            AND fecha = ? 
            AND hora_inicio = ? 
            AND estado != 'disponible'
        ");
        $stmt->execute([$id_cancha, $fecha, $hora_inicio]);
        if ($stmt->fetch()['ocupado'] > 0) {
            $conflictos[] = $fecha;
        }
    }
    return $conflictos;
}

function crearReservasReales($pdo, $id_socio, $id_club, $id_cancha, $socio, $cancha, 
                           $fechas, $hora_inicio, $hora_fin, $tipo_reserva, $tipo_arriendo,
                           $monto_recaudacion = null, $jugadores_esperados = null, $monto_enviado = 0) {
    
    require_once __DIR__ . '/../includes/bitacora.php'; // Asegurar log
    
    $reservas = [];
    
    // ✅ CORRECCIÓN CRÍTICA: Usar el monto enviado por el frontend si existe.
    // Si viene 0 o vacío, usamos el valor base de la cancha como fallback.
    $valor_final = ($monto_enviado > 0) ? (float)$monto_enviado : (float)$cancha['valor_arriendo'];

    foreach ($fechas as $fecha) {
        $codigo_reserva = strtoupper(substr(uniqid(), -8));
        
        $stmt = $pdo->prepare("
            INSERT INTO reservas (
                codigo_reserva, id_cancha, id_club, id_socio,
                nombre_cliente, email_cliente, telefono_cliente,
                fecha, hora_inicio, hora_fin,
                tipo_reserva, tipo_arriendo,
                monto_total, estado, estado_pago,
                monto_recaudacion, jugadores_esperados
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Guardamos $valor_final (ej: $80.000) en monto_total
        $stmt->execute([
            $codigo_reserva,
            $id_cancha,
            $id_club,
            $id_socio,
            $socio['nombre'],
            $socio['email'],
            $socio['celular'],
            $fecha,
            $hora_inicio,
            $hora_fin,
            $tipo_reserva,
            $tipo_arriendo,
            $valor_final, // <--- AQUÍ ESTABA EL ERROR ANTES
            'confirmada',
            'pendiente',
            $monto_recaudacion,
            $jugadores_esperados
        ]);
        
        $id_nueva_reserva = $pdo->lastInsertId();

        // === REGISTRAR EN BITÁCORA CON EL MONTO CORRECTO Y HORA CHILE ===
        if (function_exists('registrarLogReserva')) {
            registrarLogReserva(
                $pdo,
                $id_nueva_reserva,
                'creada',
                "Reserva creada automáticamente (Patrón: {$tipo_reserva})",
                $socio['nombre'],
                null,
                $valor_final // Logueamos el monto real guardado ($80.000)
            );
        }
        
        $pdo->prepare("
            UPDATE disponibilidad_canchas 
            SET estado = 'reservada', id_reserva = ?
            WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ?
        ")->execute([$id_nueva_reserva, $id_cancha, $fecha, $hora_inicio]);
        
        $reservas[] = [
            'id_reserva' => $id_nueva_reserva,
            'codigo_reserva' => $codigo_reserva,
            'fecha' => $fecha,
            'hora_inicio' => $hora_inicio
        ];
    }

    // Envío de correos (tu lógica existente)
    require_once __DIR__ . '/../includes/reserva_mailer.php';
    if (class_exists('BrevoMailer') && method_exists('BrevoMailer', 'enviarConfirmacion')) {
        BrevoMailer::enviarConfirmacion($pdo, $reservas[count($reservas)-1]['id_reserva']);
    }
    
    return $reservas;
}
?>