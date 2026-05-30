<?php
// api/crear_reserva_recurrente.php

header('Content-Type: application/json; charset=utf-8');

// 🔥 BUFFER PARA EVITAR JSON CORRUPTO
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php';
require_once __DIR__ . '/../includes/bitacora.php';

try {

    if (!isset($_SESSION['id_socio'])) {
        throw new Exception('Acceso no autorizado');
    }

    $id_socio = $_SESSION['id_socio'];

    $raw_club_id = $_POST['club_id'] ?? ($_SESSION['club_id'] ?? '');
    $id_club = !empty($raw_club_id) ? (int)$raw_club_id : null;

    $id_cancha = !empty($_POST['id_cancha']) ? (int)$_POST['id_cancha'] : 0;
    $fecha_base = $_POST['fecha_base'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $duracion_minutos = !empty($_POST['duracion_minutos']) ? (int)$_POST['duracion_minutos'] : 60;
    
    $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
    if (!$hora_inicio_obj) {
        throw new Exception('Hora inicio inválida');
    }
    $hora_fin_obj = clone $hora_inicio_obj;
    $hora_fin_obj->modify("+{$duracion_minutos} minutes");

    $hora_fin = $hora_fin_obj->format('H:i');
    $tipo_patron = $_POST['tipo_patron'] ?? 'simple';
    $fecha_desde = $_POST['fecha_desde'] ?? $fecha_base;
    $fecha_hasta = $_POST['fecha_hasta'] ?? $fecha_base;

    $monto_recaudacion = !empty($_POST['monto_recaudacion']) ? (float)$_POST['monto_recaudacion'] : null;
    $jugadores_esperados = !empty($_POST['jugadores_esperados']) ? (int)$_POST['jugadores_esperados'] : null;

    error_log("[DEBUG HORAS] Inicio: $hora_inicio | Duración: $duracion_minutos | Fin: $hora_fin");

    if (!$id_cancha || !$fecha_base || !$hora_inicio || !$hora_fin) {
        throw new Exception('Datos incompletos');
    }

    // 🔥 NORMALIZAR MONTO (evita "80.000")
    $monto_enviado_frontend = 0;
    if (isset($_POST['monto_total'])) {
        $monto_limpio = str_replace(['.', ','], ['', '.'], $_POST['monto_total']);
        $monto_enviado_frontend = (float)$monto_limpio;
    }

    error_log("[RESERVA DEBUG] Monto recibido frontend: " . ($_POST['monto_total'] ?? 'NULL') . " | Normalizado: " . $monto_enviado_frontend);

    // Obtener socio
    $stmt_socio = $pdo->prepare("SELECT nombre, email, celular FROM socios WHERE id_socio = ?");
    $stmt_socio->execute([$id_socio]);
    $socio = $stmt_socio->fetch();

    if (!$socio) {
        throw new Exception('Socio no encontrado');
    }

    // Obtener cancha
    $stmt_cancha = $pdo->prepare("SELECT valor_arriendo, id_recinto FROM canchas WHERE id_cancha = ?");
    $stmt_cancha->execute([$id_cancha]);
    $cancha = $stmt_cancha->fetch();

    if (!$cancha) {
        throw new Exception('Cancha no encontrada');
    }

    // Generar fechas
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

    // Validar disponibilidad
    $conflictos = validarDisponibilidad($pdo, $id_cancha, $fechas_reservar, $hora_inicio);

    if (!empty($conflictos)) {
        throw new Exception('Fechas no disponibles: ' . implode(', ', $conflictos));
    }

    // Crear reservas
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
        $monto_enviado_frontend
    );

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Reservas creadas exitosamente',
        'total_reservas' => count($reservas_creadas),
        'reservas' => $reservas_creadas
    ]);
    exit;

} catch (Exception $e) {

    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

/* ===================================================== */

function generarFechasPatron($tipo, $desde, $hasta, $fecha_base) {
    $fechas = [];
    $fecha_actual = new DateTime($desde);
    $fecha_fin = new DateTime($hasta);
    $dia_base = (int)(new DateTime($fecha_base))->format('N');

    if ($tipo === 'simple') {
        return [$fecha_base];
    }

    while ($fecha_actual <= $fecha_fin) {
        if ($tipo === 'semanal' && (int)$fecha_actual->format('N') === $dia_base) {
            $fechas[] = $fecha_actual->format('Y-m-d');
        } elseif ($tipo === 'quincenal') {
            $fechas[] = $fecha_actual->format('Y-m-d');
            $fecha_actual->modify('+15 days');
            continue;
        } elseif ($tipo === 'mensual') {
            $fechas[] = $fecha_actual->format('Y-m-d');
            $fecha_actual->modify('+1 month');
            continue;
        }
        $fecha_actual->modify('+1 day');
    }

    return array_unique($fechas);
}

/* ===================================================== */

function validarDisponibilidad($pdo, $id_cancha, $fechas, $hora_inicio) {
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

/* ===================================================== */

function crearReservasReales(
    $pdo, $id_socio, $id_club, $id_cancha, $socio, $cancha,
    $fechas, $hora_inicio, $hora_fin, $tipo_reserva, $tipo_arriendo,
    $monto_recaudacion, $jugadores_esperados, $monto_enviado
) {

    $reservas = [];

    // 🔥 CALCULAR MONTO REAL (blindado)
    $duracion_min = (strtotime($hora_fin) - strtotime($hora_inicio)) / 60;
    $bloques = max(1, $duracion_min / 60);

    $valor_base = (float)$cancha['valor_arriendo'];
    $valor_calculado = $valor_base * $bloques;

    $valor_final = ($monto_enviado > 0) ? $monto_enviado : $valor_calculado;

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
            $valor_final,
            'confirmada',
            'pendiente',
            $monto_recaudacion,
            $jugadores_esperados
        ]);

        $id_reserva = $pdo->lastInsertId();

        // 🔥 BITÁCORA CORRECTA
        registrarLogReserva(
            $pdo,
            $id_reserva,
            'creada',
            "Reserva creada automáticamente (Patrón: {$tipo_reserva})",
            $socio['nombre'],
            null,
            $valor_final
        );

        // Actualizar disponibilidad
        $pdo->prepare("
            UPDATE disponibilidad_canchas 
            SET estado = 'reservada', id_reserva = ?
            WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ?
        ")->execute([$id_reserva, $id_cancha, $fecha, $hora_inicio]);

        $reservas[] = [
            'id_reserva' => $id_reserva,
            'codigo_reserva' => $codigo_reserva,
            'fecha' => $fecha,
            'hora_inicio' => $hora_inicio
        ];
    }

    // 🔥 ENVÍO DE MAILS ROBUSTO
    if (class_exists('BrevoMailer') && method_exists('BrevoMailer', 'enviarConfirmacion')) {
        foreach ($reservas as $r) {
            try {
                BrevoMailer::enviarConfirmacion($pdo, $r['id_reserva']);
            } catch (Exception $e) {
                error_log("[MAIL ERROR] " . $e->getMessage());
            }
        }
    }

    return $reservas;
}