<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    session_start();
    
    // Verificar autenticación
    if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    $id_recinto = $_SESSION['id_recinto'];
    $action = $_GET['action'] ?? 'get_reservas';
    
    switch ($action) {
        case 'get_reservas':
            $rango_dias = (int)($_GET['rango_dias'] ?? 30);
            echo json_encode(getReservasDataOptimizada($pdo, $id_recinto, $rango_dias));
            break;
            
        case 'get_detalle_reserva':
            $id_disponibilidad = (int)($_POST['id_disponibilidad'] ?? 0);
            if (!$id_disponibilidad) {
                throw new Exception('ID de disponibilidad requerido');
            }
            echo json_encode(getDetalleReserva($pdo, $id_disponibilidad, $id_recinto));
            break;
            
        case 'filtrar_reservas':
            $filtros = [
                'deporte' => $_POST['deporte'] ?? null,
                'estado' => $_POST['estado'] ?? null,
                'fecha' => $_POST['fecha'] ?? null,
                'rango_dias' => (int)($_POST['rango_dias'] ?? 30)
            ];
            echo json_encode(getReservasFiltradasOptimizadas($pdo, $id_recinto, $filtros));
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['error' => $e->getMessage()]);
}

function getReservasDataOptimizada($pdo, $id_recinto, $rango_dias = 0) {
    // Por defecto mostrar HOY (rango_dias = 0)
    if ($rango_dias === 30) {
        $rango_dias = 0; // Cambiar a "Hoy" por defecto
    }
    
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = date('Y-m-d', strtotime("+$rango_dias days"));
    
    // Obtener canchas activas del recinto
    $stmt_canchas = $pdo->prepare("
        SELECT 
            id_cancha, nro_cancha, nombre_cancha, id_deporte,
            dias_disponibles, hora_inicio, hora_fin, duracion_bloque
        FROM canchas 
        WHERE id_recinto = ? AND activa = 1
    ");
    $stmt_canchas->execute([$id_recinto]);
    $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($canchas)) {
        return [];
    }
    
    // Generar disponibilidad dinámica para todas las canchas
    $todas_disponibilidades = [];
    foreach ($canchas as $cancha) {
        $disponibilidades = generarDisponibilidadCancha($cancha, $fecha_inicio, $fecha_fin);
        $todas_disponibilidades = array_merge($todas_disponibilidades, $disponibilidades);
    }
    
    // Obtener reservas reales en el período (solo las que existen en la tabla)
    $stmt_reservas = $pdo->prepare("
        SELECT 
            dc.id_disponibilidad,
            dc.id_cancha,
            dc.fecha,
            dc.hora_inicio,
            dc.hora_fin,
            dc.estado as estado_disponibilidad,
            r.id_reserva,
            r.estado as estado_reserva,
            r.estado_pago,
            r.monto_total,
            r.tipo_reserva,
            r.id_convenio,
            r.notas,
            cl.nombre as nombre_club,
            s.alias as nombre_responsable,
            r.telefono_cliente,
            r.email_cliente
        FROM disponibilidad_canchas dc
        LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
        LEFT JOIN clubs cl ON r.id_club = cl.id_club
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE dc.fecha BETWEEN ? AND ? 
        AND dc.id_cancha IN (
            SELECT id_cancha FROM canchas WHERE id_recinto = ?
        )
    ");
    $stmt_reservas->execute([$fecha_inicio, $fecha_fin, $id_recinto]);
    $reservas_reales = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear mapa de reservas reales para fusión rápida
    $reservas_map = [];
    foreach ($reservas_reales as $reserva) {
        $key = $reserva['id_cancha'] . '_' . $reserva['fecha'] . '_' . $reserva['hora_inicio'];
        $reservas_map[$key] = $reserva;
    }
    
    // Fusionar disponibilidad dinámica con reservas reales
    $resultado_final = [];
    foreach ($todas_disponibilidades as $disp) {
        $key = $disp['id_cancha'] . '_' . $disp['fecha'] . '_' . $disp['hora_inicio'];
        
        if (isset($reservas_map[$key])) {
            // Existe una reserva real, usar sus datos
            $resultado_final[] = array_merge($disp, $reservas_map[$key]);
        } else {
            // Disponibilidad normal
            $resultado_final[] = $disp;
        }
    }
    
    // Ordenar por deporte, fecha y hora
    usort($resultado_final, function($a, $b) {
        if ($a['id_deporte'] != $b['id_deporte']) {
            return strcmp($a['id_deporte'], $b['id_deporte']);
        }
        if ($a['fecha'] != $b['fecha']) {
            return strcmp($a['fecha'], $b['fecha']);
        }
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
    
    return $resultado_final;
}

function generarDisponibilidadCancha($cancha, $fecha_inicio, $fecha_fin) {
    $disponibilidades = [];
    $current_date = new DateTime($fecha_inicio);
    $end_date = new DateTime($fecha_fin);
    
    // Parsear días disponibles
    $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
    if (!is_array($dias_disponibles)) {
        $dias_disponibles = [];
    }
    
    $dias_map = [
        1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
        4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'
    ];
    
    while ($current_date <= $end_date) {
        $dia_semana = $dias_map[(int)$current_date->format('N')];
        
        if (in_array($dia_semana, $dias_disponibles)) {
            // Reemplaza esta sección en generarDisponibilidadCancha():
            $hora_inicio_str = $cancha['hora_inicio'];
            $hora_fin_str = $cancha['hora_fin'];

            // Asegurar formato correcto HH:MM:SS
            if (strlen($hora_inicio_str) == 5) {
                $hora_inicio_str .= ':00';
            }
            if (strlen($hora_fin_str) == 5) {
                $hora_fin_str .= ':00';
            }

            $duracion_minutos = (int)$cancha['duracion_bloque'];
            $duracion_segundos = $duracion_minutos * 60;

            // Usar DateTime para manejar horas correctamente
            $fecha_base = new DateTime('1970-01-01');
            $hora_inicio_dt = clone $fecha_base;
            $hora_inicio_dt->setTime(
                (int)substr($hora_inicio_str, 0, 2),
                (int)substr($hora_inicio_str, 3, 2),
                (int)substr($hora_inicio_str, 6, 2)
            );
            $hora_fin_dt = clone $fecha_base;
            $hora_fin_dt->setTime(
                (int)substr($hora_fin_str, 0, 2),
                (int)substr($hora_fin_str, 3, 2),
                (int)substr($hora_fin_str, 6, 2)
            );

            $current_hora_dt = clone $hora_inicio_dt;
            while ($current_hora_dt < $hora_fin_dt) {
                $hora_inicio_bloque = $current_hora_dt->format('H:i:s');
                $hora_fin_bloque_dt = clone $current_hora_dt;
                $hora_fin_bloque_dt->add(new DateInterval('PT' . $duracion_minutos . 'M'));
                
                if ($hora_fin_bloque_dt <= $hora_fin_dt) {
                    $hora_fin_bloque = $hora_fin_bloque_dt->format('H:i:s');
                    
                    $disponibilidades[] = [
                        'id_cancha' => $cancha['id_cancha'],
                        'nro_cancha' => $cancha['nro_cancha'],
                        'nombre_cancha' => $cancha['nombre_cancha'],
                        'id_deporte' => $cancha['id_deporte'],
                        'fecha' => $current_date->format('Y-m-d'),
                        'hora_inicio' => $hora_inicio_bloque,
                        'hora_fin' => $hora_fin_bloque,
                        'estado_disponibilidad' => 'disponible',
                        'id_disponibilidad' => null,
                        'id_reserva' => null,
                        'estado_reserva' => null,
                        'estado_pago' => null,
                        'monto_total' => null,
                        'nombre_club' => null,
                        'nombre_responsable' => null,
                        'telefono_cliente' => null,
                        'email_cliente' => null
                    ];
                }
                
                $current_hora_dt->add(new DateInterval('PT' . $duracion_minutos . 'M'));
            }
        }
        
        $current_date->modify('+1 day');
    }
    
    return $disponibilidades;
}

function getReservasFiltradasOptimizadas($pdo, $id_recinto, $filtros) {
    // Primero obtener todos los datos
    $rango_dias = 30;
    if ($filtros['fecha'] === 'hoy') {
        $rango_dias = 0;
    } elseif ($filtros['fecha'] === 'mañana') {
        $rango_dias = 1;
    } elseif ($filtros['fecha'] === 'semana') {
        $rango_dias = 7;
    }
    
    $todos_datos = getReservasDataOptimizada($pdo, $id_recinto, $rango_dias);
    
    // Aplicar filtros en PHP (más eficiente que en SQL con generación dinámica)
    $datos_filtrados = [];
    foreach ($todos_datos as $dato) {
        // Filtro por deporte
        if ($filtros['deporte'] && $dato['id_deporte'] !== $filtros['deporte']) {
            continue;
        }
        
        // Filtro por estado
        if ($filtros['estado']) {
            $estado_actual = $dato['estado_disponibilidad'] ?? 'disponible';
            if ($estado_actual !== $filtros['estado']) {
                continue;
            }
        }
        
        // Filtro por fecha específica
        if ($filtros['fecha'] === 'hoy') {
            if ($dato['fecha'] !== date('Y-m-d')) {
                continue;
            }
        } elseif ($filtros['fecha'] === 'mañana') {
            if ($dato['fecha'] !== date('Y-m-d', strtotime('+1 day'))) {
                continue;
            }
        }
        
        $datos_filtrados[] = $dato;
    }
    
    return $datos_filtrados;
}

function getDetalleReserva($pdo, $id_disponibilidad, $id_recinto) {
    $stmt = $pdo->prepare("
        SELECT 
            dc.id_disponibilidad,
            c.nro_cancha,
            c.nombre_cancha,
            c.id_deporte,
            dc.fecha,
            dc.hora_inicio,
            dc.hora_fin,
            dc.estado as estado_disponibilidad,
            r.id_reserva,
            r.estado as estado_reserva,
            r.estado_pago,
            r.monto_total,
            r.tipo_reserva,
            r.id_convenio,
            r.notas,
            cl.nombre as nombre_club,
            cl.logo as logo_club,
            s.alias as nombre_responsable,
            s.email as email_responsable,
            r.telefono_cliente,
            r.email_cliente,
            r.created_at as fecha_reserva
        FROM disponibilidad_canchas dc
        JOIN canchas c ON dc.id_cancha = c.id_cancha
        LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
        LEFT JOIN clubs cl ON r.id_club = cl.id_club
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE dc.id_disponibilidad = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_disponibilidad, $id_recinto]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$detalle) {
        throw new Exception('Reserva no encontrada');
    }
    
    return $detalle;
}
?>