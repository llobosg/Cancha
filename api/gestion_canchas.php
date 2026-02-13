<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Prevenir salida antes del JSON
if (ob_get_level() === 0) {
    ob_start();
}

try {
    session_start();
    if (!isset($_SESSION['id_recinto']) || $_SESSION['recinto_rol'] !== 'admin_recinto') {
        throw new Exception('Acceso no autorizado');
    }
    
    $action = $_POST['action'] ?? '';
    $id_recinto = (int)($_POST['id_recinto'] ?? 0);
    
    // Verificar que el recinto pertenece al administrador
    if ($id_recinto !== $_SESSION['id_recinto']) {
        throw new Exception('Acceso no autorizado al recinto');
    }
    
    if ($action !== 'insert' && $action !== 'update' && $action !== 'delete') {
        throw new Exception('Acción no válida');
    }
    
    switch ($action) {
        case 'insert':
        case 'update':
            $nro_cancha = trim($_POST['nro_cancha'] ?? '');
            $nombre_cancha = trim($_POST['nombre_cancha'] ?? '');
            $id_deporte = $_POST['id_deporte'] ?? '';
            $valor_arriendo = (float)($_POST['valor_arriendo'] ?? 0);
            $duracion_bloque = (int)($_POST['duracion_bloque'] ?? 60);
            $hora_inicio = $_POST['hora_inicio'] ?? '07:00';
            $hora_fin = $_POST['hora_fin'] ?? '21:00';
            $capacidad_jugadores = (int)($_POST['capacidad_jugadores'] ?? 10);
            $activa = (int)($_POST['activa'] ?? 1);
            $estado = $_POST['estado'] ?? 'Operativa';
            
            // Validar campos requeridos
            if (empty($nro_cancha)) {
                throw new Exception('El número/nombre de la cancha es requerido');
            }
            
            if (empty($id_deporte)) {
                throw new Exception('El deporte es requerido');
            }
            
            $deportes_validos = ['futbol', 'futbolito', 'futsal', 'tenis', 'padel', 'voleyball', 'otro'];
            if (!in_array($id_deporte, $deportes_validos)) {
                throw new Exception('Deporte no válido');
            }
            
            if ($valor_arriendo <= 0) {
                throw new Exception('El valor de arriendo debe ser mayor a 0');
            }
            
            if ($duracion_bloque < 60) {
                throw new Exception('La duración mínima debe ser de 60 minutos');
            }
            
            if ($duracion_bloque > 180) {
                throw new Exception('La duración máxima es de 180 minutos');
            }
            
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_inicio)) {
                throw new Exception('Formato de hora inicio inválido');
            }
            
            if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_fin)) {
                throw new Exception('Formato de hora fin inválido');
            }
            
            if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
                throw new Exception('La hora de fin debe ser posterior a la hora de inicio');
            }
            
            // Validar días disponibles
            $dias_disponibles = $_POST['dias_disponibles'] ?? [];
            if (is_string($dias_disponibles)) {
                $dias_disponibles = json_decode($dias_disponibles, true);
            }
            
            if (!is_array($dias_disponibles) || empty($dias_disponibles)) {
                throw new Exception('Debe seleccionar al menos un día de disponibilidad');
            }
            
            $dias_validos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            foreach ($dias_disponibles as $dia) {
                if (!in_array($dia, $dias_validos)) {
                    throw new Exception('Día no válido en la disponibilidad');
                }
            }
            
            // Convertir días a JSON
            $dias_json = json_encode($dias_disponibles);
            
            // Validar estado
            $estados_validos = ['Operativa', 'Reservada', 'Mantención', 'Construcción'];
            if (!in_array($estado, $estados_validos)) {
                throw new Exception('Estado no válido');
            }
            
            $id_cancha_generar = null; // Variable para guardar el ID

            if ($action === 'insert') {
                // Primera inserción sin fechas
                $stmt = $pdo->prepare("
                    INSERT INTO canchas (
                        id_recinto, id_deporte, nro_cancha, nombre_cancha, 
                        valor_arriendo, capacidad_jugadores, duracion_bloque, 
                        hora_inicio, hora_fin, dias_disponibles, activa, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_recinto, $id_deporte, $nro_cancha, $nombre_cancha,
                    $valor_arriendo, $capacidad_jugadores, $duracion_bloque,
                    $hora_inicio, $hora_fin, $dias_json, $activa, $estado
                ]);
                
                // Obtener el ID INMEDIATAMENTE después del INSERT
                $id_cancha = $pdo->lastInsertId();
                $id_cancha_generar = $id_cancha; // ✅ Guardar para usar después
                
                // Verificar que el ID sea válido
                if ($id_cancha) {
                    // Actualizar fechas después de la inserción
                    $update_stmt = $pdo->prepare("
                        UPDATE canchas 
                        SET fecha_desde = CURDATE(), 
                            fecha_hasta = DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
                        WHERE id_cancha = ?
                    ");
                    $update_result = $update_stmt->execute([$id_cancha]);
                    
                    error_log("=== DIAGNÓSTICO CANCHA ===");
                    error_log("ID Cancha creada: $id_cancha");
                    error_log("Actualización fechas resultado: " . ($update_result ? 'ÉXITO' : 'FALLO'));
                }
                
            } else {
                $id_cancha = (int)($_POST['id_cancha'] ?? 0);
                if (!$id_cancha) {
                    throw new Exception('ID de cancha requerido para actualización');
                }
                
                // Verificar que la cancha pertenece al recinto
                $stmt_check = $pdo->prepare("SELECT id_cancha FROM canchas WHERE id_cancha = ? AND id_recinto = ?");
                $stmt_check->execute([$id_cancha, $id_recinto]);
                if (!$stmt_check->fetch()) {
                    throw new Exception('Cancha no encontrada o no pertenece al recinto');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE canchas SET 
                        id_deporte = ?, nro_cancha = ?, nombre_cancha = ?, 
                        valor_arriendo = ?, capacidad_jugadores = ?, duracion_bloque = ?, 
                        hora_inicio = ?, hora_fin = ?, dias_disponibles = ?, activa = ?, estado = ?
                    WHERE id_cancha = ?
                ");
                $stmt->execute([
                    $id_deporte, $nro_cancha, $nombre_cancha,
                    $valor_arriendo, $capacidad_jugadores, $duracion_bloque,
                    $hora_inicio, $hora_fin, $dias_json, $activa, $estado,
                    $id_cancha
                ]);
            }
            break;
            $id_cancha_generar = (int)($_POST['id_cancha'] ?? 0);    
        case 'delete':
            $id_cancha = (int)($_POST['id_cancha'] ?? 0);
            if (!$id_cancha) {
                throw new Exception('ID de cancha requerido');
            }
            
            // Verificar que la cancha pertenece al recinto
            $stmt_check = $pdo->prepare("SELECT id_cancha FROM canchas WHERE id_cancha = ? AND id_recinto = ?");
            $stmt_check->execute([$id_cancha, $id_recinto]);
            if (!$stmt_check->fetch()) {
                throw new Exception('Cancha no encontrada o no pertenece al recinto');
            }
            
            $stmt = $pdo->prepare("DELETE FROM canchas WHERE id_cancha = ?");
            $stmt->execute([$id_cancha]);
            break;
    }
    
    // Generar disponibilidad solo si es necesario
    if ($action === 'insert' || $action === 'update') {
        if ($id_cancha_generar) { // ✅ Usar la variable guardada
            error_log("Iniciando generación de disponibilidad para cancha: $id_cancha_generar");
            generarDisponibilidadSimple($pdo, $id_cancha_generar);
        }
    }
    
    // Limpiar cualquier salida previa
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Limpiar buffer
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (ob_get_level() > 0) {
    ob_end_flush();
}

// === FUNCIÓN SIMPLE DE GENERACIÓN CON LOGGING DETALLADO ===
function generarDisponibilidadSimple($pdo, $id_cancha) {
    try {
        error_log("=== INICIANDO GENERACIÓN DISPONIBILIDAD ===");
        error_log("Procesando cancha ID: $id_cancha");
        
        // Obtener datos de la cancha
        $stmt = $pdo->prepare("
            SELECT 
                c.id_cancha, c.hora_inicio, c.hora_fin, c.duracion_bloque,
                c.dias_disponibles, c.fecha_desde, c.fecha_hasta
            FROM canchas c WHERE c.id_cancha = ?
        ");
        $stmt->execute([$id_cancha]);
        $cancha = $stmt->fetch();
        
        if (!$cancha) {
            error_log("ERROR: Cancha $id_cancha no encontrada en generación");
            return;
        }
        
        error_log("Datos cancha recuperados:");
        error_log("  Hora inicio: {$cancha['hora_inicio']}");
        error_log("  Hora fin: {$cancha['hora_fin']}");
        error_log("  Duración: {$cancha['duracion_bloque']}");
        error_log("  Fecha desde: {$cancha['fecha_desde']}");
        error_log("  Fecha hasta: {$cancha['fecha_hasta']}");
        error_log("  Días: {$cancha['dias_disponibles']}");
        
        // Eliminar disponibilidad existente
        $delete_result = $pdo->prepare("DELETE FROM disponibilidad_canchas WHERE id_cancha = ?")
            ->execute([$id_cancha]);
        error_log("Registros anteriores eliminados: " . ($delete_result ? 'SÍ' : 'NO'));
        
        // Parsear días
        $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
        if (!$dias_disponibles || !is_array($dias_disponibles)) {
            error_log("ERROR: Días no válidos para cancha $id_cancha");
            return;
        }
        
        error_log("Días parseados: " . implode(', ', $dias_disponibles));
        
        // Fechas
        $fecha_inicio = $cancha['fecha_desde'] ?: date('Y-m-d');
        $fecha_fin = $cancha['fecha_hasta'] ?: date('Y-m-d', strtotime('+365 days'));
        $hoy = date('Y-m-d');
        
        if ($fecha_inicio < $hoy) {
            $fecha_inicio = $hoy;
            error_log("Ajustando fecha inicio a hoy: $hoy");
        }
        
        error_log("Rango de generación calculado:");
        error_log("  Desde: $fecha_inicio");
        error_log("  Hasta: $fecha_fin");
        error_log("  Hoy: $hoy");
        
        // Mapeo de días
        $dias_semana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        
        // Generar para cada fecha
        $fecha_actual = $fecha_inicio;
        $fecha_limite = min($fecha_fin, date('Y-m-d', strtotime('+365 days')));
        
        error_log("Fecha límite final: $fecha_limite");
        
        $bloques_generados = 0;
        $dias_procesados = 0;
        
        while ($fecha_actual <= $fecha_limite) {
            $dia_semana_num = date('N', strtotime($fecha_actual)); // 1=lunes, 7=domingo
            $dia_nombre = $dias_semana[$dia_semana_num - 1];
            
            $dias_procesados++;
            if ($dias_procesados <= 5) { // Solo loguear los primeros 5 días para no saturar
                error_log("Procesando fecha: $fecha_actual ({$dia_nombre})");
            }
            
            if (in_array($dia_nombre, $dias_disponibles)) {
                if ($dias_procesados <= 5) {
                    error_log("  ✓ Generando bloques para $fecha_actual");
                }
                generarBloquesSimple($pdo, $cancha, $fecha_actual);
                $bloques_generados++;
            } else {
                if ($dias_procesados <= 5) {
                    error_log("  ✗ Saltando $fecha_actual (no está en días disponibles)");
                }
            }
            
            $fecha_actual = date('Y-m-d', strtotime($fecha_actual . ' +1 day'));
            
            // Safety break para evitar loops infinitos
            if ($dias_procesados > 400) {
                error_log("BREAK DE SEGURIDAD: Más de 400 días procesados");
                break;
            }
        }
        
        error_log("=== RESUMEN GENERACIÓN ===");
        error_log("Total días procesados: $dias_procesados");
        error_log("Total bloques generados: $bloques_generados");
        error_log("=== FIN GENERACIÓN ===");
        
    } catch (Exception $e) {
        error_log("ERROR CRÍTICO en generación simple cancha $id_cancha: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
}

function generarBloquesSimple($pdo, $cancha, $fecha) {
    try {
        $hora_inicio = strtotime($cancha['hora_inicio']);
        $hora_fin = strtotime($cancha['hora_fin']);
        $duracion = max(60, (int)$cancha['duracion_bloque']) * 60;
        
        $hora_actual = $hora_inicio;
        $bloques_del_dia = 0;
        
        while ($hora_actual < $hora_fin) {
            $hora_inicio_str = date('H:i:s', $hora_actual);
            $hora_fin_str = date('H:i:s', $hora_actual + $duracion);
            
            $pdo->prepare("
                INSERT INTO disponibilidad_canchas 
                (id_cancha, fecha, hora_inicio, hora_fin, estado)
                VALUES (?, ?, ?, ?, 'disponible')
            ")->execute([$cancha['id_cancha'], $fecha, $hora_inicio_str, $hora_fin_str]);
            
            $bloques_del_dia++;
            $hora_actual += $duracion;
        }
        
        // Solo loguear para depuración si es necesario
        // error_log("Generados $bloques_del_dia bloques para fecha $fecha");
        
    } catch (Exception $e) {
        error_log("Error en bloques simple fecha $fecha: " . $e->getMessage());
    }
}
?>