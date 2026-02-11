<?php
// includes/reservas_recurrentes.php

// En includes/disponibilidad.php, línea 13 aproximadamente:
$reservas_recurrentes = getReservasRecurrentesEnRango($pdo, $fecha_inicio, $fecha_fin);

// Verificar que sea un array antes de iterar
if (is_array($reservas_recurrentes) && !empty($reservas_recurrentes)) {
    foreach ($reservas_recurrentes as $reserva) {
        marcarSlotsOcupados($pdo, $reserva, $fecha_inicio, $fecha_fin);
    }
}

function getReservasRecurrentesEnRango($pdo, $fecha_inicio, $fecha_fin) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM reservas_recurrentes 
            WHERE estado = 'activa'
            AND fecha_inicio <= ?
            AND fecha_fin >= ?
        ");
        $stmt->execute([$fecha_fin, $fecha_inicio]);
        $result = $stmt->fetchAll();
        
        // Siempre devolver array, nunca null
        return $result ?: [];
        
    } catch (Exception $e) {
        error_log("Error en getReservasRecurrentesEnRango: " . $e->getMessage());
        return []; // Devolver array vacío en caso de error
    }
}

function validarConvenioDisponible($pdo, $reserva_data) {
    // Validar que no haya conflictos
}

function cancelarConvenio($pdo, $id_convenio) {
    // Cancelar convenio y liberar slots futuros
}

function crearConvenio($pdo, $datos_convenio) {
    // Crear nuevo convenio
}
?>