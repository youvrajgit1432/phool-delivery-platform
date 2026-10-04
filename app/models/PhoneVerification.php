<?php
class PhoneVerification {
    private $conn;
    private $table_name = "phone_verifications";

    public $id;
    public $phone;
    public $verification_code;
    public $status;
    public $attempts;
    public $expires_at;
    public $verified_at;
    public $created_at;

    // Security constants
    const MAX_ATTEMPTS = 5;
    const CODE_EXPIRY_MINUTES = 10;
    const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createVerification($phone, $code) {
        try {
            // Validate input parameters
            if (!$this->validatePhone($phone) || !$this->validateCode($code)) {
                return false;
            }

            // Check rate limiting
            if (!$this->checkRateLimit($phone)) {
                throw new Exception("Rate limit exceeded for phone number");
            }

            // Clean up any existing verifications for this phone
            $this->cleanupOldVerifications($phone);

            $query = "INSERT INTO " . $this->table_name . "
                     SET phone = :phone, verification_code = :code, 
                         status = 'pending', attempts = 0,
                         expires_at = DATE_ADD(NOW(), INTERVAL " . self::CODE_EXPIRY_MINUTES . " MINUTE),
                         created_at = NOW()";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":code", $code);

            if ($stmt->execute()) {
                // Log the verification attempt for security monitoring
                $this->logVerificationAttempt($phone, 'code_sent');
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Phone verification creation error: " . $e->getMessage());
            $this->logVerificationAttempt($phone, 'creation_error', $e->getMessage());
            return false;
        }
    }

    public function verifyCode($phone, $code) {
        try {
            // Validate input parameters
            if (!$this->validatePhone($phone) || !$this->validateCode($code)) {
                return false;
            }

            // Check if phone is temporarily blocked
            if ($this->isPhoneBlocked($phone)) {
                throw new Exception("Phone number temporarily blocked due to too many attempts");
            }

            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE phone = :phone AND verification_code = :code 
                     AND status = 'pending' AND expires_at > NOW() 
                     ORDER BY created_at DESC LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":code", $code);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $verification = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check attempts limit
                if ($verification['attempts'] >= self::MAX_ATTEMPTS) {
                    $this->blockPhoneTemporarily($phone);
                    throw new Exception("Maximum verification attempts exceeded");
                }

                // Update verification status using transaction for data consistency
                $this->conn->beginTransaction();
                
                try {
                    $updateQuery = "UPDATE " . $this->table_name . " 
                                   SET status = 'verified', verified_at = NOW() 
                                   WHERE id = :id";
                    $updateStmt = $this->conn->prepare($updateQuery);
                    $updateStmt->bindParam(":id", $verification['id']);
                    $updateStmt->execute();

                    // Clean up other pending verifications for this phone
                    $this->cleanupOldVerifications($phone);

                    $this->conn->commit();
                    
                    // Log successful verification
                    $this->logVerificationAttempt($phone, 'verification_success');
                    return true;
                } catch (Exception $e) {
                    $this->conn->rollBack();
                    throw $e;
                }
            }

            // Increment attempts if verification failed
            $this->incrementAttempts($phone);
            
            // Log failed attempt
            $this->logVerificationAttempt($phone, 'verification_failed');
            return false;
        } catch (Exception $e) {
            error_log("Phone verification error: " . $e->getMessage());
            $this->logVerificationAttempt($phone, 'verification_error', $e->getMessage());
            return false;
        }
    }

    public function getPendingVerification($phone) {
        // Validate phone number
        if (!$this->validatePhone($phone)) {
            return null;
        }

        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE phone = :phone AND status = 'pending' 
                 AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    }

    public function canResendCode($phone) {
        // Validate phone number
        if (!$this->validatePhone($phone)) {
            return false;
        }

        // Check if phone is blocked
        if ($this->isPhoneBlocked($phone)) {
            return false;
        }

        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE phone = :phone AND status = 'pending' 
                 AND created_at > DATE_SUB(NOW(), INTERVAL " . self::RESEND_COOLDOWN_SECONDS . " SECOND) 
                 ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        // If a code was sent within the cooldown period, cannot resend
        return $stmt->rowCount() === 0;
    }

    public function getAttemptsCount($phone) {
        // Validate phone number
        if (!$this->validatePhone($phone)) {
            return 0;
        }

        $query = "SELECT attempts FROM " . $this->table_name . " 
                 WHERE phone = :phone AND status = 'pending' 
                 AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC)['attempts'] : 0;
    }

    private function validatePhone($phone) {
        // Basic phone validation - extend based on your requirements
        if (empty($phone) || !preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
            error_log("Invalid phone number format: " . $phone);
            return false;
        }
        return true;
    }

    private function validateCode($code) {
        // Validate verification code format
        if (empty($code) || !preg_match('/^\d{4,8}$/', $code)) {
            error_log("Invalid verification code format");
            return false;
        }
        return true;
    }

    private function checkRateLimit($phone) {
        // Check if too many verification attempts in a short period
        $query = "SELECT COUNT(*) as attempt_count FROM " . $this->table_name . " 
                 WHERE phone = :phone 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Allow maximum 10 verification attempts per hour
        return $result['attempt_count'] < 10;
    }

    private function cleanupOldVerifications($phone) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = 'expired' 
                 WHERE phone = :phone AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();
    }

    private function incrementAttempts($phone) {
        $query = "UPDATE " . $this->table_name . " 
                 SET attempts = attempts + 1 
                 WHERE phone = :phone AND status = 'pending' 
                 AND expires_at > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        // Check if max attempts reached and block if necessary
        $attempts = $this->getAttemptsCount($phone);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->blockPhoneTemporarily($phone);
        }
    }

    private function isPhoneBlocked($phone) {
        // Check if phone is temporarily blocked (extend this based on your blocking logic)
        $query = "SELECT * FROM phone_blocks 
                 WHERE phone = :phone AND block_expires > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    private function blockPhoneTemporarily($phone, $minutes = 30) {
        // Create or update phone block record
        $query = "INSERT INTO phone_blocks (phone, block_expires, created_at) 
                 VALUES (:phone, DATE_ADD(NOW(), INTERVAL :minutes MINUTE), NOW())
                 ON DUPLICATE KEY UPDATE block_expires = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":minutes", $minutes);
        $stmt->execute();

        $this->logVerificationAttempt($phone, 'phone_blocked');
    }

    private function logVerificationAttempt($phone, $action, $details = '') {
        // Log security events for monitoring
        $query = "INSERT INTO security_logs (phone, action, details, ip_address, user_agent, created_at) 
                 VALUES (:phone, :action, :details, :ip, :ua, NOW())";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":action", $action);
            $stmt->bindParam(":details", $details);
            $stmt->bindValue(":ip", $_SERVER['REMOTE_ADDR'] ?? '');
            $stmt->bindValue(":ua", $_SERVER['HTTP_USER_AGENT'] ?? '');
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }
}

// Additional table schemas for reference:
/*
CREATE TABLE phone_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    verification_code VARCHAR(20) NOT NULL,
    status ENUM('pending', 'verified', 'expired') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_phone_status (phone, status),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE phone_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL UNIQUE,
    block_expires DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_block_expires (block_expires)
);

CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL,
    INDEX idx_phone_action (phone, action),
    INDEX idx_created_at (created_at)
);
*/
?>