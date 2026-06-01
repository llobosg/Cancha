<?php
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/reserva_mailer.php';

class ReservaService {

    public static function crearRecurrente($pdo, $data) {
        try {
            date_default_timezone_set('America/Santiago');
            // Nota: Si config.php ya setea la zona horaria, esta línea es redundante pero segura
            $pdo->exec("SET time_zone = '-03:00'");

            $pdo->beginTransaction();

            $fechas = self::generarFechas(
                $data['tipo_patron'],
                $data['fecha_base'],
                $data['fecha_desde'],
                $data['fecha_hasta']
            );

            self::validarDisponibilidad(
                $pdo,
                $data['id_cancha'],
                $fechas,
                $data['hora_inicio']
            );

            // ✅ CORRECCIÓN 1: Obtener socio con su id_club
            $socio = self::getSocio($pdo, $data['id_socio']);
            $cancha = self::getCancha($pdo, $data['id_cancha']);

            $monto = ($data['monto'] > 0)
                ? $data['monto']
                : $cancha['valor_arriendo'];

            $reservas = [];

            foreach ($fechas as $fecha) {

                $hora_fin = self::calcularHoraFin($data['hora_inicio'], $data['duracion']);

                // ✅ CORRECCIÓN 2: Agregar id_club al INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        codigo_reserva, id_cancha, id_club, id_socio,
                        nombre_cliente, email_cliente, telefono_cliente,
                        fecha, hora_inicio, hora_fin,
                        tipo_reserva, tipo_arriendo,
                        monto_total, estado, estado_pago
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada','pendiente')
                ");

                $codigo = strtoupper(substr(uniqid(), -8));

                $tipos_validos = ['spot','semanal','mensual','campeonato','evento'];

                $tipo_reserva_db = ($data['tipo_patron'] === 'simple') 
                    ? 'spot' 
                    : (in_array($data['tipo_patron'], $tipos_validos) ? $data['tipo_patron'] : 'spot');

                // ✅ CORRECCIÓN 3: Pasar $socio['id_club'] al execute
                $stmt->execute([
                    $codigo,
                    $data['id_cancha'],
                    $socio['id_club'], // <--- AQUÍ SE GUARDA EL CLUB
                    $data['id_socio'],
                    $socio['nombre'],
                    $socio['email'],
                    $socio['celular'],
                    $fecha,
                    $data['hora_inicio'],
                    $hora_fin,
                    $tipo_reserva_db,
                    $tipo_reserva_db,
                    $monto
                ]);

                $id_reserva = $pdo->lastInsertId();

                // actualizar disponibilidad
                $pdo->prepare("
                    UPDATE disponibilidad_canchas 
                    SET estado='reservada', id_reserva=?
                    WHERE id_cancha=? AND fecha=? AND hora_inicio=?
                ")->execute([$id_reserva, $data['id_cancha'], $fecha, $data['hora_inicio']]);

                // BITÁCORA 🔥
                registrarLogReserva(
                    $pdo,
                    $id_reserva,
                    'creada',
                    "⏱️ {$data['duracion']} min | 💰 $" . number_format($monto, 0, ',', '.'),
                    $socio['nombre'],
                    [
                        'fecha' => $fecha,
                        'hora_inicio' => $data['hora_inicio'],
                        'hora_fin' => $hora_fin
                    ]
                );

                $reservas[] = $id_reserva;
            }

            // correo SOLO última reserva
            try {
                BrevoMailer::enviarConfirmacion($pdo, end($reservas));
            } catch (Exception $e) {
                error_log("[MAIL ERROR] " . $e->getMessage());
            }

            $pdo->commit();

            return ['success' => true, 'total' => count($reservas)];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function calcularHoraFin($hora_inicio, $duracion) {
        $h = new DateTime($hora_inicio);
        $h->modify("+{$duracion} minutes");
        return $h->format('H:i');
    }

    private static function generarFechas($tipo, $base, $desde, $hasta) {
        $fechas = [];

        if ($tipo === 'simple') return [$base];

        $f = new DateTime($desde);
        $fin = new DateTime($hasta);

        while ($f <= $fin) {
            $fechas[] = $f->format('Y-m-d');

            if ($tipo === 'semanal') $f->modify('+7 days');
            elseif ($tipo === 'quincenal') $f->modify('+15 days');
            elseif ($tipo === 'mensual') $f->modify('+1 month');
            else break;
        }

        return $fechas;
    }

    private static function validarDisponibilidad($pdo, $id_cancha, $fechas, $hora_inicio) {
        foreach ($fechas as $fecha) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM disponibilidad_canchas 
                WHERE id_cancha=? AND fecha=? AND hora_inicio=? AND estado!='disponible'
            ");
            $stmt->execute([$id_cancha, $fecha, $hora_inicio]);

            if ($stmt->fetch()['total'] > 0) {
                throw new Exception("Conflicto en fecha {$fecha}");
            }
        }
    }

    // ✅ CORRECCIÓN 4: Modificar getSocio para traer id_club
    private static function getSocio($pdo, $id) {
        $s = $pdo->prepare("SELECT nombre, email, celular, id_club FROM socios WHERE id_socio=?");
        $s->execute([$id]);
        $data = $s->fetch();
        // Asegurar que id_club sea null si está vacío para evitar errores de tipo
        if ($data) {
            $data['id_club'] = !empty($data['id_club']) ? $data['id_club'] : null;
        }
        return $data;
    }

    private static function getCancha($pdo, $id) {
        $c = $pdo->prepare("SELECT valor_arriendo FROM canchas WHERE id_cancha=?");
        $c->execute([$id]);
        return $c->fetch();
    }
}