<?php
// includes/brevo_mailer.php

class BrevoMailer {
    private $to;
    private $subject;
    private $htmlBody;
    
    public function __construct() {
        // Constructor
    }
    
    public function setTo($email, $name = '') {
        $this->to = ['email' => $email, 'name' => $name];
    }
    
    public function setSubject($subject) {
        $this->subject = $subject;
    }
    
    public function setHtmlBody($htmlBody) {
        $this->htmlBody = $htmlBody;
    }
    
    public function send() {
        // Lógica de envío usando BREVO_API_KEY
        $apiKey = BREVO_API_KEY;
        
        if (empty($apiKey)) {
            error_log("BREVO_API_KEY no configurada");
            return false;
        }
        
        // Configuración de la API de Brevo
        $url = 'https://api.brevo.com/v3/smtp/email';
        $headers = [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // ✅ USAR EL REMITENTE VERIFICADO
        $sender_email = 'llobos@gltcomex.com';
        $sender_name = 'CanchaSport';
        
        $data = [
            'sender' => ['email' => $sender_email, 'name' => $sender_name],
            'to' => [$this->to],
            'subject' => $this->subject,
            'htmlContent' => $this->htmlBody
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Brevo API Error: HTTP $httpCode, Response: $response, Error: $error");
            return false;
        }
    }
}
?>