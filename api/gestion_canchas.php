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
                
                // Obtener el ID de la cancha recién insertada
                $id_cancha = $pdo->lastInsertId();
                
                // Actualizar fechas después de la inserción
                $pdo->prepare("
                    UPDATE canchas 
                    SET fecha_desde = CURDATE(), 
                        fecha_hasta = DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
                    WHERE id_cancha = ?
                ")->execute([$id_cancha]);
                
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
        if ($action === 'insert') {
            $id_cancha = $pdo->lastInsertId();
        } else {
            $id_cancha = (int)($_POST['id_cancha'] ?? 0);
        }
        
        if ($id_cancha) {
            generarDisponibilidadSimple($pdo, $id_cancha);
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

// === FUNCIÓN SIMPLE DE GENERACIÓN ===
function generarDisponibilidadSimple($pdo, $id_cancha) {
    try {
        // Obtener datos de la cancha
        $stmt = $pdo->prepare("
            SELECT 
                c.id_cancha, c.hora_inicio, c.hora_fin, c.duracion_bloque,
                c.dias_disponibles, c.fecha_desde, c.fecha_hasta
            FROM canchas c WHERE c.id_cancha = ?
        ");
        $stmt->execute([$id_cancha]);
        $cancha = $stmt->fetch();
        
        if (!$cancha) return;
        
        // Eliminar disponibilidad existente
        $pdo->prepare("DELETE FROM disponibilidad_canchas WHERE id_cancha = ?")
            ->execute([$id_cancha]);
        
        // Parsear días
        $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
        if (!$dias_disponibles || !is_array($dias_disponibles)) {
            return;
        }
        
        // Fechas
        $fecha_inicio = $cancha['fecha_desde'] ?: date('Y-m-d');
        $fecha_fin = $cancha['fecha_hasta'] ?: date('Y-m-d', strtotime('+365 days'));
        $hoy = date('Y-m-d');
        
        if ($fecha_inicio < $hoy) {
            $fecha_inicio = $hoy;
        }
        
        // Mapeo de días
        $dias_semana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        
        // Generar para cada fecha
        $fecha_actual = $fecha_inicio;
        $fecha_limite = min($fecha_fin, date('Y-m-d', strtotime('+365 days')));
        
        while ($fecha_actual <= $fecha_limite) {
            $dia_semana_num = date('N', strtotime($fecha_actual)); // 1=lunes, 7=domingo
            $dia_nombre = $dias_semana[$dia_semana_num - 1];
            
            if (in_array($dia_nombre, $dias_disponibles)) {
                generarBloquesSimple($pdo, $cancha, $fecha_actual);
            }
            
            $fecha_actual = date('Y-m-d', strtotime($fecha_actual . ' +1 day'));
        }
        
    } catch (Exception $e) {
        error_log("Error generación simple cancha $id_cancha: " . $e->getMessage());
    }
}

function generarBloquesSimple($pdo, $cancha, $fecha) {
    try {
        $hora_inicio = strtotime($cancha['hora_inicio']);
        $hora_fin = strtotime($cancha['hora_fin']);
        $duracion = max(60, (int)$cancha['duracion_bloque']) * 60;
        
        $hora_actual = $hora_inicio;
        while ($hora_actual < $hora_fin) {
            $hora_inicio_str = date('H:i:s', $hora_actual);
            $hora_fin_str = date('H:i:s', $hora_actual + $duracion);
            
            $pdo->prepare("
                INSERT INTO disponibilidad_canchas 
                (id_cancha, fecha, hora_inicio, hora_fin, estado)
                VALUES (?, ?, ?, ?, 'disponible')
            ")->execute([$cancha['id_cancha'], $fecha, $hora_inicio_str, $hora_fin_str]);
            
            $hora_actual += $duracion;
        }
        
    } catch (Exception $e) {
        error_log("Error bloques simple fecha $fecha: " . $e->getMessage());
    }
}
?>