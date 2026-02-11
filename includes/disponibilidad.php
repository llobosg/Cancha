<?php
// includes/disponibilidad.php

function getDisponibilidad($pdo, $fecha_inicio, $fecha_fin) {
    // Generar disponibilidad base si no existe
    generarDisponibilidadMaterializada($pdo, $fecha_inicio, $fecha_fin);
    
    // Obtener reservas recurrentes activas
    require_once __DIR__ . '/reservas_recurrentes.php';
    $reservas_recurrentes = getReservasRecurrentesEnRango($pdo, $fecha_inicio, $fecha_fin);
    
    // Marcar slots ocupados por convenios
    foreach ($reservas_recurrentes as $reserva) {
        marcarSlotsOcupados($pdo, $reserva, $fecha_inicio, $fecha_fin);
    }
    
    return consultarDisponibilidadReal($pdo, $fecha_inicio, $fecha_fin);
}

function generarDisponibilidadMaterializada($pdo, $fecha_inicio, $fecha_fin) {
    // Lógica para generar bloques horarios
    // (la que ya tienes implementada)
}

function consultarDisponibilidadReal($pdo, $fecha_inicio, $fecha_fin) {
    $stmt = $pdo->prepare("
        SELECT 
            dc.id_disponibilidad,
            dc.id_cancha,
            c.nombre_cancha as nro_cancha,
            c.id_deporte,
            c.valor_arriendo,
            dc.fecha,
            dc.hora_inicio,
            dc.hora_fin,
            r.nombre as recinto_nombre,
            dc.estado
        FROM disponibilidad_canchas dc
        JOIN canchas c ON dc.id_cancha = c.id_cancha
        JOIN recintos_deportivos r ON c.id_recinto = r.id_recinto
        WHERE dc.fecha BETWEEN ? AND ?
        AND dc.estado = 'disponible'
        ORDER BY dc.fecha, dc.hora_inicio
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    return $stmt->fetchAll();
}
?>