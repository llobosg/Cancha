<?php
require_once __DIR__ . '/../includes/config.php';

function generarDisponibilidad($pdo, $forzar = false, $dias_adelantados = 30) {
    try {
        // Asegurar que $dias_adelantados sea un número válido
        $dias_adelantados = (int)$dias_adelantados;
        if ($dias_adelantados <= 0) {
            $dias_adelantados = 30;
        }
        
        // Obtener canchas con rango de fechas definido
        $stmt = $pdo->prepare("
            SELECT 
                c.id_cancha,
                c.hora_inicio,
                c.hora_fin, 
                c.duracion_bloque,
                c.dias_disponibles,
                c.fecha_desde,
                c.fecha_hasta,
                c.id_recinto
            FROM canchas c
            WHERE c.activa = 1 
            AND c.estado = 'operativa'
            AND c.fecha_desde IS NOT NULL 
            AND c.fecha_hasta IS NOT NULL
            AND c.fecha_hasta >= CURDATE()
        ");
        $stmt->execute();
        $canchas = $stmt->fetchAll();
        
        if (empty($canchas)) {
            return ['success' => true, 'message' => 'No hay canchas con fechas definidas'];
        }
        
        $total_generados = 0;
        
        foreach ($canchas as $cancha) {
            $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
            if (!$dias_disponibles || !is_array($dias_disponibles)) {
                continue;
            }
            
            // Usar fechas desde/hasta de la cancha (prioridad)
            $fecha_inicio_cancha = new DateTime($cancha['fecha_desde']);
            $fecha_fin_cancha = new DateTime($cancha['fecha_hasta']);
            
            // Limitar el rango si es muy largo
            $hoy = new DateTime();
            $fecha_limite_max = clone $hoy;
            $fecha_limite_max->modify("+$dias_adelantados days");
            
            $fecha_inicio = $fecha_inicio_cancha > $hoy ? $fecha_inicio_cancha : $hoy;
            $fecha_fin = $fecha_fin_cancha < $fecha_limite_max ? $fecha_fin_cancha : $fecha_limite_max;
            
            // Mapeo de días
            $dias_espanol = [
                1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
                4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 7 => 'domingo'
            ];
            
            $fecha_actual = clone $fecha_inicio;
            while ($fecha_actual <= $fecha_fin) {
                $dia_numero = (int)$fecha_actual->format('N');
                $dia_actual = $dias_espanol[$dia_numero];
                
                if (in_array($dia_actual, $dias_disponibles)) {
                    $bloques = generarBloquesParaFecha(
                        $pdo, 
                        $cancha, 
                        $fecha_actual->format('Y-m-d'),
                        $forzar
                    );
                    $total_generados += $bloques;
                }
                
                $fecha_actual->modify('+1 day');
            }
        }
        
        return [
            'success' => true, 
            'message' => "Disponibilidad generada: $total_generados bloques"
        ];
        
    } catch (Exception $e) {
        error_log("Error generando disponibilidad: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function generarBloquesParaFecha($pdo, $cancha, $fecha) {
    try {
        $hora_inicio = strtotime($cancha['hora_inicio']);
        $hora_fin = strtotime($cancha['hora_fin']);
        $duracion_minutos = (int)$cancha['duracion_bloque'];
        $duracion_segundos = $duracion_minutos * 60;
        
        if ($duracion_minutos <= 0) {
            $duracion_segundos = 3600; // 1 hora por defecto
        }
        
        $hora_actual = $hora_inicio;
        $bloques_generados = 0;
        
        while ($hora_actual < $hora_fin) {
            $hora_inicio_bloque = date('H:i:s', $hora_actual);
            $hora_fin_bloque = date('H:i:s', $hora_actual + $duracion_segundos);
            
            // Verificar si ya existe un bloque para esta hora
            $stmt_check = $pdo->prepare("
                SELECT id_disponibilidad 
                FROM disponibilidad_canchas 
                WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ?
            ");
            $stmt_check->execute([$cancha['id_cancha'], $fecha, $hora_inicio_bloque]);
            
            if (!$stmt_check->fetch()) {
                // Insertar nuevo bloque
                $stmt_insert = $pdo->prepare("
                    INSERT INTO disponibilidad_canchas 
                    (id_cancha, fecha, hora_inicio, hora_fin, estado)
                    VALUES (?, ?, ?, ?, 'disponible')
                ");
                $stmt_insert->execute([
                    $cancha['id_cancha'],
                    $fecha,
                    $hora_inicio_bloque,
                    $hora_fin_bloque
                ]);
                $bloques_generados++;
            }
            
            $hora_actual += $duracion_segundos;
        }
        
        return $bloques_generados;
        
    } catch (Exception $e) {
        error_log("Error generando bloques para fecha $fecha: " . $e->getMessage());
        return 0;
    }
}

// Ejecutar generación
if (php_sapi_name() === 'cli') {
    // Ejecución desde línea de comandos
    $resultado = generarDisponibilidad($pdo, 30);
    echo json_encode($resultado) . "\n";
} else {
    // Ejecución desde web (requiere autenticación)
    header('Content-Type: application/json');
    
    // Aquí podrías agregar validación de admin
    $resultado = generarDisponibilidad($pdo, 30);
    echo json_encode($resultado);
}
?>