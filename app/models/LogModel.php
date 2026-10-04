<?php
class LogModel {
    private $conn;
    private $table_name = "customer_login_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Log a login attempt with enhanced security validation
     */
    public function logLoginAttempt($customer_id, $status, $ip_address, $user_agent = '') {
        try {
            // Validate and sanitize inputs
            $customer_id = $this->validateCustomerId($customer_id);
            $status = $this->validateStatus($status);
            $ip_address = $this->validateIpAddress($ip_address);
            $user_agent = $this->sanitizeUserAgent($user_agent);

            if (!$customer_id || !$status || !$ip_address) {
                error_log("Invalid input parameters for login attempt");
                return false;
            }

            $query = "INSERT INTO " . $this->table_name . " 
                     (customer_id, login_time, status, ip_address, user_agent) 
                     VALUES (:customer_id, NOW(), :status, :ip_address, :user_agent)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch (Exception $e) {
            error_log("Login log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update logout time with security validation - FIXED: Enhanced error handling
     */
    public function updateLogoutTime($log_id) {
        try {
            $log_id = $this->validateLogId($log_id);
            if (!$log_id) {
                error_log("Invalid log ID for logout time update");
                return false;
            }

            $query = "UPDATE " . $this->table_name . " 
                     SET logout_time = NOW() 
                     WHERE id = :log_id AND logout_time IS NULL";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Failed to update logout time for log ID: " . $log_id);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Logout time update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get login history for a customer with input validation
     */
    public function getLoginHistory($customer_id, $limit = 10) {
        try {
            $customer_id = $this->validateCustomerId($customer_id);
            $limit = $this->validateLimit($limit);
            
            if (!$customer_id || !$limit) {
                return [];
            }

            $query = "SELECT id, login_time, logout_time, status, ip_address, user_agent 
                     FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     ORDER BY login_time DESC 
                     LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Login history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed login attempts count with enhanced security
     */
    public function getFailedAttemptsCount($ip_address, $hours = 1) {
        try {
            $ip_address = $this->validateIpAddress($ip_address);
            $hours = $this->validateTimeRange($hours);
            
            if (!$ip_address || !$hours) {
                return 0;
            }

            $query = "SELECT COUNT(*) as attempt_count 
                     FROM " . $this->table_name . " 
                     WHERE ip_address = :ip_address 
                     AND status = 'failed' 
                     AND login_time >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['attempt_count'] ?? 0;
        } catch (Exception $e) {
            error_log("Failed attempts count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get last successful login with security validation
     */
    public function getLastSuccessfulLogin($customer_id) {
        try {
            $customer_id = $this->validateCustomerId($customer_id);
            if (!$customer_id) {
                return false;
            }

            $query = "SELECT id, login_time, logout_time, ip_address, user_agent 
                     FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     AND status = 'success' 
                     ORDER BY login_time DESC 
                     LIMIT 1, 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Last successful login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old login logs with validation
     */
    public function cleanupOldLogs($days = 90) {
        try {
            $days = $this->validateTimeRange($days);
            if (!$days || $days < 1) {
                error_log("Invalid days parameter for cleanup");
                return false;
            }

            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE login_time < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Login logs cleanup error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Security validation methods
     */
    
    private function validateCustomerId($customer_id) {
        if (!is_numeric($customer_id) || $customer_id <= 0) {
            return false;
        }
        return (int)$customer_id;
    }

    private function validateLogId($log_id) {
        if (!is_numeric($log_id) || $log_id <= 0) {
            return false;
        }
        return (int)$log_id;
    }

    private function validateStatus($status) {
        $allowed_statuses = ['success', 'failed', 'locked', 'expired'];
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }
        return $status;
    }

    private function validateIpAddress($ip_address) {
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            // If it's not a valid IP, log it but use a placeholder
            error_log("Invalid IP address detected: " . $ip_address);
            return '0.0.0.0';
        }
        return $ip_address;
    }

    private function sanitizeUserAgent($user_agent) {
        // Limit length and remove potentially harmful characters
        $user_agent = substr($user_agent, 0, 500);
        return htmlspecialchars($user_agent, ENT_QUOTES, 'UTF-8');
    }

    private function validateLimit($limit) {
        if (!is_numeric($limit) || $limit <= 0 || $limit > 1000) {
            return 10; // Default safe limit
        }
        return (int)$limit;
    }

    private function validateTimeRange($time) {
        if (!is_numeric($time) || $time <= 0 || $time > 8760) { // Max 1 year
            return 1; // Default safe range
        }
        return (int)$time;
    }

    /**
     * Additional security method: Check for suspicious activity
     */
    public function detectSuspiciousActivity($customer_id, $ip_address) {
        try {
            $customer_id = $this->validateCustomerId($customer_id);
            $ip_address = $this->validateIpAddress($ip_address);
            
            if (!$customer_id || !$ip_address) {
                return false;
            }

            // Check if this IP has been used for this customer before
            $query = "SELECT COUNT(DISTINCT ip_address) as unique_ips 
                     FROM " . $this->table_name . " 
                     WHERE customer_id = :customer_id 
                     AND login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $unique_ips = $result['unique_ips'] ?? 0;
            
            // If more than 5 unique IPs in 30 days, might be suspicious
            return $unique_ips > 5;
        } catch (Exception $e) {
            error_log("Suspicious activity detection error: " . $e->getMessage());
            return false;
        }
    }
}
?>