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
        // Usa variables de entorno de Railway
        $this->apiKey = $_ENV['BREVO_API_KEY'] ?? 'tu_clave_smtp_brevo';
        $this->fromEmail = $_ENV['MAILER_FROM_EMAIL'] ?? 'no-reply@cancha.app';
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
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}