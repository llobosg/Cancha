<?php
require_once __DIR__ . '/../includes/config.php';

function generarDisponibilidad($pdo, $dias_adelantados = 30) {
    try {
        // Obtener todas las canchas activas con su configuración
        $stmt = $pdo->prepare("
            SELECT 
                c.id_cancha,
                c.hora_inicio,
                c.hora_fin, 
                c.duracion_bloque,
                c.dias_disponibles,
                c.id_recinto
            FROM canchas c
            WHERE c.activa = 1 AND c.estado = 'operativa'
        ");
        $stmt->execute();
        $canchas = $stmt->fetchAll();
        
        if (empty($canchas)) {
            return ['success' => true, 'message' => 'No hay canchas activas'];
        }
        
        $hoy = new DateTime();
        $fecha_fin = clone $hoy;
        $fecha_fin->modify("+$dias_adelantados days");
        
        $total_generados = 0;
        
        foreach ($canchas as $cancha) {
            $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
            if (!$dias_disponibles || !is_array($dias_disponibles)) {
                continue;
            }
            
            // Mapeo de días en español
            $dias_espanol = [
                1 => 'lunes',
                2 => 'martes', 
                3 => 'miercoles',
                4 => 'jueves',
                5 => 'viernes',
                6 => 'sabado',
                7 => 'domingo'
            ];
            
            $fecha_actual = clone $hoy;
            while ($fecha_actual <= $fecha_fin) {
                $dia_numero = (int)$fecha_actual->format('N'); // 1=lunes, 7=domingo
                $dia_actual = $dias_espanol[$dia_numero];
                
                if (in_array($dia_actual, $dias_disponibles)) {
                    $bloques_generados = generarBloquesParaFecha(
                        $pdo, 
                        $cancha, 
                        $fecha_actual->format('Y-m-d')
                    );
                    $total_generados += $bloques_generados;
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