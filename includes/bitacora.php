<?php
function registrarLogReserva(
    $pdo,
    $id_reserva,
    $accion,
    $descripcion,
    $usuario_nombre = null,
    $monto_ant = null,
    $monto_nue = null,
    $metadata = null
) {
    try {

        if (!$usuario_nombre) {
            $usuario_nombre = $_SESSION['nombre_completo']
                ?? $_SESSION['recinto_usuario']
                ?? 'Sistema';
        }

        // 🔥 SANITIZAR TODO (CLAVE)
        $descripcion = is_array($descripcion) 
            ? json_encode($descripcion, JSON_UNESCAPED_UNICODE) 
            : (string)$descripcion;

        $usuario_nombre = is_array($usuario_nombre)
            ? json_encode($usuario_nombre)
            : (string)$usuario_nombre;

        $monto_ant = is_array($monto_ant) ? null : $monto_ant;
        $monto_nue = is_array($monto_nue) ? null : $monto_nue;

        $metadata_json = is_array($metadata)
            ? json_encode($metadata, JSON_UNESCAPED_UNICODE)
            : $metadata;

        // 🔥 Hora Chile REAL
        $fechaChile = date('Y-m-d H:i:s');

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
            $metadata_json,
            $fechaChile
        ]);

        error_log("✅ Bitácora OK reserva {$id_reserva}");

    } catch (Exception $e) {
        error_log("❌ Bitácora ERROR: " . $e->getMessage());
        return false;
    }
}