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
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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
        return "Email could not be sent. Error: {$mail->ErrorInfo}";
    }
}

// Gmail SMTP configuration example
$gmailConfig = [
    'host' => 'smtp.gmail.com',
    'username' => 'rohandas4761@gmail.com',
    'password' => $_ENV['MAIL_APP_PASSWORD'], // Use app password for Gmail
    'port' => 587,
    'from_email' => 'rohandas4761@gmail.com',
    'from_name' => 'Rohan Das'
];

// Example usage with PHPMailer
$to = "nagatopain328509@gmail.com";
$subject = "Test Email with PHPMailer";
$body =        " <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #99a1af; background-color: #ffffff;}
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #615fff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; color: #0f172b}
                .otp-code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #ffffff; 
                    text-align: center; 
                    padding: 20px; 
                    background-color: #615fff; 
                    border-radius: 5px; 
                    margin: 20px 0;
                    letter-spacing: 5px;
                }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #99a1af; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Verification Required</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>Thank you for registering! Please use the following code to verify your account:</p>
                    <div class='otp-code'>123456</div>
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This code will expire in 5 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this code, please ignore this email</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
echo sendEmailWithPHPMailer($to, $subject, $body, $gmailConfig);
