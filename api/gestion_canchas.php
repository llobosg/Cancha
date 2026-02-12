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
    
    if ($id_recinto !== $_SESSION['id_recinto']) {
        throw new Exception('Acceso no autorizado al recinto');
    }
    
    if ($action !== 'insert' && $action !== 'update' && $action !== 'delete') {
        throw new Exception('Acción no válida');
    }
    
    // ... resto de tu lógica de validación y guardado ...
    
    switch ($action) {
        case 'insert':
        case 'update':
            // ... tu código de validación existente ...
            
            if ($action === 'insert') {
                $stmt = $pdo->prepare("
                    INSERT INTO canchas (
                        id_recinto, id_deporte, nro_cancha, nombre_cancha, 
                        valor_arriendo, capacidad_jugadores, duracion_bloque, 
                        hora_inicio, hora_fin, dias_disponibles, activa, estado,
                        fecha_desde, fecha_hasta
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))
                ");
                $stmt->execute([
                    $id_recinto, $id_deporte, $nro_cancha, $nombre_cancha,
                    $valor_arriendo, $capacidad_jugadores, $duracion_bloque,
                    $hora_inicio, $hora_fin, $dias_json, $activa, $estado
                ]);
            } else {
                // ... tu código de update existente ...
            }
            break;
            
        case 'delete':
            // ... tu código de delete existente ...
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
            // Generar disponibilidad simple sin funciones complejas
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