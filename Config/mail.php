<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


class MailConfig
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->mail->Host = $_ENV['MAIL_HOST'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $_ENV['MAIL_USERNAME'];
        $this->mail->Password = $_ENV['MAIL_APP_PASSWORD'];
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = $_ENV['MAIL_PORT'];

        $this->mail->setFrom($_ENV['MAIL_FROM_EMAIL'], $_ENV['MAIL_FROM_NAME']);
    }

    public function sendEmail($to, $subject, $body)
    {
        try {
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            $this->mail->send();

            return ["success" => True];
        } catch (Exception $e) {
            return [
                "success" => False,
                "message " => $this->mail->ErrorInfo
            ];
        }
    }

    public function checkEmailExists($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'reason' => 'Invalid email format'
        ];
    }

    $domain = substr(strrchr($email, "@"), 1);

    if (!getmxrr($domain, $mxRecords)) {
        $aRecord = gethostbyname($domain);
        if ($aRecord === $domain) {
            return [
                'valid' => false,
                'reason' => 'Domain has no MX or A record'
            ];
        }
        $mxRecords = [$domain];
    }

    $mxRecord = $mxRecords[0];

    $connection = fsockopen($mxRecord, 25, $errno, $errstr, 10);
    if (!$connection) {
        return [
            'valid' => false,
            'reason' => 'Cannot connect to mail server'
        ];
    }

    $readResponse = function ($conn) {
        $response = "";
        do {
            $line = fgets($conn, 1024);
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        return $response;
    };

    fputs($connection, "EHLO {$_ENV['MAIL_HOST']}\r\n");
    $readResponse($connection);

    $mailFrom = $_ENV['MAIL_FROM_EMAIL'] ?? 'test@example.com';
    fputs($connection, "MAIL FROM:<{$mailFrom}>\r\n");
    $readResponse($connection);

    fputs($connection, "RCPT TO:<{$email}>\r\n");
    $response = $readResponse($connection);

    fputs($connection, "QUIT\r\n");
    fclose($connection);

    $responseCode = substr($response, 0, 3);

    if ($responseCode == '250' || $responseCode == '251') {
        return [
            'valid' => true,
            'reason' => 'Email appears to exist',
            'smtp_response' => $response
        ];
    } else {
        return [
            'valid' => false,
            'reason' => 'Server rejected address',
            'smtp_response' => $response
        ];
    }
}

}