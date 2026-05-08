<?php
// api/anular_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

// Verificar si existe el mailer, si no, crear una clase dummy para evitar errores fatales
if (!file_exists(__DIR__ . '/../includes/reserva_mailer.php')) {
    class BrevoMailer { public function setTo(){} public function setSubject(){} public function setHtmlBody(){} public function send(){ return true; } }
} else {
    require_once __DIR__ . '/../includes/reserva_mailer.php';
}

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
    
    // Si estaba pagada o parcialmente pagada, marcamos como reembolsado
    if ($reserva['monto_recaudacion'] > 0) {
        $nuevo_estado_pago = 'reembolsado'; 
        $mensaje_financiero = "Se ha registrado un reembolso automático por la anulación.";
    } else {
        $nuevo_estado_pago = 'pendiente'; 
    }

    // 3. Actualizar Reserva
    // ✅ CORRECCIÓN: Usar concatenación de strings en PHP antes de enviar al SQL
    $fecha_anulacion = date('Y-m-d H:i:s');
    $nota_anulacion = "\n[ANULADA POR ADMIN: {$fecha_anulacion}]";
    
    // Obtenemos las notas actuales primero para concatenarlas correctamente en PHP o SQL puro
    $notas_actuales = $reserva['notas'] ?? '';
    $nuevas_notas = $notas_actuales . $nota_anulacion;

    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'cancelada', 
            estado_pago = ?, 
            notas = ?,
            updated_at = NOW()
        WHERE id_reserva = ?
    ");
    
    $stmt_update->execute([
        $nuevo_estado_pago,
        $nuevas_notas,
        $id_reserva
    ]);

    // 4. Registrar Log de Auditoría
    $usuario_admin = $_SESSION['recinto_usuario'] ?? 'Admin';
    $descripcion_log = "Anulada por admin. Estado previo: " . $reserva['estado'];
    
    $stmt_log = $pdo->prepare("
        INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, created_at) 
        VALUES (?, ?, 'anulada', ?, NOW())
    ");
    $stmt_log->execute([$id_reserva, $usuario_admin, $descripcion_log]);

    // 5. Enviar Correo al Cliente
    if (!empty($reserva['email_cliente']) && !empty($reserva['nombre_cliente'])) {
        try {
            // Verificar si la función de HTML existe, sino usar fallback simple
            $html_content = "<html><body><h1>⚠️ Tu reserva ha sido anulada</h1>";
            $html_content .= "<p>Hola <strong>{$reserva['nombre_cliente']}</strong>,</p>";
            $html_content .= "<p>Lamentablemente, tu reserva en <strong>{$reserva['nombre_cancha']}</strong> para el día <strong>{$reserva['fecha']} a las {$reserva['hora_inicio']}</strong> ha sido <strong>ANULADA</strong> por administración.</p>";
            
            if ($mensaje_financiero) {
                $html_content .= "<p><strong>Nota Financiera:</strong> " . $mensaje_financiero . "</p>";
            }
            
            $html_content .= "<p>Si tienes alguna duda, por favor contáctanos.</p>";
            $html_content .= "<p>Saludos,<br>Equipo CanchaSport</p></body></html>";

            // Intentar usar el mailer si existe
            if (class_exists('BrevoMailer')) {
                $mail = new BrevoMailer();
                $mail->setTo($reserva['email_cliente'], $reserva['nombre_cliente'])
                    ->setSubject("Cancelación de Reserva #{$id_reserva}")
                    ->setHtmlBody($html_content)
                    ->send();
            }
                
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