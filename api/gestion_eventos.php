<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

session_start();

if (!isset($_SESSION['id_socio']) || !isset($_SESSION['club_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $id_socio = $_SESSION['id_socio'];
    $id_club = $_SESSION['club_id'];
    $club_slug = $_SESSION['current_club'] ?? '';
    
    if ($action !== 'anotarse') {
        throw new Exception('Acción no válida');
    }
    
    $id_reserva = (int)($_POST['id_reserva'] ?? 0);
    $deporte = $_POST['deporte'] ?? '';
    $players_max = (int)($_POST['players_max'] ?? 0);
    
    if (!$id_reserva) {
        throw new Exception('Reserva inválida');
    }
    
    // Verificar que la reserva existe y pertenece al club
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
    $ya_inscrito = $stmt_check->fetch();
    
    if ($ya_inscrito) {
        // Dar de baja
        $pdo->prepare("DELETE FROM inscritos WHERE id_evento = ? AND id_socio = ?")
             ->execute([$id_reserva, $id_socio]);
        
        $accion = 'bajado';
        $mensaje = "✅ Te has dado de baja del evento";
    } else {
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
        
        // Determinar equipo por defecto
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM inscritos WHERE id_evento = ?");
        $stmt_count->execute([$id_reserva]);
        $total_inscritos = $stmt_count->fetch()['total'];
        $equipo_default = ($total_inscritos % 2 == 0) ? 'blanco' : 'azul';
        
        // Insertar en inscritos
        $pdo->prepare("
            INSERT INTO inscritos (id_evento, id_socio, anotado, equipo, posicion_jugador)
            VALUES (?, ?, 1, ?, ?)
        ")->execute([$id_reserva, $id_socio, $equipo_default, $posicion_default]);
        
        $accion = 'anotado';
        $mensaje = "✅ ¡Inscripción confirmada!";
    }
    
    // === NOTIFICACIONES POR CORREO (solo a otros socios del club) ===
    $stmt_nombre = $pdo->prepare("SELECT nombre FROM socios WHERE id_socio = ?");
    $stmt_nombre->execute([$id_socio]);
    $nombre_inscrito = $stmt_nombre->fetch()['nombre'] ?? 'Un jugador';
    
    $fecha_formateada = date('d/m', strtotime($reserva['fecha']));
    
    // Obtener socios del club (excepto el que actuó)
    $stmt_socios = $pdo->prepare("
        SELECT id_socio, email, nombre 
        FROM socios 
        WHERE id_club = ? AND id_socio != ? AND email_verified = 1
    ");
    $stmt_socios->execute([$id_club, $id_socio]);
    $socios_notificar = $stmt_socios->fetchAll();
    
    foreach ($socios_notificar as $socio) {
        try {
            require_once __DIR__ . '/../includes/brevo_mailer.php';
            $mail = new BrevoMailer();
            $mail->setTo($socio['email'], $socio['nombre']);
            $mail->setSubject('⚽ Actualización en tu club: ' . ($accion === 'bajado' ? 'baja' : 'nueva inscripción'));
            $mail->setHtmlBody("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 12px;'>
                    <div style='text-align: center; background: #071289; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                        <h2>🔔 CanchaSport</h2>
                    </div>
                    <p style='font-size: 1.1rem; line-height: 1.5;'>
                        <strong>{$nombre_inscrito}</strong> se ha " . ($accion === 'bajado' ? "<span style='color:#E74C3C;'>dado de baja</span>" : "<span style='color:#2ECC71;'>anotado</span>") . " al próximo evento.
                    </p>
                    <p style='font-size: 1rem; color: #555;'>
                        <strong>Fecha:</strong> {$fecha_formateada} | <strong>Hora:</strong> {$reserva['hora_inicio']}
                    </p>
                    <p style='margin-top: 20px; text-align: center;'>
                        <a href='https://canchasport.com/pages/dashboard_socio.php?id_club={$club_slug}' 
                           style='background: #071289; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                            Ver dashboard
                        </a>
                    </p>
                    <hr style='margin: 25px 0; border: 0; border-top: 1px solid #eee;'>
                    <p style='text-align: center; font-size: 0.9rem; color: #888;'>
                        Este mensaje fue generado automáticamente. Por favor, no respondas a este correo.
                    </p>
                </div>
            ");
            $mail->send(); // No detener si falla
        } catch (Exception $e) {
            error_log("Error notificando a {$socio['email']}: " . $e->getMessage());
        }
    }
    
    // Mensaje único para el usuario que actuó
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    error_log("Gestión eventos error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>