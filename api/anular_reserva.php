<?php
// api/anular_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // Asegúrate que esta ruta sea correcta

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Validar sesión
if (!isset($_SESSION['id_recinto'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_recinto = (int)$_SESSION['id_recinto'];
$input = json_decode(file_get_contents('php://input'), true);
$id_reserva = (int)($input['id_reserva'] ?? 0);

try {
    if (!$id_reserva) {
        throw new Exception("ID de reserva requerido");
    }

    // 1. Obtener datos actuales de la reserva
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, s.email as email_cliente, s.nombre as nombre_cliente
        FROM reservas r
        JOIN canchas c ON r.id_cancha = c.id_cancha
        LEFT JOIN socios s ON r.id_socio = s.id_socio
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt->execute([$id_reserva, $id_recinto]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        throw new Exception("Reserva no encontrada o no pertenece a este recinto");
    }

    if ($reserva['estado'] === 'cancelada') {
        throw new Exception("La reserva ya está anulada");
    }

    // 2. Determinar acción financiera
    $mensaje_financiero = "";
    $nuevo_estado_pago = $reserva['estado_pago'];
    
    // Si estaba pagada o parcialmente pagada, marcamos como reembolsado (o pendiente de reembolso según política)
    // Aquí asumimos que al anular, el dinero debe ser devuelto o la deuda se borra.
    // Opción A: Marcar como 'reembolsado' si había cobrado algo.
    // Opción B: Marcar como 'pendiente' pero con estado 'cancelada' para auditoría.
    
    if ($reserva['monto_recaudacion'] > 0) {
        $nuevo_estado_pago = 'reembolsado'; // O 'pendiente_reembolso' si tienes ese enum
        $mensaje_financiero = "Se ha registrado un reembolso automático por la anulación.";
    } else {
        $nuevo_estado_pago = 'pendiente'; // Mantiene el estado original pero la reserva muere
    }

    // 3. Actualizar Reserva
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'cancelada', 
            estado_pago = ?, 
            notas = CONCAT(COALESCE(notas, ''), '\n[ANULADA POR ADMIN: ", NOW(), "]'),
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    $stmt_update->execute([$nuevo_estado_pago, $id_reserva]);

    // 4. Registrar Log de Auditoría
    $usuario_admin = $_SESSION['recinto_usuario'] ?? 'Admin';
    $stmt_log = $pdo->prepare("
        INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) 
        VALUES (?, ?, 'anulada', 'Anulada por admin. Estado previo: " . $reserva['estado'] . "', NOW())
    ");
    $stmt_log->execute([$id_reserva, $usuario_admin]);

    // 5. Enviar Correo al Cliente
    if (!empty($reserva['email_cliente']) && !empty($reserva['nombre_cliente'])) {
        try {
            $mail = new BrevoMailer(); // O tu clase de mail existente
            
            $titulo = "⚠️ Tu reserva ha sido anulada";
            $mensaje = "
                <p>Hola <strong>{$reserva['nombre_cliente']}</strong>,</p>
                <p>Lamentablemente, tu reserva en <strong>{$reserva['nombre_cancha']}</strong> para el día 
                <strong>{$reserva['fecha']} a las {$reserva['hora_inicio']}</strong> ha sido <strong>ANULADA</strong> por administración.</p>
                
                <p>" . ($mensaje_financiero ? "<strong>Nota Financiera:</strong> " . $mensaje_financiero : "") . "</p>
                
                <p>Si tienes alguna duda, por favor contáctanos.</p>
                <p>Saludos,<br>Equipo CanchaSport</p>
            ";
            
            $html = generarEmailHTML($titulo, $mensaje, "Contactar Soporte", "#"); // Botón dummy o link real
            
            $mail->setTo($reserva['email_cliente'], $reserva['nombre_cliente'])
                ->setSubject("Cancelación de Reserva #{$id_reserva}")
                ->setHtmlBody($html)
                ->send();
                
        } catch (Exception $e) {
            error_log("Error enviando correo anulación: " . $e->getMessage());
            // No fallamos la operación principal por error de mail
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Reserva anulada correctamente.'
    ]);

} catch (Exception $e) {
    error_log("❌ Error anular reserva: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>