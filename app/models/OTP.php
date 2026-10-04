<?php
class OTP {
    private $db;
    private $table = 'otp_codes';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Create a new OTP record
     */
    public function create($phone, $code, $expiresAt) {
        $query = "INSERT INTO {$this->table} (phone, code, expires_at, created_at) 
                 VALUES (:phone, :code, :expires_at, NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }

    /**
     * Find a valid OTP for the given phone and code
     */
    public function findValidOTP($phone, $code) {
        $query = "SELECT id, phone, code, expires_at, is_used 
                 FROM {$this->table} 
                 WHERE phone = :phone 
                 AND code = :code 
                 AND is_used = 0 
                 AND expires_at > NOW() 
                 ORDER BY created_at DESC 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mark OTP as used
     */
    public function markAsUsed($otpId) {
        $query = "UPDATE {$this->table} SET is_used = 1, used_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $otpId);
        
        return $stmt->execute();
    }

    /**
     * Count recent OTP attempts for a phone
     */
    public function countRecentAttempts($phone, $minutes = 10) {
        $query = "SELECT COUNT(*) as attempt_count 
                 FROM {$this->table} 
                 WHERE phone = :phone 
                 AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['attempt_count'] ?? 0;
    }

    /**
     * Count OTP attempts in the last 24 hours (daily limit)
     * MAX: 5 OTP per day
     */
    public function countDailyAttempts($phone) {
        $query = "SELECT COUNT(*) as attempt_count 
                 FROM {$this->table} 
                 WHERE phone = :phone 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['attempt_count'] ?? 0;
    }

    /**
     * Check if resend is allowed (60-second cooldown)
     * Returns array with allowed flag and time remaining
     */
    public function checkResendCooldown($phone, $cooldownSeconds = 60) {
        $query = "SELECT created_at FROM {$this->table} 
                 WHERE phone = :phone 
                 ORDER BY created_at DESC 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $lastOtp = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastCreatedTime = strtotime($lastOtp['created_at']);
            $currentTime = time();
            $elapsedSeconds = $currentTime - $lastCreatedTime;
            $secondsRemaining = max(0, $cooldownSeconds - $elapsedSeconds);
            
            if ($secondsRemaining > 0) {
                return [
                    'allowed' => false,
                    'seconds_remaining' => $secondsRemaining,
                    'can_resend_at' => date('Y-m-d H:i:s', $currentTime + $secondsRemaining)
                ];
            }
        }
        
        return [
            'allowed' => true,
            'seconds_remaining' => 0,
            'can_resend_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get the last OTP creation time for a phone
     */
    public function getLastOTPTime($phone) {
        $query = "SELECT created_at FROM {$this->table} 
                 WHERE phone = :phone 
                 ORDER BY created_at DESC 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['created_at'];
        }
        
        return null;
    }

    /**
     * Delete OTP record
     */
    public function delete($otpId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $otpId);
        
        return $stmt->execute();
    }
}
?>