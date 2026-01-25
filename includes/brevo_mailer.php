<?php
// includes/brevo_mailer.php

class BrevoMailer {
    private $apiKey;
    private $fromEmail;
    private $fromName;
    private $toEmail;
    private $toName;
    private $subject;
    private $htmlBody;

    public function __construct() {
        // Usa tu correo real como remitente
        $this->apiKey = getenv('BREVO_API_KEY') ?: $_SERVER['BREVO_API_KEY'] ?? 'clave_no_definida';
        $this->fromEmail = 'llobos@gltcomex.com'; // ✅ Tu correo verificado
        $this->fromName = 'Cancha';
    }

    public function setTo($email, $name = '') {
        $this->toEmail = $email;
        $this->toName = $name ?: $email;
    }

    public function setSubject($subject) {
        $this->subject = $subject;
    }

    public function setHtmlBody($html) {
        $this->htmlBody = $html;
    }

    public function send() {
        // Validación crítica
        if ($this->apiKey === 'clave_no_definida') {
            error_log("❌ BREVO_API_KEY no configurada");
            return false;
        }

        $url = 'https://api.brevo.com/v3/smtp/email';
        $data = [
            'sender' => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'to' => [['email' => $this->toEmail, 'name' => $this->toName]],
            'subject' => $this->subject,
            'htmlContent' => $this->htmlBody
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Logging detallado
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("✅ Correo enviado a {$this->toEmail}");
            return true;
        } else {
            error_log("❌ Brevo error [$httpCode]: " . substr($response, 0, 500));
            error_log("❌ Datos enviados: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            return false;
        }
    }
}