<?php
class LogOTPModel {
    private $conn;
    private $table_name = "customer_login_otps";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Save OTP to database with security enhancements
     */
    public function saveOTP($customer_id, $otp, $expires_at) {
        try {
            // Validate input parameters
            if (!$this->validateInput($customer_id, $otp, $expires_at)) {
                throw new Exception("Invalid input parameters");
            }

            // Sanitize customer_id
            $customer_id = $this->sanitizeCustomerId($customer_id);
            
            $query = "INSERT INTO " . $this->table_name . " 
                     (customer_id, otp_code, expires_at, created_at) 
                     VALUES (:customer_id, :otp_code, :expires_at, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':otp_code', $otp, PDO::PARAM_STR);
            $stmt->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("OTP save failed for customer_id: " . $customer_id);
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("OTP save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify OTP with security enhancements
     */
    public function verifyOTP($customer_id, $otp) {
        try {
            // Validate input parameters
            if (!$this->validateInput($customer_id, $otp)) {
                return false;
            }

            // Sanitize customer_id
            $customer_id = $this->sanitizeCustomerId($customer_id);
            
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     AND otp_code = :otp_code 
                     AND used = 0 
                     AND expires_at > NOW() 
                     ORDER BY created_at DESC 
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':otp_code', $otp, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $otp_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Additional security: Verify OTP format and length
                if ($this->isValidOTPFormat($otp_data['otp_code'])) {
                    return $otp_data;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("OTP verification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark OTP as used with security enhancements
     */
    public function markOTPAsUsed($otp_id) {
        try {
            // Validate OTP ID
            if (!$this->validateOTPId($otp_id)) {
                throw new Exception("Invalid OTP ID");
            }

            $query = "UPDATE " . $this->table_name . " 
                     SET used = 1, used_at = NOW() 
                     WHERE id = :otp_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':otp_id', $otp_id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Failed to mark OTP as used: " . $otp_id);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("OTP mark as used error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last OTP for a customer with security enhancements
     */
    public function getLastOTP($customer_id) {
        try {
            // Validate customer_id
            if (!$this->validateCustomerId($customer_id)) {
                return false;
            }

            // Sanitize customer_id
            $customer_id = $this->sanitizeCustomerId($customer_id);
            
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     ORDER BY created_at DESC 
                     LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $otp_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Sanitize output data
                return $this->sanitizeOTPData($otp_data);
            }
            return false;
        } catch (Exception $e) {
            error_log("Get last OTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired OTPs with security enhancements
     */
    public function cleanupExpiredOTPs($hours = 24) {
        try {
            // Validate hours parameter
            if (!$this->validateHoursParameter($hours)) {
                $hours = 24; // Default to safe value
            }

            // Use transaction for cleanup operation
            $this->conn->beginTransaction();
            
            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE expires_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $this->conn->commit();
            } else {
                $this->conn->rollBack();
                error_log("OTP cleanup failed for hours: " . $hours);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("OTP cleanup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Security validation methods
     */
    
    private function validateInput($customer_id, $otp, $expires_at = null) {
        // Validate customer_id
        if (!$this->validateCustomerId($customer_id)) {
            return false;
        }
        
        // Validate OTP format and length
        if (!$this->isValidOTPFormat($otp)) {
            return false;
        }
        
        // Validate expires_at if provided
        if ($expires_at !== null && !$this->isValidDateTime($expires_at)) {
            return false;
        }
        
        return true;
    }
    
    private function validateCustomerId($customer_id) {
        return is_numeric($customer_id) && $customer_id > 0 && $customer_id <= PHP_INT_MAX;
    }
    
    private function validateOTPId($otp_id) {
        return is_numeric($otp_id) && $otp_id > 0 && $otp_id <= PHP_INT_MAX;
    }
    
    private function validateHoursParameter($hours) {
        return is_numeric($hours) && $hours > 0 && $hours <= 8760; // Max 1 year
    }
    
    private function isValidOTPFormat($otp) {
        // OTP should be numeric and between 4-8 digits
        return is_string($otp) && preg_match('/^\d{4,8}$/', $otp);
    }
    
    private function isValidDateTime($datetime) {
        return (bool) strtotime($datetime);
    }
    
    private function sanitizeCustomerId($customer_id) {
        return filter_var($customer_id, FILTER_SANITIZE_NUMBER_INT);
    }
    
    private function sanitizeOTPData($otp_data) {
        if (!is_array($otp_data)) {
            return $otp_data;
        }
        
        // Remove any sensitive data that shouldn't be exposed
        unset($otp_data['internal_notes']);
        unset($otp_data['debug_info']);
        
        // Sanitize string values
        foreach ($otp_data as $key => $value) {
            if (is_string($value)) {
                $otp_data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $otp_data;
    }

    /**
     * Additional security method: Rate limiting check
     */
    public function checkRateLimit($customer_id, $max_attempts = 5, $time_window_minutes = 15) {
        try {
            $customer_id = $this->sanitizeCustomerId($customer_id);
            
            $query = "SELECT COUNT(*) as attempt_count 
                     FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':minutes', $time_window_minutes, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['attempt_count'] < $max_attempts);
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false; // Fail closed - assume rate limited on error
        }
    }

    /**
     * Additional security method: Invalidate all previous OTPs for customer
     */
    public function invalidatePreviousOTPs($customer_id) {
        try {
            $customer_id = $this->sanitizeCustomerId($customer_id);
            
            $query = "UPDATE " . $this->table_name . " 
                     SET used = 1 
                     WHERE customer_id = :customer_id 
                     AND used = 0";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Invalidate previous OTPs error: " . $e->getMessage());
            return false;
        }
    }
}
?>