<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $id_socio = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'];
    
    if ($action === 'anotarse') {
        $id_reserva = (int)$_POST['id_reserva'];
        $deporte = $_POST['deporte'];
        $players_max = (int)$_POST['players_max'];
        
        // Verificar que la reserva existe y pertenece al club
        // ¡ELIMINAR el filtro de tipo_reserva = 'evento'!
        $stmt = $pdo->prepare("
            SELECT r.id_reserva, r.id_club, r.fecha, r.hora_inicio, r.id_cancha, r.monto_total
            FROM reservas r 
            WHERE r.id_reserva = ? AND r.id_club = ? AND r.estado = 'confirmada'
        ");
        $stmt->execute([$id_reserva, $id_club]);
        $reserva = $stmt->fetch();
        
        if (!$reserva) {
            throw new Exception('Reserva no encontrada o no pertenece a tu club');
        }
        
        // Verificar si ya está inscrito
        $stmt_check = $pdo->prepare("SELECT id_inscrito FROM inscritos WHERE id_evento = ? AND id_socio = ?");
        $stmt_check->execute([$id_reserva, $id_socio]);
        $inscrito_existente = $stmt_check->fetch();
        
        if ($inscrito_existente) {
            // Ya está inscrito → dar de baja
            $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ?")
                 ->execute([$id_reserva, $id_socio]);
            echo json_encode(['success' => true, 'message' => 'Te has dado de baja del evento']);
            exit;
        }
        
        // Validar cupo solo para deportes específicos
        $deportes_con_cupo = ['futbolito', 'futsal', 'padel', 'tenis'];
        if (in_array($deporte, $deportes_con_cupo)) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ?");
            $stmt_count->execute([$id_reserva]);
            $total_inscritos = $stmt_count->fetch()['total'];
            
            if ($total_inscritos >= $players_max) {
                throw new Exception('Cupo lleno para este evento');
            }
        }
        
        // Obtener puesto del socio para determinar posición por defecto
        $stmt_puesto = $pdo->prepare("SELECT rol, genero FROM socios WHERE id_socio = ?");
        $stmt_puesto->execute([$id_socio]);
        $socio_info = $stmt_puesto->fetch();

        // Determinar posición por defecto según el rol
        $posicion_default = null;
        if ($socio_info) {
            $rol = $socio_info['rol'];
            if (strpos($rol, 'Arquero') !== false || strpos($rol, 'Portero') !== false) {
                $posicion_default = 'arquero';
            } elseif (strpos($rol, 'Defensa') !== false) {
                $posicion_default = 'defensa';
            } elseif (strpos($rol, 'Delantero') !== false) {
                $posicion_default = 'delantero';
            } elseif (strpos($rol, 'Medio') !== false || strpos($rol, 'Central') !== false) {
                $posicion_default = 'medio';
            }
        }

        // Determinar equipo por defecto (alternar según número de inscritos)
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ?");
        $stmt_count->execute([$id_reserva]);
        $total_inscritos = $stmt_count->fetch()['total'];

        $equipo_default = ($total_inscritos % 2 == 0) ? 'blanco' : 'azul';

        // Insertar en inscritos con valores por defecto
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, anotado, equipo, posicion_jugador)
            VALUES (?, ?, 1, ?, ?)
        ")->execute([$id_reserva, $id_socio, $equipo_default, $posicion_default]);

        // 🔔 NOTIFICAR A TODOS LOS SOCIOS DEL CLUB
        $stmt_socios = $pdo->prepare("
            SELECT id_socio, nombre, email 
            FROM socios 
            WHERE id_club = ? AND email_verified = 1
        ");
        $stmt_socios->execute([$id_club]);
        $socios_notificar = $stmt_socios->fetchAll();

        // Obtener nombre del socio que se anotó
        $nombre_inscrito = $socio_actual['nombre'] ?? 'Un jugador';

        // Enviar notificación a cada socio
        foreach ($socios_notificar as $socio) {
            if ($socio['id_socio'] == $id_socio) continue; // No notificarse a sí mismo
            
            // Web Push (si está suscrito)
            // Aquí iría la lógica de Firebase/Web Push si la tienes implementada
            
            // Email (opcional, solo si quieres notificar por correo también)
            /*
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo($socio['email'], $socio['nombre']);
            $mail->setSubject('⚽ Nueva inscripción en tu club');
            $mail->setHtmlBody("
                <p><strong>{$nombre_inscrito}</strong> se ha anotado al próximo evento.</p>
                <p>¡Ya casi armamos el equipo!</p>
            ");
            $mail->send(); // No detener el flujo si falla
            */
        }

        // Mensaje de éxito
        echo json_encode(['success' => true, 'message' => "✅ ¡Anotado! Se notificó a los socios del club."]);
        
        // Mensaje personalizado
        $fecha_formateada = date('d/m', strtotime($reserva['fecha']));
        $mensaje = "Listo....Anotado para {$fecha_formateada} {$reserva['hora_inicio']} Cancha {$reserva['id_cancha']}";
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>