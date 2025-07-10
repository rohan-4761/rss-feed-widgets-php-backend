<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function sendEmailWithPHPMailer($to, $subject, $body, $config) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['port'];

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return 'Email sent successfully!';
    } catch (Exception $e) {
        echo $_ENV["GMAIL_APP_PASSWORD"];
        return "Email could not be sent. Error: {$mail->ErrorInfo}";
    }
}

// Gmail SMTP configuration example
$gmailConfig = [
    'host' => 'smtp.gmail.com',
    'username' => 'rohandas4761@gmail.com',
    'password' => $_ENV['GMAIL_APP_PASSWORD'], // Use app password for Gmail
    'port' => 465,
    'from_email' => 'rohandas4761@gmail.com',
    'from_name' => 'Rohan Das'
];

// Example usage with PHPMailer
$to = "nagatopain328509@gmail.com";
$subject = "Test Email with PHPMailer";
$body = "<h1>Hello from PHPMailer!</h1><p>This email was sent using PHPMailer with SMTP.</p>";

echo sendEmailWithPHPMailer($to, $subject, $body, $gmailConfig);
