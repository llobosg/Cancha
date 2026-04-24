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
        
        $iconos = [1=>'🎾', 2=>'🎾', 3=>'🏐', 10=>'⚽', 11=>'⚽', 'default'=>'🏟️'];
        $icono = $iconos[$actualizada['id_deporte']] ?? $iconos['default'];
        
        // Formatear valores originales vs nuevos
        $orig_fecha = date('d/m/Y', strtotime($original['fecha']));
        $new_fecha  = date('d/m/Y', strtotime($actualizada['fecha']));
        $orig_hora  = substr($original['hora_inicio'], 0, 5) . ' - ' . substr($original['hora_fin'], 0, 5);
        $new_hora   = substr($actualizada['hora_inicio'], 0, 5) . ' - ' . substr($actualizada['hora_fin'], 0, 5);
        $orig_cancha = $original['nombre_cancha'];
        $new_cancha  = $actualizada['nombre_cancha'];
        
        // Construir lista de cambios reales
        $cambios_html = "";
        if ($orig_fecha != $new_fecha) $cambios_html .= "<p style='margin:8px 0'>📅 <strong>Fecha:</strong> <span style='text-decoration:line-through;color:#888;'>$orig_fecha</span> → <strong>$new_fecha</strong></p>";
        if ($orig_hora  != $new_hora)  $cambios_html .= "<p style='margin:8px 0'>⏰ <strong>Hora:</strong> <span style='text-decoration:line-through;color:#888;'>$orig_hora</span> → <strong>$new_hora</strong></p>";
        if ($orig_cancha != $new_cancha) $cambios_html .= "<p style='margin:8px 0'>🏟️ <strong>Cancha:</strong> <span style='text-decoration:line-through;color:#888;'>$orig_cancha</span> → <strong>$new_cancha</strong></p>";
        if (empty($cambios_html)) $cambios_html = "<p style='margin:8px 0;color:#666;'>No se registraron cambios en los datos principales.</p>";
        
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;border-radius:12px;'>
        <div style='text-align:center;background:linear-gradient(135deg,#2196F3,#1565C0);color:white;padding:15px;border-radius:8px;margin-bottom:20px;'>
            <h2 style='margin:0;'>🔄 Reserva Reubicada</h2>
        </div>
        <p style='font-size:1.1rem;'>Hola <strong>$to_name</strong>,</p>
        <p>Tu reserva ha sido movida exitosamente:</p>
        
        <div style='background:white;padding:15px;border-radius:8px;border-left:4px solid #4CAF50;margin:15px 0;'>
            <p style='margin:5px 0'><strong>📍 Recinto:</strong> {$actualizada['recinto_nombre']}</p>
            $cambios_html
            <p style='margin:15px 0 5px 0;font-size:0.85rem;color:#666;border-top:1px solid #eee;padding-top:8px;'>
                ID Reserva: #{$actualizada['id_reserva']}
            </p>
        </div>
        
        <p style='margin-top:20px;text-align:center;'>
            <a href='https://canchasport.com' style='background:#071289;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:bold;'>Ver mis reservas</a>
        </p>
        <hr style='margin:25px 0;border:0;border-top:1px solid #eee;'>
        <p style='text-align:center;font-size:0.9rem;color:#888;'>
            ¿Necesitas ayuda? Contáctanos en contacto@canchasport.com
        </p>
    </div>";
    
    try {
        $mail = new BrevoMailer();
        $mail->setTo($to_email, $to_name)
             ->setSubject("🔄 Tu reserva ha sido reubicada - CanchaSport")
             ->setReplyTo('contacto@canchasport.com', 'Soporte CanchaSport')
             ->setHtmlBody($html);
        return $mail->send();
    } catch (Exception $e) {
        error_log("[ReservaMailer] Error actualización: " . $e->getMessage());
        return false;
    }
}
}
?>