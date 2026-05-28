<?php
// api/anular_reserva.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reserva_mailer.php'; // Asegurar carga de mailer

// 1. Validar Sesión y Permisos
if (!isset($_SESSION['id_recinto']) || !in_array($_SESSION['recinto_rol'] ?? '', ['admin', 'asistente'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_reserva = (int)($input['id_reserva'] ?? 0);

if (!$id_reserva) {
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
    exit;
}

try {
    // 2. Obtener datos actuales de la reserva para validar propiedad y estado
    $stmt_check = $pdo->prepare("
        SELECT r.*, c.nombre_cancha, c.id_recinto 
        FROM reservas r 
        JOIN canchas c ON r.id_cancha = c.id_cancha 
        WHERE r.id_reserva = ? AND c.id_recinto = ?
    ");
    $stmt_check->execute([$id_reserva, $_SESSION['id_recinto']]);
    $reserva = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada o no pertenece a este recinto']);
        exit;
    }

    if ($reserva['estado'] === 'cancelada') {
        echo json_encode(['success' => false, 'message' => 'La reserva ya está anulada']);
        exit;
    }

    // 3. Determinar nuevo estado de pago según lógica financiera
    // Si estaba pagada, se marca como 'reembolsado' o se mantiene 'pagado' pero con nota.
    // Si estaba pendiente/parcial, se mantiene así pero se cancela la reserva.
    $nuevo_estado_pago = $reserva['estado_pago'];
    if ($reserva['estado_pago'] === 'pagado') {
        $nuevo_estado_pago = 'reembolsado'; // Opcional: si tienes este estado en tu BD
    }
    
    // Mensaje financiero para el correo
    $mensaje_financiero = '';
    if ($reserva['estado_pago'] === 'pagado') {
        $mensaje_financiero = "El monto total de $" . number_format($reserva['monto_total'], 0, ',', '.') . " será reembolsado según nuestras políticas.";
    } elseif ($reserva['estado_pago'] === 'parcial') {
        $mensaje_financiero = "Se ha registrado la anulación. El saldo parcial abonado de $" . number_format($reserva['monto_recaudacion'], 0, ',', '.') . " será considerado para futuras reservas o reembolsado según política.";
    }

    // 4. Actualizar Reserva
    $fecha_anulacion = date('Y-m-d H:i:s');
    $usuario_admin = $_SESSION['recinto_usuario'] ?? $_SESSION['nombre_completo'] ?? 'Admin';
    
    // Concatenar nota de anulación al historial de notas
    $nota_anulacion = "\n\n[ANULADA POR ADMIN: {$usuario_admin} - {$fecha_anulacion}]";
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

    // 5. Registrar Log de Auditoría (Bitácora)
    // Usamos la estructura estándar de reservas_log
    $descripcion_log = "Anulada por admin ({$usuario_admin}). Estado previo: {$reserva['estado']}. Pago previo: {$reserva['estado_pago']}.";
    
    $stmt_log = $pdo->prepare("
        INSERT INTO reservas_log (id_reserva, usuario_nombre, accion, descripcion, monto_anterior, created_at) 
        VALUES (?, ?, 'anulada', ?, ?, NOW())
    ");
    $stmt_log->execute([
        $id_reserva, 
        $usuario_admin, 
        $descripcion_log,
        $reserva['monto_total'] // Guardamos el monto total como referencia
    ]);

    // 6. Enviar Correo al Cliente
    if (!empty($reserva['email_cliente']) && !empty($reserva['nombre_cliente'])) {
        try {
            $html_content = "
            <html>
            <body style='font-family: sans-serif; color: #333;'>
                <h2 style='color: #C62828;'>⚠️ Tu reserva ha sido anulada</h2>
                <p>Hola <strong>{$reserva['nombre_cliente']}</strong>,</p>
                <p>Lamentablemente, tu reserva en <strong>{$reserva['nombre_cancha']}</strong> para el día <strong>{$reserva['fecha']} a las {$reserva['hora_inicio']}</strong> ha sido <strong>ANULADA</strong> por administración.</p>
                
                ".($mensaje_financiero ? "<div style='background:#FFF3CD; padding:10px; border-radius:5px; margin:10px 0;'><strong>Nota Financiera:</strong> {$mensaje_financiero}</div>" : "")."
                
                <p>Si tienes alguna duda o necesitas reagendar, por favor contáctanos respondiendo a este correo.</p>
                <hr>
                <small style='color:#999;'>Equipo de CanchaSport</small>
            </body>
            </html>";

            if (class_exists('BrevoMailer')) {
                $mail = new BrevoMailer();
                $mail->setTo($reserva['email_cliente'], $reserva['nombre_cliente'])
                    ->setSubject("Cancelación de Reserva #{$id_reserva} - {$reserva['nombre_cancha']}")
                    ->setHtmlBody($html_content)
                    ->send();
            }
        } catch (Exception $e) {
            error_log("Error enviando correo anulación: " . $e->getMessage());
            // No interrumpimos el flujo si falla el correo
        }
    }

    echo json_encode(['success' => true, 'message' => 'Reserva anulada correctamente']);

} catch (PDOException $e) {
    error_log("Error PDO anular_reserva: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} catch (Exception $e) {
    error_log("Error General anular_reserva: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado']);
}
?>