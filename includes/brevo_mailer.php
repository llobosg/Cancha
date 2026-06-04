<?php
// includes/brevo_mailer.php
class BrevoMailer {
    private $to;
    private $subject;
    private $htmlBody;
    private $replyTo;
    
    public function setTo($email, $name = '') {
        $this->to = ['email' => filter_var($email, FILTER_SANITIZE_EMAIL), 'name' => htmlspecialchars($name)];
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
        $this->replyTo = ['email' => $email, 'name' => $name];
        return $this;
    }
    
    public function send() {
        $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : getenv('BREVO_API_KEY');
        
        if (empty($apiKey)) {
            error_log("[Brevo] ❌ API Key no configurada");
            return false;
        }
        
        // ✅ Remitente verificado
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
            CURLOPT_VERBOSE => true
        ]);
        
        if ($response === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $log = "[Brevo] To:{$this->to['email']} | Subject:{$this->subject} | HTTP:$httpCode";
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("$log | ✅ Enviado");
            return true;
        } else {
            error_log("$log | ❌ Falló | Response: $response | cURL Error: $curlError");
            return false;
        }
    }

    // === ✅ NUEVO MÉTODO: Enviar Confirmación de Reserva ===
    public static function enviarConfirmacion($pdo, $id_reserva) {
        try {
            // 1. Obtener datos de la reserva
            $stmt = $pdo->prepare("
                SELECT r.*, c.nombre_cancha, s.nombre as nombre_socio, s.email as email_socio
                FROM reservas r
                JOIN canchas c ON r.id_cancha = c.id_cancha
                LEFT JOIN socios s ON r.id_socio = s.id_socio
                WHERE r.id_reserva = ?
            ");
            $stmt->execute([$id_reserva]);
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reserva || empty($reserva['email_cliente']) && empty($reserva['email_socio'])) {
                error_log("[Brevo] ❌ No se pudo enviar confirmación: Reserva o Email no encontrados ID:$id_reserva");
                return false;
            }

            // Determinar email y nombre destinatario
            $email_destino = !empty($reserva['email_cliente']) ? $reserva['email_cliente'] : $reserva['email_socio'];
            $nombre_destino = !empty($reserva['nombre_cliente']) ? $reserva['nombre_cliente'] : ($reserva['nombre_socio'] ?? 'Socio');

            // Formatear fecha
            $fecha_obj = new DateTime($reserva['fecha']);
            $fecha_fmt = $fecha_obj->format('d/m/Y');
            $hora_ini = substr($reserva['hora_inicio'], 0, 5);
            $hora_fin = substr($reserva['hora_fin'], 0, 5);
            $monto = number_format($reserva['monto_total'], 0, ',', '.');
            $monto_fmt = htmlspecialchars($monto, ENT_QUOTES, 'UTF-8');
            $nombre_cancha = htmlspecialchars($reserva['nombre_cancha'] ?? '', ENT_QUOTES, 'UTF-8');

            // 2. Construir HTML
            $html = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
                <div style='background: linear-gradient(135deg, #4FC3F7, #66BB6A); padding: 20px; text-align: center; border-radius: 12px 12px 0 0;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>🎉 ¡Reserva Confirmada!</h1>
                </div>
                <div style='padding: 20px; background: #f9f9f9; border-radius: 0 0 12px 12px;'>
                    <p style='font-size: 16px;'>Hola <strong>{$nombre_destino}</strong>,</p>
                    <p>Tu reserva ha sido registrada exitosamente. Aquí tienes los detalles:</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin: 20px 0;'>
                        <div style='margin-bottom: 10px;'><strong>🏟️ Cancha:</strong> {$nombre_cancha}</div>
                        <div style='margin-bottom: 10px;'><strong>📅 Fecha:</strong> {$fecha_fmt}</div>
                        <div style='margin-bottom: 10px;'><strong>⏰ Hora:</strong> {$hora_ini} - {$hora_fin} hrs</div>
                        <div style='margin-bottom: 10px;'><strong>💰 Monto Total:</strong> $ " . $monto_fmt . "</div>
                        <div style='margin-bottom: 10px;'><strong>🔖 Estado Pago:</strong> <span style='color: #E67E22; font-weight: bold;'>{$reserva['estado_pago']}</span></div>
                    </div>
                    
                    <p style='font-size: 14px; color: #666;'>Te esperamos en CanchaSport. Si necesitas cancelar o modificar, por favor contáctanos con antelación.</p>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='https://canchasport.com/pages/dashboard_socio.php' style='background: #66BB6A; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Ver mis Reservas</a>
                    </div>
                </div>
                <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #999;'>
                    © 2026 CanchaSport. Todos los derechos reservados.
                </div>
            </div>
            ";

            // 3. Enviar Email
            $mailer = new self();
            $mailer->setTo($email_destino, $nombre_destino);
            $mailer->setSubject("✅ Confirmación de Reserva - CanchaSport");
            $mailer->setHtmlBody($html);
            
            return $mailer->send();

        } catch (Exception $e) {
            error_log("[Brevo] ❌ Error enviando confirmación: " . $e->getMessage());
            return false;
        }
    }
}
?>