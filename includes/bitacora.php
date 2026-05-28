<?php
// includes/bitacora.php

/**
 * Registra una acción en la bitácora de reservas
 */
function registrarLogReserva($pdo, $id_reserva, $accion, $descripcion, $usuario_nombre = null, $monto_ant = null, $monto_nue = null, $metadata = null) {
    try {
        // Si no se pasa usuario, intentar tomarlo de la sesión o poner 'Sistema'
        if (!$usuario_nombre) {
            $usuario_nombre = $_SESSION['recinto_usuario'] ?? $_SESSION['nombre_completo'] ?? 'Admin/Sistema';
        }

        $stmt = $pdo->prepare("
            INSERT INTO reservas_log (
                id_reserva, 
                usuario_nombre, 
                accion, 
                descripcion, 
                monto_anterior, 
                monto_nuevo, 
                metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id_reserva,
            $usuario_nombre,
            $accion,
            $descripcion,
            $monto_ant,
            $monto_nue,
            $metadata ? json_encode($metadata) : null
        ]);
    } catch (Exception $e) {
        // No interrumpimos el flujo principal si falla el log, solo registramos error
        error_log("[Bitácora] Error al registrar log para reserva {$id_reserva}: " . $e->getMessage());
    }
}
?>