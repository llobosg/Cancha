<?php
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../includes/config.php';


    try {
        // 1. Verificar autenticación
        $rol_actual = $_SESSION['recinto_rol'] ?? '';
        $roles_permitidos = ['admin', 'asistente']; // Aceptamos ambos roles

        if (!isset($_SESSION['id_recinto']) || !in_array($rol_actual, $roles_permitidos)) {
            error_log("❌ [API] Acceso denegado. Rol actual: '$rol_actual'. Roles permitidos: " . implode(', ', $roles_permitidos));
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
                // Validar sesión y recinto
                if (!isset($_SESSION['id_recinto'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Sesión no válida']);
                    exit;
                }
                
                $id_reserva = (int)($_POST['id_reserva'] ?? 0);
                $id_recinto = (int)$_SESSION['id_recinto'];

                error_log("[DEBUG] get_detalle_reserva: id_reserva=$id_reserva, id_recinto=$id_recinto");
                
                if (!$id_reserva) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID de reserva requerido']);
                    exit;
                }
                
                // Consulta SEGURA: validar que la reserva pertenece a este recinto
                $stmt = $pdo->prepare("
                    SELECT 
                        r.*, 
                        c.nombre_cancha, 
                        c.id_deporte,
                        rec.nombre as recinto_nombre
                    FROM reservas r
                    JOIN canchas c ON r.id_cancha = c.id_cancha
                    JOIN recintos_deportivos rec ON c.id_recinto = rec.id_recinto
                    WHERE r.id_reserva = ? 
                    AND c.id_recinto = ?
                    AND r.estado != 'cancelada'
                ");
                $stmt->execute([$id_reserva, $id_recinto]);
                $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$detalle) {
                    // Log para depuración (solo en desarrollo)
                    error_log("[API] Reserva $id_reserva no encontrada para recinto $id_recinto");
                    http_response_code(404);
                    echo json_encode(['error' => 'Reserva no encontrada o no pertenece a este recinto']);
                    exit;
                }
                
                echo json_encode($detalle);
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
                
                if ($deporte && $deporte !== 'todos' && !in_array($deporte, ['futbol', 'padel', 'tenis', 'voleyball', 'futsal', 'futbolito'])) {
                    echo json_encode(['error' => 'Deporte no válido']);
                    exit;
                }
                
                echo json_encode(getPlanillaReservas($pdo, $id_recinto, $fecha, $deporte));
                break;

            case 'get_lista_kpi':
                $tipo = $_GET['tipo'] ?? ''; // 'parcial' o 'deuda'
                echo json_encode(getListaKPI($pdo, $id_recinto, $tipo));
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
                r.telefono_cliente, r.email_cliente,
                lc.usuario_nombre as usuario_creacion
            FROM disponibilidad_canchas dc
            LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
            LEFT JOIN clubs cl ON r.id_club = cl.id_club
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            LEFT JOIN (
                SELECT id_reserva, usuario_nombre FROM reservas_log WHERE accion = 'creada'
            ) lc ON r.id_reserva = lc.id_reserva
            WHERE dc.fecha BETWEEN ? AND ? AND dc.id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)
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
        
        // Obtener hora de cierre de la cancha y duración del bloque
        $hora_cierre_str = strlen($cancha['hora_fin']) == 5 ? $cancha['hora_fin'].':00' : $cancha['hora_fin'];
        $duracion_minutos = (int)$cancha['duracion_bloque'];
        
        // Calcular la HORA MÁXIMA DE INICIO para que la reserva termine a tiempo
        // Ej: cierre 23:00, duración 90 min → última inicio posible: 21:30
        $hora_cierre_dt = new DateTime("1970-01-01 $hora_cierre_str");
        $hora_max_inicio_dt = clone $hora_cierre_dt;
        $hora_max_inicio_dt->sub(new DateInterval('PT' . $duracion_minutos . 'M'));
        
        while ($current_date <= $end_date) {
            $dia_semana = $dias_map[(int)$current_date->format('N')];
            
            if (in_array($dia_semana, $dias_disponibles)) {
                $hora_inicio_str = strlen($cancha['hora_inicio']) == 5 ? $cancha['hora_inicio'].':00' : $cancha['hora_inicio'];
                
                $fecha_base = new DateTime('1970-01-01');
                $hora_inicio_dt = clone $fecha_base;
                $hora_inicio_dt->setTime((int)substr($hora_inicio_str,0,2), (int)substr($hora_inicio_str,3,2), (int)substr($hora_inicio_str,6,2));
                
                // ✅ CORRECCIÓN CLAVE: Iterar solo mientras hora_inicio <= hora_max_inicio
                $current_hora_dt = clone $hora_inicio_dt;
                while ($current_hora_dt <= $hora_max_inicio_dt) {
                    $hora_fin_bloque_dt = clone $current_hora_dt;
                    $hora_fin_bloque_dt->add(new DateInterval('PT' . $duracion_minutos . 'M'));
                    
                    // Verificación extra de seguridad
                    if ($hora_fin_bloque_dt <= $hora_cierre_dt) {
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
                    // Avanzar al siguiente slot (puede ser cada 30 min o según duracion_bloque)
                    $current_hora_dt->add(new DateInterval('PT30M')); // Avanzar de 30 en 30 min para la grilla
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
            $stmt_reservas = $pdo->prepare("
                SELECT dc.id_disponibilidad, dc.id_cancha, dc.fecha, dc.hora_inicio, dc.hora_fin,
                    dc.estado as estado_disponibilidad,
                    r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total,
                    r.tipo_reserva, r.id_convenio, r.notas,
                    cl.nombre as nombre_club, s.alias as nombre_responsable,
                    r.telefono_cliente, r.email_cliente,
                    lc.usuario_nombre as usuario_creacion
                FROM disponibilidad_canchas dc
                LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
                LEFT JOIN clubs cl ON r.id_club = cl.id_club
                LEFT JOIN socios s ON r.id_socio = s.id_socio
                LEFT JOIN (
                    SELECT id_reserva, usuario_nombre FROM reservas_log WHERE accion = 'creada'
                ) lc ON r.id_reserva = lc.id_reserva
                WHERE dc.fecha BETWEEN ? AND ? AND dc.id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)
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
        
        $stmt_reservas = $pdo->prepare("
            SELECT dc.id_disponibilidad, dc.id_cancha, dc.fecha, dc.hora_inicio, dc.hora_fin,
                dc.estado as estado_disponibilidad,
                r.id_reserva, r.estado as estado_reserva, r.estado_pago, r.monto_total,
                r.tipo_reserva, r.id_convenio, r.notas,
                cl.nombre as nombre_club, s.alias as nombre_responsable,
                r.telefono_cliente, r.email_cliente,
                lc.usuario_nombre as usuario_creacion
            FROM disponibilidad_canchas dc
            LEFT JOIN reservas r ON dc.id_reserva = r.id_reserva
            LEFT JOIN clubs cl ON r.id_club = cl.id_club
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            LEFT JOIN (
                SELECT id_reserva, usuario_nombre FROM reservas_log WHERE accion = 'creada'
            ) lc ON r.id_reserva = lc.id_reserva
            WHERE dc.fecha BETWEEN ? AND ? AND dc.id_cancha IN (SELECT id_cancha FROM canchas WHERE id_recinto = ?)
        ");
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

    // En api/canchaboard.php, dentro de la función getPlanillaReservas

    function getPlanillaReservas($pdo, $id_recinto, $fecha, $deporte) {
        // 1. Determinar la consulta según el filtro
        if ($deporte === 'todos' || empty($deporte)) {
            // ✅ CASO "TODOS LOS DEPORTES": Traer todas las canchas activas del recinto
            $stmt_canchas = $pdo->prepare("
                SELECT id_cancha, nro_cancha, nombre_cancha, id_deporte, hora_inicio, hora_fin, duracion_bloque
                FROM canchas
                WHERE id_recinto = ? 
                AND activa = 1 
                AND estado = 'Operativa'
                ORDER BY id_deporte ASC, nro_cancha ASC
            ");
            $stmt_canchas->execute([$id_recinto]);
        } else {
            // ✅ CASO FILTRADO: Solo el deporte seleccionado
            $stmt_canchas = $pdo->prepare("
                SELECT id_cancha, nro_cancha, nombre_cancha, id_deporte, hora_inicio, hora_fin, duracion_bloque
                FROM canchas
                WHERE id_recinto = ? 
                AND id_deporte = ? 
                AND activa = 1 
                AND estado = 'Operativa'
                ORDER BY nro_cancha ASC
            ");
            $stmt_canchas->execute([$id_recinto, $deporte]);
        }
        
        $canchas = $stmt_canchas->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($canchas)) {
            return ['canchas' => [], 'slots' => [], 'reservas' => []];
        }
        
        // ... (El resto de la función sigue igual: generar slots, obtener reservas, mapear, etc.) ...
        
        // 2. Generar slots globales (mínimo inicio, máximo fin de TODAS las canchas seleccionadas)
        $min_hora = null;
        $max_hora = null;
        
        foreach ($canchas as $c) {
            $inicio = strtotime("1970-01-01 {$c['hora_inicio']}");
            $fin = strtotime("1970-01-01 {$c['hora_fin']}");
            
            if ($min_hora === null || $inicio < $min_hora) $min_hora = $inicio;
            if ($max_hora === null || $fin > $max_hora) $max_hora = $fin;
        }
        
        // Generar slots... (código existente)
        $slots = [];
        $current_time = $min_hora;
        while ($current_time < $max_hora) {
            $next_time = $current_time + 1800; // +30 min
            $slots[] = [
                'start_ts' => $current_time,
                'end_ts' => $next_time,
                'label' => date('H:i', $current_time),
                'is_label_row' => true
            ];
            $current_time = $next_time;
        }

        // 3. Obtener Reservas para estas canchas (sin filtrar por deporte aquí, ya que las IDs vienen filtradas arriba)
        $cancha_ids = array_column($canchas, 'id_cancha');
        $placeholders = implode(',', array_fill(0, count($cancha_ids), '?'));
        
        $stmt_reservas = $pdo->prepare("
            SELECT r.id_reserva, r.id_cancha, r.hora_inicio, r.hora_fin, r.estado, r.estado_pago, 
                r.monto_recaudacion, r.nombre_cliente, r.telefono_cliente, r.notas,
                s.alias as nombre_socio, cl.nombre as nombre_club, c.id_deporte,
                lc.usuario_nombre as usuario_creacion
            FROM reservas r
            JOIN canchas c ON r.id_cancha = c.id_cancha
            LEFT JOIN socios s ON r.id_socio = s.id_socio
            LEFT JOIN clubs cl ON r.id_club = cl.id_club
            LEFT JOIN (
                SELECT id_reserva, usuario_nombre FROM reservas_log WHERE accion = 'creada'
            ) lc ON r.id_reserva = lc.id_reserva
            WHERE r.fecha = ? AND r.id_cancha IN ($placeholders) AND r.estado != 'cancelada'
            ORDER BY r.hora_inicio
        ");
        
        $params = array_merge([$fecha], $cancha_ids);
        $stmt_reservas->execute($params);
        $reservas_raw = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear reservas... (código existente)
        $reservas_map = [];
        foreach ($reservas_raw as $res) {
            // La clave sigue siendo id_cancha_hora
            $key = $res['id_cancha'] . '_' . date('H:i', strtotime("1970-01-01 {$res['hora_inicio']}"));
            $reservas_map[$key] = $res;
        }
        
        return [
            'canchas' => $canchas,
            'slots' => $slots,
            'reservas' => $reservas_map,
            'fecha' => $fecha
        ];
    }

    function getListaKPI($pdo, $id_recinto, $tipo) {
        $hoy = date('Y-m-d');
        $primer_dia_mes = date('Y-m-01');
        
        if ($tipo === 'parcial') {
            // Pagos parciales del mes actual
            $sql = "SELECT r.id_reserva, r.fecha, r.hora_inicio, c.nombre_cancha, 
                        r.nombre_cliente, r.telefono_cliente, 
                        r.monto_total, r.monto_recaudacion, 
                        (r.monto_total - r.monto_recaudacion) as saldo_pendiente,
                        r.notas
                    FROM reservas r
                    JOIN canchas c ON r.id_cancha = c.id_cancha
                    WHERE c.id_recinto = :id_recinto
                    AND r.estado_pago = 'parcial'
                    AND r.fecha >= :fecha_inicio
                    AND r.estado != 'cancelada'
                    ORDER BY r.fecha DESC";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_recinto' => $id_recinto, ':fecha_inicio' => $primer_dia_mes]);
            
        } elseif ($tipo === 'deuda') {
            // Deudas vencidas (fecha < hoy y no pagado total)
            $sql = "SELECT r.id_reserva, r.fecha, r.hora_inicio, c.nombre_cancha, 
                        r.nombre_cliente, r.telefono_cliente, 
                        r.monto_total, r.monto_recaudacion, 
                        (r.monto_total - r.monto_recaudacion) as saldo_pendiente,
                        r.notas
                    FROM reservas r
                    JOIN canchas c ON r.id_cancha = c.id_cancha
                    WHERE c.id_recinto = :id_recinto
                    AND r.fecha < :hoy
                    AND r.estado_pago != 'pagado'
                    AND r.estado != 'cancelada'
                    ORDER BY r.fecha ASC"; // Las más antiguas primero
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_recinto' => $id_recinto, ':hoy' => $hoy]);
        } else {
            return [];
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
?>