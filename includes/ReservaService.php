<?php
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/reserva_mailer.php'; // Asegúrate que este archivo exista o comenta esta línea si no lo usas

class ReservaService {

    public static function crearRecurrente($pdo, $data) {
        try {
            date_default_timezone_set('America/Santiago');
            $pdo->beginTransaction();

            $fechas = self::generarFechas(
                $data['tipo_patron'],
                $data['fecha_base'] ?? null,
                $data['fecha_desde'],
                $data['fecha_hasta']
            );

            self::validarDisponibilidad(
                $pdo,
                $data['id_cancha'],
                $fechas,
                $data['hora_inicio']
            );

            $socio = self::getSocio($pdo, $data['id_socio']);
            $cancha = self::getCancha($pdo, $data['id_cancha']);

            $monto = ($data['monto'] > 0) ? $data['monto'] : $cancha['valor_arriendo'];
            $reservas = [];
            $codigo = strtoupper(substr(uniqid(), -8));

            foreach ($fechas as $fecha) {
                $hora_fin = self::calcularHoraFin($data['hora_inicio'], $data['duracion']);
                
                // Determinar tipo de reserva
                $tipos_validos = ['spot','semanal','mensual','campeonato','evento'];
                $tipo_reserva_db = ($data['tipo_patron'] === 'simple') 
                    ? 'spot' 
                    : (in_array($data['tipo_patron'], $tipos_validos) ? $data['tipo_patron'] : 'spot');

                // Obtener capacidad
                $stmt_cap = $pdo->prepare("SELECT capacidad_jugadores FROM canchas WHERE id_cancha = ?");
                $stmt_cap->execute([$data['id_cancha']]);
                $cap_data = $stmt_cap->fetch(PDO::FETCH_ASSOC);
                $jugadores_esperados = intval($cap_data['capacidad_jugadores'] ?? 4);

                // Insertar
                $stmt = $pdo->prepare("
                    INSERT INTO reservas (
                        codigo_reserva, id_cancha, id_club, id_socio,
                        nombre_cliente, email_cliente, telefono_cliente,
                        fecha, hora_inicio, hora_fin,
                        tipo_reserva, tipo_arriendo,
                        monto_total, estado, estado_pago, jugadores_esperados
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', 'pendiente', ?)
                ");

                $stmt->execute([
                    $codigo,
                    $data['id_cancha'],
                    $socio['id_club'] ?? null, // Usar el club del socio si existe
                    $data['id_socio'],
                    $socio['nombre'],
                    $socio['email'],
                    $socio['celular'],
                    $fecha,
                    $data['hora_inicio'],
                    $hora_fin,
                    $tipo_reserva_db,
                    $tipo_reserva_db,
                    $monto,
                    $jugadores_esperados
                ]);

                $id_reserva = $pdo->lastInsertId();
                $reservas[] = $id_reserva;

                // Bitácora
                if (function_exists('registrarLogReserva')) {
                    registrarLogReserva(
                        $pdo, $id_reserva, 'creada_recurrente', 
                        "Reserva generada por servicio recurrente", 
                        $socio['nombre'], null, $monto
                    );
                }
            }

            // Enviar correo de confirmación (solo uno al final)
            if (!empty($reservas) && class_exists('BrevoMailer')) {
                try {
                    BrevoMailer::enviarConfirmacion($pdo, end($reservas));
                } catch (Exception $e) {
                    error_log("[MAIL ERROR] " . $e->getMessage());
                }
            }

            $pdo->commit();
            return ['success' => true, 'total' => count($reservas)];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
            // Nota: Si no usas la tabla disponibilidad_canchas, comenta esto o adáptalo a la tabla reservas
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM reservas 
                WHERE id_cancha=? AND fecha=? AND hora_inicio=? AND estado != 'cancelada'
            ");
            $stmt->execute([$id_cancha, $fecha, $hora_inicio]);

            if ($stmt->fetch()['total'] > 0) {
                throw new Exception("Conflicto en fecha {$fecha} a las {$hora_inicio}");
            }
        }
    }

    private static function getSocio($pdo, $id) {
        $s = $pdo->prepare("SELECT nombre, email, celular, id_club FROM socios WHERE id_socio=?");
        $s->execute([$id]);
        $data = $s->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $data['id_club'] = !empty($data['id_club']) ? $data['id_club'] : null;
        }
        return $data;
    }

    private static function getCancha($pdo, $id) {
        $c = $pdo->prepare("SELECT valor_arriendo FROM canchas WHERE id_cancha=?");
        $c->execute([$id]);
        return $c->fetch(PDO::FETCH_ASSOC);
    }
}
?>