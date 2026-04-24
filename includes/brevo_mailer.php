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
        
        // ✅ Remitente verificado (DEBE estar verificado en Brevo)
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
            CURLOPT_VERBOSE => true // Para debug en logs
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
}
?>