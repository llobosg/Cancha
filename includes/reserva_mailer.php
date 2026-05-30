<?php
// includes/reserva_mailer.php
// Clase centralizada para envío de correos vía Brevo

    class BrevoMailer {
        private $to;
        private $subject;
        private $htmlBody;
        private $replyTo;
        
        public function __construct() {
            // Constructor vacío
        }
        
        public function setTo($email, $name = '') {
            $this->to = [
                'email' => filter_var($email, FILTER_SANITIZE_EMAIL),
                'name' => htmlspecialchars($name)
            ];
            return $this;
        }
        
        public function setSubject($subject) {
            $this->subject = htmlspecialchars($subject);
            return $this;
        }
        
        public function setHtmlBody($htmlBody) {
            $this->htmlBody = $htmlBody;
            return $this;
        }
        
        public function setReplyTo($email, $name = '') {
            $this->replyTo = [
                'email' => filter_var($email, FILTER_SANITIZE_EMAIL),
                'name' => htmlspecialchars($name)
            ];
            return $this;
        }
        
        public function send() {
            $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : getenv('BREVO_API_KEY');
            
            if (empty($apiKey)) {
                error_log("[Brevo] ❌ API Key no configurada");
                return false;
            }
            
            // Remitente verificado en Brevo
            $sender = [
                'email' => 'contacto@canchasport.com',
                'name' => 'CanchaSport'
            ];
            
            $payload = [
                'sender' => $sender,
                'to' => [$this->to],
                'subject' => $this->subject,
                'htmlContent' => $this->htmlBody,
                'headers' => [
                    'X-Mailer' => 'CanchaSport-BrevoMailer/1.0'
                ]
            ];
            
            if ($this->replyTo) {
                $payload['replyTo'] = $this->replyTo;
            }
            
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_VERBOSE => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Logging detallado
            $log = "[Brevo] To:{$this->to['email']} | Subject:{$this->subject} | HTTP:$httpCode";
            if ($httpCode >= 200 && $httpCode < 300) {
                error_log("$log | ✅ Enviado");
                return true;
            } else {
                error_log("$log | ❌ Falló | Response: $response | cURL Error: $curlError");
                return false;
            }
        }
        
            // === FUNCIÓN NUEVA: Enviar actualización con datos ya actualizados ===
        public static function enviarActualizacionConDatos($pdo, $actualizada, $original) {
            $to_email = $actualizada['email'] ?? $actualizada['email_cliente'] ?? null;
            $to_name = $actualizada['nombre_socio'] ?? $actualizada['alias'] ?? $actualizada['nombre_cliente'] ?? 'Cliente';
            if (!$to_email) return false;
            
            // Formatear datos para comparar
            $orig_fecha = date('d/m/Y', strtotime($original['fecha']));
            $new_fecha  = date('d/m/Y', strtotime($actualizada['fecha']));
            
            $orig_hora  = substr($original['hora_inicio'], 0, 5) . ' - ' . substr($original['hora_fin'], 0, 5);
            $new_hora   = substr($actualizada['hora_inicio'], 0, 5) . ' - ' . substr($actualizada['hora_fin'], 0, 5);
            
            $orig_cancha = $original['nombre_cancha'];
            $new_cancha  = $actualizada['nombre_cancha'];

            // Iniciar bloque de cambios
            $html_cambios = "";

            // 1. Cancha (Solo si cambió, si no, mostrar solo la actual)
            if ($orig_cancha != $new_cancha) {
                $html_cambios .= "<p style='margin:5px 0'>🏟️ <strong>Cancha:</strong> <span style='text-decoration:line-through;color:#999;'>$orig_cancha</span> → <strong style='color:#071289;'>$new_cancha</strong></p>";
            } else {
                $html_cambios .= "<p style='margin:5px 0'>🏟️ <strong>Cancha:</strong> $new_cancha</p>";
            }

            // 2. FECHA Y HORA (SIEMPRE VISIBLE - Formato solicitado: Fecha/Hora -> Fecha/Hora Nueva)
            // Usamos color gris tachado para lo viejo y azul fuerte para lo nuevo
            $html_cambios .= "<p style='margin:10px 0 5px 0'>📅 <strong>Fecha / Hora:</strong><br> " .
                            "<span style='text-decoration:line-through;color:#999;'>$orig_fecha $orig_hora</span> → " .
                            "<strong style='color:#071289;'>$new_fecha $new_hora</strong></p>";

            // HTML Principal del Correo
            $iconos = [1=>'🎾', 2=>'🎾', 3=>'🏐', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
            $icono = $iconos[$actualizada['id_deporte']] ?? $iconos['default'];
            $recinto = $actualizada['recinto_nombre'] ?? 'Recinto';

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                <div style='text-align:center;background:linear-gradient(135deg,#2196F3,#1565C0);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
                    <h2 style='margin:0;'>🔄 Reserva Reubicada</h2>
                </div>
                <p style='font-size:1.1rem;'>Hola <strong>$to_name</strong>,</p>
                <p>Tu reserva ha sido movida exitosamente:</p>
                
                <div style='background:white;padding:15px;border-radius:8px;border-left:4px solid #4CAF50;margin:15px 0;'>
                    <p style='margin:5px 0'>📍 <strong>Recinto:</strong> $recinto</p>
                    $html_cambios
                    <hr style='margin:15px 0;border:0;border-top:1px solid #eee;'>
                    <p style='margin:5px 0;font-size:0.85rem;color:#888;'>ID Reserva: #{$actualizada['id_reserva']}</p>
                </div>
                
                <p style='margin-top:20px;text-align:center;'>
                    <a href='https://canchasport.com' style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>Ver mis reservas</a>
                </p>
                <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
                <p style='text-align:center;font-size:0.9rem;color:#888;'>
                    ¿Necesitas ayuda? Contáctanos en soporte@canchasport.com
                </p>
            </div>";
            
            try {
                $mail = new BrevoMailer();
                $mail->setTo($to_email, $to_name)
                    ->setSubject("🔄 Tu reserva ha sido reubicada - CanchaSport")
                    ->setReplyTo('reservas@canchasport.com', 'Soporte CanchaSport')
                    ->setHtmlBody($html);
                return $mail->send();
            } catch (Exception $e) {
                error_log("[ReservaMailer] Error actualización: " . $e->getMessage());
                return false;
            }
        }
        public static function enviarConfirmacion($pdo, $id_reserva) {
            try {

                $stmt = $pdo->prepare("
                    SELECT r.*, c.nombre_cancha, d.nombre as recinto_nombre
                    FROM reservas r
                    LEFT JOIN canchas c ON r.id_cancha = c.id_cancha
                    LEFT JOIN recintos_deportivos d ON c.id_recinto = d.id_recinto
                    WHERE r.id_reserva = ?
                ");
                $stmt->execute([$id_reserva]);
                $reserva = $stmt->fetch();

                if (!$reserva) return false;

                $to_email = $reserva['email_cliente'];
                $to_name  = $reserva['nombre_cliente'];

                if (!$to_email) return false;

                // 🧠 FORMATEO
                $fecha = date('d/m/Y', strtotime($reserva['fecha']));
                $hora  = substr($reserva['hora_inicio'],0,5) . ' - ' . substr($reserva['hora_fin'],0,5);
                $monto = number_format($reserva['monto_total'], 0, ',', '.');

                // 🎨 HTML
                $html = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
                    <div style='text-align:center;background:#071289;color:white;padding:15px;border-radius:8px;'>
                        <h2 style='margin:0;'>✅ Reserva Confirmada</h2>
                    </div>

                    <p>Hola <strong>{$to_name}</strong>,</p>
                    <p>Tu reserva ha sido confirmada con éxito:</p>

                    <div style='background:white;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #4CAF50;'>
                        <p>📍 <strong>Recinto:</strong> {$reserva['recinto_nombre']}</p>
                        <p>🏟️ <strong>Cancha:</strong> {$reserva['nombre_cancha']}</p>
                        <p>📅 <strong>Fecha:</strong> {$fecha}</p>
                        <p>⏰ <strong>Hora:</strong> {$hora}</p>
                        <p>💰 <strong>Total:</strong> \${$monto}</p>
                        <hr>
                        <p style='font-size:0.85rem;color:#888;'>ID Reserva: #{$id_reserva}</p>
                    </div>

                    <p style='text-align:center;'>
                        <a href='https://canchasport.com' 
                        style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>
                        Ver mis reservas
                        </a>
                    </p>
                </div>";

                $mail = new BrevoMailer();
                return $mail->setTo($to_email, $to_name)
                    ->setSubject("✅ Confirmación de Reserva - CanchaSport")
                    ->setReplyTo('reservas@canchasport.com', 'CanchaSport')
                    ->setHtmlBody($html)
                    ->send();

            } catch (Exception $e) {
                error_log("[MAIL CONFIRMACION ERROR] " . $e->getMessage());
                return false;
            }
        }
    }
?>