<?php

require_once "./Controllers/BaseController.php";
require_once "./Models/Otp.php";
require_once "./Controllers/MailController.php";

class OtpController extends BaseController{
    private $db;
    private $otpModel;
    private $mailController; 

    public function __construct($db)
    {
        $this->db = $db;
        $this->otpModel = new Otp($db);
        $this->mailController = new MailController();
    }

    public function generateAndSendOTP($email, $purpose = 'registration') {

        if (!$this->otpModel->canRequestNewOTP($email, $purpose)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please wait before requesting again.'
            ];
        }
        
        $otpCode = $this->otpModel->generateOTP();
        
        if ($this->otpModel->storeOTP($email, $otpCode, $purpose)) {
            if ($this->mailController->sendOTPEmail($email, $otpCode, $purpose)) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully to your email!'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP email.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP.'
            ];
        }
    }

    public function verifyOTP($email, $otpCode, $purpose = 'registration') {
        return $this->otpModel->verifyOTP($email, $otpCode, $purpose);
    }
    
    // Handle API request to generate OTP
    public function handleGenerateOTPRequest() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        $purpose = $input['purpose'] ?? 'registration';
        
        if (!$email) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }
        
        $result = $this->generateAndSendOTP($email, $purpose);
        echo json_encode($result);
    }

    public function handleVerifyOTPRequest() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email']) || !isset($input['otp_code'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $email = $input['email'];
        $otpCode = $input['otp_code'];
        $purpose = $input['purpose'] ?? 'registration';
        
        $result = $this->verifyOTP($email, $otpCode, $purpose);
        echo json_encode($result);
    }
}


