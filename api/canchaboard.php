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
                // Intentar obtener id_disponibilidad primero
                $id_disponibilidad = (int)($_POST['id_disponibilidad'] ?? 0);
                
                // Si no hay id_disponibilidad, buscar por id_reserva
                if (!$id_disponibilidad) {
                    $id_reserva_alt = (int)($_POST['id_reserva'] ?? 0);
                    
                    if ($id_reserva_alt) {
                        // Buscar el id_disponibilidad asociado a esta reserva en la fecha/hora correspondiente
                        // O simplemente llamar a una función que busque directo por reserva
                        // Para simplificar, vamos a modificar la llamada a la función getDetalleReserva
                        // para que acepte buscar por ID de reserva si la disponibilidad es 0.
                        
                        // Opción A: Buscar el ID de disponibilidad primero (más robusto)
                        $stmt_find_disp = $pdo->prepare("SELECT id_disponibilidad FROM disponibilidad_canchas WHERE id_reserva = ? LIMIT 1");
                        $stmt_find_disp->execute([$id_reserva_alt]);
                        $disp_data = $stmt_find_disp->fetch();
                        
                        if ($disp_data) {
                            $id_disponibilidad = (int)$disp_data['id_disponibilidad'];
                        } else {
                            // Si no hay registro en disponibilidad (raro), lanzamos error o manejamos例外
                            throw new Exception('No se encontró disponibilidad asociada a esta reserva');
                        }
                    } else {
                        throw new Exception('ID de disponibilidad o ID de reserva requerido');
                    }
                }

                // Ahora sí llamamos a la función con el ID válido
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

            case 'get_planilla_reservas':
                $fecha = $_GET['fecha'] ?? date('Y-m-d');
                $deporte = $_GET['deporte'] ?? ''; // Ej: 'padel'
                
                if (!$deporte) {
                    echo json_encode(['error' => 'Deporte requerido']);
                    exit;
                }
                
                echo json_encode(getPlanillaReservas($pdo, $id_recinto, $fecha, $deporte));
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
        
        // Definir rango de fechas base
        // Por defecto: Solo futuras (desde hoy)
        $fecha_inicio = $fecha_hoy; 
        $fecha_fin = date('Y-m-d', strtotime('+30 days')); // 30 días adelante
        
        // Ajustes según el filtro de fecha seleccionado
        if ($filtros['fecha'] === 'hoy') {
            $fecha_inicio = $fecha_hoy;
            $fecha_fin = $fecha_hoy;
        } elseif ($filtros['fecha'] === 'mañana') {
            $fecha_inicio = date('Y-m-d', strtotime('+1 day'));
            $fecha_fin = $fecha_inicio;
        } elseif ($filtros['fecha'] === 'semana') {
            $fecha_fin = date('Y-m-d', strtotime('+7 days'));
        } elseif ($filtros['fecha'] === 'mes') {
            $fecha_fin = date('Y-m-d', strtotime('+30 days'));
        }
        // Si es 'reservas' (todas), ampliamos el rango (opcional, pero útil)
        elseif ($filtros['fecha'] === 'reservas') {
            $fecha_inicio = date('Y-m-d', strtotime('-30 days')); // 30 días atrás
            $fecha_fin = date('Y-m-d', strtotime('+365 days'));   // 1 año adelante
        }

        error_log(" [FILTRO] Rango fechas: $fecha_inicio a $fecha_fin");

        // 1. Obtener canchas activas
        $stmt_canchas = $pdo->prepare("SELECT id_cancha, nro_cancha, nombre_cancha, id_deporte, dias_disponibles, hora_inicio, hora_fin, duracion_bloque FROM canchas WHERE id_recinto = ? AND activa = 1");
        $stmt_canchas->execute([$id_recinto]);
        $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($canchas)) return [];
        
        // 2. Generar disponibilidades dinámicas
        $todas_disponibilidades = [];
        foreach ($canchas as $cancha) {
            $disponibilidades = generarDisponibilidadCancha($cancha, $fecha_inicio, $fecha_fin);
            $todas_disponibilidades = array_merge($todas_disponibilidades, $disponibilidades);
        }
        
        // 3. Obtener reservas reales en ese rango
        $stmt_reservas = $pdo->prepare("
            SELECT dc.id_disponibilidad, dc.id_cancha, dc.fecha, dc.hora_inicio, dc.hora_fin,
                dc.estado as estado_disponibilidad,
                r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total, r.monto_recaudacion,
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
        
        // 4. Fusionar datos
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
        
        // 5. Filtrado final en PHP
        $datos_filtrados = [];
        foreach ($resultado_final as $dato) {
            $saltar = false;
            
            // Filtro Deporte
            if (!empty($filtros['deporte']) && $dato['id_deporte'] !== $filtros['deporte']) {
                $saltar = true;
            }
            
            // Filtro Estado (LÓGICA CORREGIDA)
            if (!$saltar && !empty($filtros['estado'])) {
                $esReservaReal = !empty($dato['id_reserva']) && $dato['id_reserva'] !== 'null';
                $estadoReserva = $dato['estado_reserva'] ?? null;
                $estadoPago = $dato['estado_pago'] ?? null;
                $fechaDato = $dato['fecha'];
                
                $pasaEstado = false;
                
                switch($filtros['estado']) {
                    case 'disponible':
                        $pasaEstado = (!$esReservaReal);
                        break;
                        
                    case 'reservada':
                        // SOLO FUTURAS (fecha >= hoy) Y estado confirmada/pendiente
                        // Excluimos las pagadas completamente aquí
                        if ($fechaDato >= $fecha_hoy && $esReservaReal && in_array($estadoReserva, ['confirmada', 'pendiente']) && $estadoPago !== 'pagado') {
                            $pasaEstado = true;
                        }
                        break;
                        
                    case 'pagadas': // NUEVO FILTRO
                        // Muestra reservas con estado_pago = 'pagado' O 'parcial'
                        // Podemos mostrar también las pasadas si fueron pagadas
                        if ($esReservaReal && in_array($estadoPago, ['pagado', 'parcial'])) {
                            $pasaEstado = true;
                        }
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
            
            // Filtro Fecha Específica (si no es el modo amplio 'reservas')
            if (!$saltar && !empty($filtros['fecha']) && $filtros['fecha'] !== 'reservas') {
                if ($filtros['fecha'] === 'hoy' && $fechaDato !== $fecha_hoy) $saltar = true;
                elseif ($filtros['fecha'] === 'mañana' && $fechaDato !== date('Y-m-d', strtotime('+1 day'))) $saltar = true;
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
                r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total, r.monto_recaudacion,
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

    function getPlanillaReservas($pdo, $id_recinto, $fecha, $deporte) {
        // 1. Obtener Canchas Activas y Operativas del Deporte seleccionado
        $stmt_canchas = $pdo->prepare("
            SELECT id_cancha, nro_cancha, nombre_cancha, hora_inicio, hora_fin, duracion_bloque
            FROM canchas
            WHERE id_recinto = ? 
            AND id_deporte = ? 
            AND activa = 1 
            AND estado = 'Operativa'
            ORDER BY nro_cancha ASC
        ");
        $stmt_canchas->execute([$id_recinto, $deporte]);
        $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($canchas)) {
            return ['canchas' => [], 'slots' => [], 'reservas' => []];
        }
        
        // 2. Determinar rango horario global (mínimo inicio, máximo fin de todas las canchas)
        $min_hora = null;
        $max_hora = null;
        
        foreach ($canchas as $c) {
            $inicio = strtotime("1970-01-01 {$c['hora_inicio']}");
            $fin = strtotime("1970-01-01 {$c['hora_fin']}");
            
            if ($min_hora === null || $inicio < $min_hora) $min_hora = $inicio;
            if ($max_hora === null || $fin > $max_hora) $max_hora = $fin;
        }
        
        // Generar slots de 30 minutos
        $slots = [];
        $current_time = $min_hora;
        while ($current_time < $max_hora) {
            $next_time = $current_time + 1800; // +30 min
            
            // Formato para etiqueta: "07:00"
            $label = date('H:i', $current_time);
            
            $slots[] = [
                'start_ts' => $current_time,
                'end_ts' => $next_time,
                'label' => $label,
                'is_label_row' => true // Esta fila muestra la hora
            ];
            
            $current_time = $next_time;
        }
        
        // 3. Obtener Reservas del día seleccionado para estas canchas
        // Traemos todos los datos necesarios para pintar la celda
        $cancha_ids = array_column($canchas, 'id_cancha');
        $placeholders = implode(',', array_fill(0, count($cancha_ids), '?'));
        
        $stmt_reservas = $pdo->prepare("
            SELECT r.id_reserva, r.id_cancha, r.hora_inicio, r.hora_fin, r.estado, r.estado_pago, 
                r.monto_recaudacion, r.nombre_cliente, r.telefono_cliente, r.notas,
                s.alias as nombre_socio, cl.nombre as nombre_club
            FROM reservas r
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            LEFT JOIN clubs cl ON r.id_club = cl.id_club
            WHERE r.fecha = ? 
            AND r.id_cancha IN ($placeholders)
            AND r.estado != 'cancelada'
            ORDER BY r.hora_inicio
        ");
        
        $params = array_merge([$fecha], $cancha_ids);
        $stmt_reservas->execute($params);
        $reservas_raw = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear reservas por cancha y hora para fácil acceso
        $reservas_map = [];
        foreach ($reservas_raw as $res) {
            $key = $res['id_cancha'] . '_' . date('H:i', strtotime("1970-01-01 {$res['hora_inicio']}"));
            $reservas_map[$key] = $res;
        }
        
        return [
            'canchas' => $canchas,
            'slots' => $slots,
            'reservas' => $reservas_map, // Mapa rápido: 'idCancha_Hora' => datos
            'fecha' => $fecha
        ];
    }
?>