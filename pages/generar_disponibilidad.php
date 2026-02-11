<?php
require_once __DIR__ . '/includes/config.php';

function generarDisponibilidadSemanal($pdo) {
    // Obtener todas las canchas activas
    $stmt = $pdo->prepare("
        SELECT c.id_cancha, c.hora_inicio, c.hora_fin, c.duracion_bloque, c.dias_disponibles
        FROM canchas c
        WHERE c.activa = 1 AND c.estado = 'operativa'
    ");
    $stmt->execute();
    $canchas = $stmt->fetchAll();
    
    $hoy = new DateTime();
    $fin_semana = clone $hoy;
    $fin_semana->modify('+7 days');
    
    foreach ($canchas as $cancha) {
        $dias_disponibles = json_decode($cancha['dias_disponibles'], true);
        if (!$dias_disponibles) continue;
        
        $fecha_actual = clone $hoy;
        while ($fecha_actual <= $fin_semana) {
            $dia_semana = strtolower($fecha_actual->format('l'));
            $dia_numero = $fecha_actual->format('N'); // 1=lunes, 7=domingo
            
            // Mapeo español
            $dias_es = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            $dia_actual = $dias_es[$dia_numero - 1];
            
            if (in_array($dia_actual, $dias_disponibles)) {
                generarBloquesHorarios($pdo, $cancha, $fecha_actual->format('Y-m-d'));
            }
            
            $fecha_actual->modify('+1 day');
        }
    }
}

function generarBloquesHorarios($pdo, $cancha, $fecha) {
    $hora_inicio = strtotime($cancha['hora_inicio']);
    $hora_fin = strtotime($cancha['hora_fin']);
    $duracion = (int)$cancha['duracion_bloque'] * 60; // minutos a segundos
    
    $hora_actual = $hora_inicio;
    while ($hora_actual < $hora_fin) {
        $hora_inicio_bloque = date('H:i:s', $hora_actual);
        $hora_fin_bloque = date('H:i:s', $hora_actual + $duracion);
        
        // Verificar si ya existe
        $stmt_check = $pdo->prepare("
            SELECT id_disponibilidad FROM disponibilidad_canchas 
            WHERE id_cancha = ? AND fecha = ? AND hora_inicio = ?
        ");
        $stmt_check->execute([$cancha['id_cancha'], $fecha, $hora_inicio_bloque]);
        
        if (!$stmt_check->fetch()) {
            $stmt_insert = $pdo->prepare("
                INSERT INTO disponibilidad_canchas (id_cancha, fecha, hora_inicio, hora_fin, estado)
                VALUES (?, ?, ?, ?, 'disponible')
            ");
            $stmt_insert->execute([
                $cancha['id_cancha'], 
                $fecha, 
                $hora_inicio_bloque, 
                $hora_fin_bloque
            ]);
        }
        
        $hora_actual += $duracion;
    }
}

// Ejecutar generación
generarDisponibilidadSemanal($pdo);
echo "✅ Disponibilidad generada correctamente";
?>