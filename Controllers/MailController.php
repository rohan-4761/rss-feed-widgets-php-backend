<?php

require './Config/mail.php';

class MailController
{
    private $mail;

    private function __construct()
    {
        $this->mail = new MailConfig();
    }

    private function getPurposeText($purpose)
    {
        switch ($purpose) {
            case 'registration':
                return 'Thank you for registering! Please use the following code to verify your account:';
            case 'login':
                return 'Someone is trying to login to your account. Please use the following code to verify:';
            case 'password_reset':
                return 'You requested a password reset. Please use the following code to proceed:';
            case 'email_verification':
                return 'Please verify your email address using the following code:';
            default:
                return 'Please use the following verification code:';
        }
    }
    private function getEmailTemplate($otpCode, $purpose)
    {
        $purposeText = $this->getPurposeText($purpose);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .otp-code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #4CAF50; 
                    text-align: center; 
                    padding: 20px; 
                    background-color: #e8f5e8; 
                    border-radius: 5px; 
                    margin: 20px 0;
                    letter-spacing: 5px;
                }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Verification Required</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>{$purposeText}</p>
                    <div class='otp-code'>{$otpCode}</div>
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
    }
    private function getEmailSubject($purpose)
    {
        switch ($purpose) {
            case 'registration':
                return 'Complete Your Registration - OTP Verification';
            case 'login':
                return 'Login Verification Code';
            case 'password_reset':
                return 'Password Reset Verification';
            case 'email_verification':
                return 'Email Verification Code';
            default:
                return 'Your Verification Code';
        }
    }

    public function sendOTPEmail($email, $otpCode, $purpose = 'verification')
    {
        try {

            $emailExist = $this->mail->checkEmailExists($email);
            if ($emailExist) {
                $subject = $this->getEmailSubject($purpose);
                $message = $this->getEmailTemplate($otpCode, $purpose);
                $result = $this->mail->sendEmail($email, $subject, $message);
                if (!$result['success']) {
                    return $result;
                }
            }
        } catch (Exception $e) {
            return ["success" => False, "message" => $e->getMessage()];
        }
    }
}
