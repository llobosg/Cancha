<?php
// includes/bitacora.php

function registrarLogReserva($pdo, $id_reserva, $accion, $descripcion, $usuario_nombre = null, $monto_ant = null, $monto_nue = null, $metadata = null) {
    try {
        // ✅ FORZAR ZONA HORARIA CHILE (Santiago) PARA EL LOG
        date_default_timezone_set('America/Santiago');
        
        if (!$usuario_nombre) {
            $usuario_nombre = $_SESSION['recinto_usuario'] ?? $_SESSION['nombre_completo'] ?? 'Admin/Sistema';
        }

       $fecha_chile = (new DateTime('now', new DateTimeZone('America/Santiago')))
        ->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO reservas_log (
                id_reserva, 
                usuario_nombre, 
                accion, 
                descripcion, 
                monto_anterior, 
                monto_nuevo, 
                metadata,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $id_reserva,
            $usuario_nombre,
            $accion,
            $descripcion,
            $monto_ant,
            $monto_nue,
            $metadata ? json_encode($metadata) : null,
            $fecha_chile // 🔥 AQUÍ VA LA HORA CORRECTA
        ]);
    } catch (Exception $e) {
        error_log("[Bitácora] Error al registrar log para reserva {$id_reserva}: " . $e->getMessage());
    }
}
?>