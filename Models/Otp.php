<?php

class Otp
{
    private $conn;
    private $table = "otps";
    private $otpExpiry = 300;
    private $otpLength = 6;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function generateOTP($length = null)
    {
        $length = $length ?? $this->otpLength;
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= mt_rand(0, 9);
        }
        return $otp;
    }


    private function invalidateExistingOtps($userId, $purpose)
    {
        $query = "UPDATE {$this->table} SET is_used = TRUE WHERE user_id = :user_id AND purpose = :purpose AND is_used = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":purpose", $purpose);
        $stmt->execute();
    }

    public function storeOTP($userId, $otpCode, $purpose = "registration")
    {
        $this->invalidateExistingOtps($userId, $purpose);

        $expiresAt = date('Y-m-d H:i:s', time() + $this->otpExpiry);
        $query = "INSERT INTO {$this->table} (user_id, otp_code, purpose, expires_at) VALUES (:user_id, :otp_code, :purpose, :expires_at)";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $userId);
            $stmt->bindParam(":otp_code", $otpCode);
            $stmt->bindParam(":purpose", $purpose);
            $stmt->bindParam(":expires_at", $expiresAt);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error storing OTP: " . $e->getMessage());
            return false;
        }
    }

    private function incrementAttempts($userId, $otpCode, $purpose)
    {
        $query = "UPDATE otps SET attempts = attempts + 1 
                WHERE user_id = :user_id AND otp_code = :otp_code AND purpose = :purpose AND is_used = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":otp_code", $otpCode);
        $stmt->bindParam(":purpose", $purpose);
        $stmt->execute();
    }

    private function markOTPAsUsed($otpId)
    {
        $query = "UPDATE otps SET is_used = TRUE WHERE id = :otp_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":otp_id", $otpId);
        $stmt->execute();
    }

    public function cleanupExpiredOTPs()
    {
        $query = "DELETE FROM otps WHERE expires_at < NOW() OR is_used = TRUE";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error cleaning up OTPs: " . $e->getMessage());
            return 0;
        }
    }

    public function canRequestNewOTP($userId, $purpose = 'registration')
    {
        $query = "SELECT COUNT(*) as count FROM otps 
                WHERE user_id = :user_id AND purpose = :purpose AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":purpose", $purpose);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] < 3; // Max 3 OTPs per minute
    }

    public function verifyOTP($userId, $otpCode, $purpose = "registration")
    {
        $this->incrementAttempts($userId, $otpCode, $purpose);
        $query = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND otp_code = :otp_code AND purpose = :purpose 
                AND is_used = FALSE AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $userId);
            $stmt->bindParam(":otp_code", $otpCode);
            $stmt->bindParam(":purpose", $purpose);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if ($result['attempts'] > 5) {
                    return [
                        'success' => false,
                        'message' => 'Too many verification attempts. Please request a new code.'
                    ];
                }

                // Mark OTP as used
                $this->markOTPAsUsed($result['id']);

                return [
                    'success' => true,
                    'message' => 'OTP verified successfully!'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP code.'
                ];
            }
        } catch (PDOException $e) {
            error_log("Error verifying OTP: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during verification.'
            ];
        }
    }
}
