<?php

require_once "./BaseController.php";
require_once "./Models/Otp.php";
require_once "./MailController.php";

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

    public function generateAndSendOTP($userId, $email, $purpose = 'registration') {
        // Check rate limiting
        if (!$this->otpModel->canRequestNewOTP($userId, $purpose)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please wait before requesting again.'
            ];
        }
        
        // Generate OTP
        $otpCode = $this->otpModel->generateOTP();
        
        // Store OTP in database
        if ($this->otpModel->storeOTP($userId, $otpCode, $purpose)) {
            // Send OTP via email
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

    public function verifyOTP($userId, $otpCode, $purpose = 'registration') {
        return $this->otpModel->verifyOTP($userId, $otpCode, $purpose);
    }
    
    // Handle API request to generate OTP
    public function handleGenerateOTPRequest() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id']) || !isset($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $userId = $input['user_id'];
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        $purpose = $input['purpose'] ?? 'registration';
        
        if (!$email) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }
        
        $result = $this->generateAndSendOTP($userId, $email, $purpose);
        echo json_encode($result);
    }

    public function handleVerifyOTPRequest() {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id']) || !isset($input['otp_code'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $userId = $input['user_id'];
        $otpCode = $input['otp_code'];
        $purpose = $input['purpose'] ?? 'registration';
        
        $result = $this->verifyOTP($userId, $otpCode, $purpose);
        echo json_encode($result);
    }
}


