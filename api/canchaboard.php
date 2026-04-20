<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

try {
    session_start();
    
    // 1. Verificar autenticación
    if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
        throw new Exception('Acceso no autorizado', 401);
    }
    
    // 2. DEFINIR ID_RECINTO AQUÍ (Crítico)
    $id_recinto = (int)$_SESSION['id_recinto'];
    
    // 3. Determinar acción (POST o GET)
    $action = $_POST['action'] ?? $_GET['action'] ?? 'get_reservas';
    
    error_log("🎯 [API] Acción: $action | Recinto ID: $id_recinto");

    switch ($action) {
        case 'get_reservas':
            // Leer rango_dias de GET o POST
            $rango_dias = (int)($_GET['rango_dias'] ?? $_POST['rango_dias'] ?? 0);
            error_log("📅 [API] Cargando reservas. Rango: $rango_dias días");
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
            error_log("🔍 [API] Filtrando con: " . print_r($filtros, true));
            echo json_encode(getReservasFiltradasOptimizadas($pdo, $id_recinto, $filtros));
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['error' => $e->getMessage()]);
    error_log("❌ [API] Error: " . $e->getMessage());
}

// === FUNCIONES ===

function getReservasDataOptimizada($pdo, $id_recinto, $rango_dias = 0) {
    // Calcular fechas
    $fecha_inicio = date('Y-m-d');
    $fecha_fin = date('Y-m-d', strtotime("+$rango_dias days"));
    
    error_log("📊 [DB] Buscando desde $fecha_inicio hasta $fecha_fin para recinto $id_recinto");

    // Obtener canchas activas
    $stmt_canchas = $pdo->prepare("
        SELECT id_cancha, nro_cancha, nombre_cancha, id_deporte,
               dias_disponibles, hora_inicio, hora_fin, duracion_bloque
        FROM canchas WHERE id_recinto = ? AND activa = 1
    ");
    $stmt_canchas->execute([$id_recinto]);
    $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($canchas)) {
        error_log("⚠️ [DB] No hay canchas activas para este recinto");
        return [];
    }
    
    // Generar disponibilidades dinámicas
    $todas_disponibilidades = [];
    foreach ($canchas as $cancha) {
        $disponibilidades = generarDisponibilidadCancha($cancha, $fecha_inicio, $fecha_fin);
        $todas_disponibilidades = array_merge($todas_disponibilidades, $disponibilidades);
    }
    
    // Obtener reservas reales
    $stmt_reservas = $pdo->prepare("
        SELECT dc.id_disponibilidad, dc.id_cancha, dc.fecha, dc.hora_inicio, dc.hora_fin,
               dc.estado as estado_disponibilidad,
               r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total,
               r.tipo_reserva, r.id_convenio, r.notas,
               cl.nombre as nombre_club, s.alias as nombre_responsable,
               r.telefono_cliente, r.email_cliente
        FROM disponibilidad_canchas dc
        LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
        LEFT JOIN clubs cl ON r.id_club = cl.id_club
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE dc.fecha BETWEEN ? AND ? 
        AND dc.id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)
    ");
    $stmt_reservas->execute([$fecha_inicio, $fecha_fin, $id_recinto]);
    $reservas_reales = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
    
    // Fusionar
    $reservas_map = [];
    foreach ($reservas_reales as $reserva) {
        $key = $reserva['id_cancha'].'_'.$reserva['fecha'].'_'.$reserva['hora_inicio'];
        $reservas_map[$key] = $reserva;
    }
    
    $resultado_final = [];
    foreach ($todas_disponibilidades as $disp) {
        $key = $disp['id_cancha'].'_'.$disp['fecha'].'_'.$disp['hora_inicio'];
        $resultado_final[] = isset($reservas_map[$key]) 
            ? array_merge($disp, $reservas_map[$key]) 
            : $disp;
    }
    
    // Ordenar
    usort($resultado_final, function($a, $b) {
        if ($a['id_deporte'] != $b['id_deporte']) return strcmp($a['id_deporte'], $b['id_deporte']);
        if ($a['fecha'] != $b['fecha']) return strcmp($a['fecha'], $b['fecha']);
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
    
    error_log("✅ [DB] Total registros generados: " . count($resultado_final));
    return $resultado_final;
}

function generarDisponibilidadCancha($cancha, $fecha_inicio, $fecha_fin) {
    $disponibilidades = [];
    $current_date = new DateTime($fecha_inicio);
    $end_date = new DateTime($fecha_fin);
    
    $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
    if (!is_array($dias_disponibles)) $dias_disponibles = [];
    
    $dias_map = [1=>'lunes', 2=>'martes', 3=>'miercoles', 4=>'jueves', 5=>'viernes', 6=>'sabado', 7=>'domingo'];
    
    while ($current_date <= $end_date) {
        $dia_semana = $dias_map[(int)$current_date->format('N')];
        
        if (in_array($dia_semana, $dias_disponibles)) {
            $hora_inicio_str = strlen($cancha['hora_inicio']) == 5 ? $cancha['hora_inicio'].':00' : $cancha['hora_inicio'];
            $hora_fin_str = strlen($cancha['hora_fin']) == 5 ? $cancha['hora_fin'].':00' : $cancha['hora_fin'];
            
            $duracion_minutos = (int)$cancha['duracion_bloque'];
            
            $fecha_base = new DateTime('1970-01-01');
            $hora_inicio_dt = clone $fecha_base;
            $hora_inicio_dt->setTime((int)substr($hora_inicio_str,0,2), (int)substr($hora_inicio_str,3,2), (int)substr($hora_inicio_str,6,2));
            
            $hora_fin_dt = clone $fecha_base;
            $hora_fin_dt->setTime((int)substr($hora_fin_str,0,2), (int)substr($hora_fin_str,3,2), (int)substr($hora_fin_str,6,2));
            
            $current_hora_dt = clone $hora_inicio_dt;
            while ($current_hora_dt < $hora_fin_dt) {
                $hora_fin_bloque_dt = clone $current_hora_dt;
                $hora_fin_bloque_dt->add(new DateInterval('PT' . $duracion_minutos . 'M'));
                
                if ($hora_fin_bloque_dt <= $hora_fin_dt) {
                    $disponibilidades[] = [
                        'id_cancha' => $cancha['id_cancha'],
                        'nro_cancha' => $cancha['nro_cancha'],
                        'nombre_cancha' => $cancha['nombre_cancha'],
                        'id_deporte' => $cancha['id_deporte'],
                        'fecha' => $current_date->format('Y-m-d'),
                        'hora_inicio' => $current_hora_dt->format('H:i:s'),
                        'hora_fin' => $hora_fin_bloque_dt->format('H:i:s'),
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
    error_log("🔍 [FILTRO] Iniciando filtrado...");
    
    $fecha_hoy = date('Y-m-d');
    $rango_dias = 30; 
    
    // Lógica de fechas
    if ($filtros['fecha'] === 'hoy') {
        $rango_dias = 0;
    } elseif ($filtros['fecha'] === 'mañana') {
        $rango_dias = 1;
    } elseif ($filtros['fecha'] === 'semana') {
        $rango_dias = 7;
    } elseif ($filtros['fecha'] === 'mes') {
        $rango_dias = 30;
    } else {
        // CASO NUEVO: Si no hay fecha específica o es 'reservas' (todas), ampliamos el rango
        // Podríamos poner 365 días o NULL para todo el historial
        $rango_dias = 365; // Mostramos hasta un año hacia adelante y atrás si es necesario
        // Opcional: Si quieres TODAS sin límite, maneja la fecha_inicio/fin diferente abajo
    }
    
    // Definir fechas base
    // Si queremos ver también las PASADAS, debemos restar días a la fecha_inicio
    $fecha_fin = date('Y-m-d', strtotime("+$rango_dias days"));
    
    // Si el filtro es para ver "Reservas" (historial), empezamos desde hace X días
    // Ajusta '90' según cuánto historial quieras mostrar
    $dias_historial = ($filtros['fecha'] === 'reservas' || empty($filtros['fecha'])) ? 90 : 0;
    $fecha_inicio = date('Y-m-d', strtotime("-$dias_historial days"));
    
    // Si el rango_dias era específico (ej. semana), mantenemos la lógica original de inicio en HOY
    if (!empty($filtros['fecha']) && $filtros['fecha'] !== 'reservas') {
        $fecha_inicio = $fecha_hoy;
        $fecha_fin = date('Y-m-d', strtotime("+$rango_dias days"));
    }

    error_log("📅 [FILTRO] Rango fechas: $fecha_inicio a $fecha_fin");

    // Obtener datos base con el rango calculado
    // NOTA: getReservasDataOptimizada usa $rango_dias relativo a HOY hacia adelante.
    // Para soportar pasado, necesitamos ajustar la función getReservasDataOptimizada 
    // o hacer una consulta directa aquí. 
    
    // MEJOR OPCIÓN: Modificar ligeramente getReservasDataOptimizada para aceptar fecha_inicio explícita
    // Pero para no romper todo, hagamos un truco: si es 'reservas', pedimos un rango grande (ej. 400 días)
    // y filtramos por estado 'reservada'/'confirmada' independientemente de la fecha en el loop.
    
    if ($filtros['fecha'] === 'reservas' || (empty($filtros['fecha']) && empty($filtros['estado']))) {
         // Forzamos un rango amplio para capturar pasado y futuro cercano
         $rango_dias = 400; 
         $fecha_inicio = date('Y-m-d', strtotime('-30 days')); // 30 días atrás
         $fecha_fin = date('Y-m-d', strtotime('+365 days'));   // 1 año adelante
    } else {
         $fecha_inicio = date('Y-m-d');
         $fecha_fin = date('Y-m-d', strtotime("+$rango_dias days"));
    }

    // LLAMADA A LA FUNCIÓN BASE (Debemos pasarle fechas custom o usar el truco del rango)
    // Como getReservasDataOptimizada calcula fechas internamente, vamos a forzar el rango grande
    // y luego filtrar en PHP si es necesario.
    
    // Truco: Calculamos días totales desde hoy hasta la fecha_fin deseada
    $diff = strtotime($fecha_fin) - strtotime($fecha_inicio);
    $dias_totales = floor($diff / (60 * 60 * 24));
    
    // Obtenemos datos desde HOY hasta el fin (la función siempre empieza en hoy)
    // PARA VER EL PASADO, necesitamos modificar getReservasDataOptimizada para aceptar fecha_inicio.
    // HACEMOS ESO AHORA MISMO EN EL CÓDIGO DE ABAJO (versión modificada de la función).
    
    $todos_datos = getReservasDataOptimizadaConRangoCustom($pdo, $id_recinto, $fecha_inicio, $fecha_fin);
    
    error_log("📊 [FILTRO] Datos base obtenidos: " . count($todos_datos));
    
    $datos_filtrados = [];
    foreach ($todos_datos as $dato) {
        $saltar = false;
        
        // Filtro Deporte
        if (!empty($filtros['deporte']) && $dato['id_deporte'] !== $filtros['deporte']) {
            $saltar = true;
        }
        
        // Filtro Estado (Aquí es clave: si el estado es 'reservada', mostramos aunque sea pasada)
        if (!$saltar && !empty($filtros['estado'])) {
            $esReservaReal = !empty($dato['id_reserva']) && $dato['id_reserva'] !== 'null';
            $estadoReserva = $dato['estado_reserva'] ?? null;
            $estadoDisp = $dato['estado_disponibilidad'] ?? 'disponible';
            $estadoPago = $dato['estado_pago'] ?? null;
            
            $pasaEstado = false;
            switch($filtros['estado']) {
                case 'disponible':
                    $pasaEstado = (!$esReservaReal && ($estadoDisp === 'disponible' || empty($estadoDisp)));
                    break;
                case 'reservada':
                    // MUESTRA RESERVAS CONFIRMADAS (PASADAS O FUTURAS)
                    $pasaEstado = ($esReservaReal && in_array($estadoReserva, ['confirmada', 'completada']));
                    break;
                case 'ocupada':
                    $pasaEstado = ($esReservaReal && $estadoReserva === 'completada');
                    break;
                case 'cancelada':
                    $pasaEstado = ($esReservaReal && $estadoReserva === 'cancelada');
                    break;
                case 'parcial':
                     $pasaEstado = ($esReservaReal && $estadoPago === 'parcial');
                     break;
            }
            if (!$pasaEstado) $saltar = true;
        }
        
        // Filtro Fecha Específica (solo si no estamos en modo "todas las reservas")
        if (!$saltar && !empty($filtros['fecha']) && $filtros['fecha'] !== 'reservas') {
            $fecha_dato = $dato['fecha'];
            if ($filtros['fecha'] === 'hoy' && $fecha_dato !== $fecha_hoy) $saltar = true;
            elseif ($filtros['fecha'] === 'mañana' && $fecha_dato !== date('Y-m-d', strtotime('+1 day'))) $saltar = true;
        }
        
        if (!$saltar) $datos_filtrados[] = $dato;
    }
    
    error_log("✅ [FILTRO] Resultado final: " . count($datos_filtrados));
    return $datos_filtrados;
}

// NUEVA FUNCIÓN AUXILIAR PARA PERMITIR FECHA INICIO CUSTOM (PASADO)
function getReservasDataOptimizadaConRangoCustom($pdo, $id_recinto, $fecha_inicio_custom, $fecha_fin_custom) {
    // Copia de getReservasDataOptimizada pero usando las fechas pasadas por argumento
    error_log("📊 [DB CUSTOM] Buscando desde $fecha_inicio_custom hasta $fecha_fin_custom");

    $stmt_canchas = $pdo->prepare("SELECT id_cancha, nro_cancha, nombre_cancha, id_deporte, dias_disponibles, hora_inicio, hora_fin, duracion_bloque FROM canchas WHERE id_recinto = ? AND activa = 1");
    $stmt_canchas->execute([$id_recinto]);
    $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($canchas)) return [];
    
    $todas_disponibilidades = [];
    foreach ($canchas as $cancha) {
        $disponibilidades = generarDisponibilidadCancha($cancha, $fecha_inicio_custom, $fecha_fin_custom);
        $todas_disponibilidades = array_merge($todas_disponibilidades, $disponibilidades);
    }
    
    $stmt_reservas = $pdo->prepare("SELECT dc.id_disponibilidad, dc.id_cancha, dc.fecha, dc.hora_inicio, dc.hora_fin, dc.estado as estado_disponibilidad, r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total, r.tipo_reserva, r.id_convenio, r.notas, cl.nombre as nombre_club, s.alias as nombre_responsable, r.telefono_cliente, r.email_cliente FROM disponibilidad_canchas dc LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva LEFT JOIN clubs cl ON r.id_club = cl.id_club LEFT JOIN socios s ON r.id_socio = s.id_socio WHERE dc.fecha BETWEEN ? AND ? AND dc.id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)");
    $stmt_reservas->execute([$fecha_inicio_custom, $fecha_fin_custom, $id_recinto]);
    $reservas_reales = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
    
    $reservas_map = [];
    foreach ($reservas_reales as $reserva) {
        $key = $reserva['id_cancha'].'_'.$reserva['fecha'].'_'.$reserva['hora_inicio'];
        $reservas_map[$key] = $reserva;
    }
    
    $resultado_final = [];
    foreach ($todas_disponibilidades as $disp) {
        $key = $disp['id_cancha'].'_'.$disp['fecha'].'_'.$disp['hora_inicio'];
        $resultado_final[] = isset($reservas_map[$key]) ? array_merge($disp, $reservas_map[$key]) : $disp;
    }
    
    usort($resultado_final, function($a, $b) {
        if ($a['fecha'] != $b['fecha']) return strcmp($a['fecha'], $b['fecha']);
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
    
    return $resultado_final;
}

function getDetalleReserva($pdo, $id_disponibilidad, $id_recinto) {
    $stmt = $pdo->prepare("
        SELECT dc.id_disponibilidad, c.nro_cancha, c.nombre_cancha, c.id_deporte,
               dc.fecha, dc.hora_inicio, dc.hora_fin, dc.estado as estado_disponibilidad,
               r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total,
               r.tipo_reserva, r.id_convenio, r.notas,
               cl.nombre as nombre_club, cl.logo as logo_club,
               s.alias as nombre_responsable, s.email as email_responsable,
               r.telefono_cliente, r.email_cliente, r.created_at as fecha_reserva
        FROM disponibilidad_canchas dc
        JOIN canchas c ON dc.id_cancha = c.id_cancha
        LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
        LEFT JOIN clubs cl ON r.id_club = cl.id_club
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE dc.id_disponibilidad = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_disponibilidad, $id_recinto]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$detalle) throw new Exception('Reserva no encontrada');
    return $detalle;
}
?>