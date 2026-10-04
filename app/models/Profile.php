<?php
// app/models/Profile.php
class Profile {
    private $conn;
    private $table_users = "customers";
    private $table_otp = "email_verification_otps";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get user profile with address information
    public function getUserProfile($user_id) {
        // Validate user_id
        if (!is_numeric($user_id) || $user_id <= 0) {
            return null;
        }
        
        $query = "SELECT 
                    c.id, c.name, c.email, c.phone, c.contact,
                    c.email_verified_at, c.phone_verified_at,
                    c.address, c.city, c.verification_status,
                    c.created_at, c.updated_at
                  FROM " . $this->table_users . " c
                  WHERE c.id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add verification status flags
        if ($user) {
            $user['email_verified'] = !empty($user['email_verified_at']);
            $user['phone_verified'] = !empty($user['phone_verified_at']);
            
            // Get city ID from city name for form selection
            if (!empty($user['city'])) {
                $city_query = "SELECT id FROM delivery_cities WHERE city_name = :city_name LIMIT 1";
                $city_stmt = $this->conn->prepare($city_query);
                $city_stmt->bindValue(":city_name", $user['city'], PDO::PARAM_STR);
                $city_stmt->execute();
                $city_result = $city_stmt->fetch(PDO::FETCH_ASSOC);
                $user['city_id'] = $city_result['id'] ?? null;
            }
            
            // Sanitize output data for display
            $user['name'] = $this->escapeOutput($user['name']);
            $user['email'] = $this->escapeOutput($user['email']);
            if ($user['phone']) {
                $user['phone'] = $this->escapeOutput($user['phone']);
            }
            if ($user['address']) {
                $user['address'] = $this->escapeOutput($user['address']);
            }
            if ($user['city']) {
                $user['city'] = $this->escapeOutput($user['city']);
            }
        }
        
        return $user;
    }
    
    // Update user profile with address information
    public function updateProfile($user_id, $data) {
        // Validate user_id
        if (!is_numeric($user_id) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Validate input data
        $validation_result = $this->validateProfileData($data);
        if (!$validation_result['success']) {
            error_log("Validation error: " . $validation_result['message']);
            return $validation_result;
        }
        
        // Check for duplicate email (if provided and changed)
        if (!empty($data['email'])) {
            if ($this->isEmailExists($data['email'], $user_id)) {
                error_log("Email already exists: " . $data['email']);
                return ['success' => false, 'message' => 'This email is already registered'];
            }
        }
        
        // Check for duplicate phone (if provided and changed)
        if (!empty($data['phone'])) {
            if ($this->isPhoneExists($data['phone'], $user_id)) {
                error_log("Phone already exists: " . $data['phone']);
                return ['success' => false, 'message' => 'This phone number is already registered'];
            }
        }
        
        // Get current user data to compare changes
        $current_user = $this->getUserProfile($user_id);
        if (!$current_user) {
            error_log("User not found with ID: " . $user_id);
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Begin transaction for data consistency
        $this->conn->beginTransaction();
        
        try {
            // Get city name from city ID
            $city_name = '';
            if (!empty($data['city'])) {
                // data['city'] is the ID from the form
                $city_name = $this->getCityNameById($data['city']);
                if (empty($city_name)) {
                    throw new Exception('Invalid city selected');
                }
            } else {
                // Keep existing city if not provided
                $city_name = $current_user['city'];
            }
            
            // Prepare values for update
            $name = trim($data['name'] ?? $current_user['name']);
            $email = trim($data['email'] ?? $current_user['email']);
            $phone = trim($data['phone'] ?? $current_user['phone']);
            $contact = trim($data['contact'] ?? $current_user['contact'] ?? '');
            $address = trim($data['address'] ?? $current_user['address'] ?? '');
            
            // Build update query
            $query = "UPDATE " . $this->table_users . " 
                     SET name = :name, email = :email, phone = :phone, contact = :contact,
                         address = :address, city = :city, 
                         updated_at = NOW()";
            
            // Check if email changed - reset email verification
            $email_changed = false;
            if ($current_user['email'] !== $email) {
                $query .= ", email_verified_at = NULL";
                $email_changed = true;
            }
            
            // Check if phone changed - reset phone verification
            $phone_changed = false;
            if ($current_user['phone'] !== $phone) {
                $query .= ", phone_verified_at = NULL";
                $phone_changed = true;
            }
            
            $query .= " WHERE id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":name", $name, PDO::PARAM_STR);
            $stmt->bindValue(":email", $email, PDO::PARAM_STR);
            $stmt->bindValue(":phone", $phone, PDO::PARAM_STR);
            $stmt->bindValue(":contact", $contact, PDO::PARAM_STR);
            $stmt->bindValue(":address", $address, PDO::PARAM_STR);
            $stmt->bindValue(":city", $city_name, PDO::PARAM_STR);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute profile update');
            }
            
            error_log("Profile updated successfully for user: " . $user_id);
            
            // Update session data securely
            $this->updateSessionData($data, $city_name);
            
            // Commit transaction
            $this->conn->commit();
            
            $message = 'Profile updated successfully';
            
            // Add notification if verification status was reset
            if ($email_changed && $phone_changed) {
                $message .= '. Email and phone verification required.';
            } elseif ($email_changed) {
                $message .= '. Email verification required.';
            } elseif ($phone_changed) {
                $message .= '. Phone verification required.';
            }
            
            return ['success' => true, 'message' => $message];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollBack();
            $error_msg = $e->getMessage();
            $this->logError("Profile update error", $error_msg, $user_id);
            error_log("Profile update exception: " . $error_msg);
            return ['success' => false, 'message' => 'Failed to update profile: ' . $error_msg];
        }
    }

    // Get city name by ID
    private function getCityNameById($city_id) {
        if (!is_numeric($city_id) || $city_id <= 0) {
            return '';
        }
        
        try {
            $query = "SELECT city_name FROM delivery_cities WHERE id = :city_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":city_id", $city_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['city_name'] ?? '';
        } catch (Exception $e) {
            $this->logError("Get city name error", $e->getMessage());
            return '';
        }
    }

    // Check if OTP exists and is valid
    public function hasPendingOTP($user_id) {
        // Validate user_id
        if (!is_numeric($user_id) || $user_id <= 0) {
            return false;
        }
        
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_otp . " 
                     WHERE user_id = :user_id AND expires_at > NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (Exception $e) {
            $this->logError("Has Pending OTP Error", $e->getMessage(), $user_id);
            return false;
        }
    }
    
    // Validate profile data with improved validation including address fields
    private function validateProfileData($data) {
        // Check required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }
        
        // Validate name length and format (more inclusive for international names)
        $name = trim($data['name']);
        if (strlen($name) > 255) {
            return ['success' => false, 'message' => 'Name is too long'];
        }
        
        // More inclusive name validation for international characters
        if (!preg_match('/^[\p{L}\s\-\.\'\p{M}]+$/u', $name)) {
            return ['success' => false, 'message' => 'Name contains invalid characters'];
        }
        
        // Validate email
        if (!empty($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            if (strlen($email) > 255) {
                return ['success' => false, 'message' => 'Email is too long'];
            }
        }
        
        // Validate phone with better international support
        if (!empty($data['phone'])) {
            $phone = trim($data['phone']);
            if (strlen($phone) > 20) {
                return ['success' => false, 'message' => 'Phone number is too long'];
            }
            
            // More flexible phone validation for international numbers
            if (!preg_match('/^[\+\-\s\d\(\)]{8,20}$/', $phone)) {
                return ['success' => false, 'message' => 'Invalid phone number format'];
            }
        }
        
        // Validate city (required)
        if (empty($data['city'])) {
            return ['success' => false, 'message' => 'City is required'];
        }
        
        // Validate address
        if (!empty($data['address'])) {
            $address = trim($data['address']);
            if (strlen($address) > 500) {
                return ['success' => false, 'message' => 'Address must not exceed 500 characters'];
            }
        }
        
        return ['success' => true];
    }
    
    // Sanitize input for database storage (preserves data integrity)
    private function sanitizeForDatabase($input) {
        if ($input === null) {
            return null;
        }
        
        $input = trim($input);
        // Only strip tags for database storage, preserve special characters
        $input = strip_tags($input);
        return $input;
    }
    
    // Escape output for HTML display
    private function escapeOutput($output) {
        if ($output === null) {
            return null;
        }
        
        return htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }
    
    // Update session data securely including address information
    private function updateSessionData($data, $city_name) {
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        
        if (isset($data['name'])) {
            $_SESSION['customer_name'] = $this->escapeOutput($data['name']);
        }
        if (isset($data['email'])) {
            $_SESSION['customer_email'] = $this->escapeOutput($data['email']);
        }
        
        // Update city preference in session
        if (!empty($data['city'])) {
            $_SESSION['user_city_id'] = $data['city'];
            $_SESSION['user_city_name'] = $city_name;
            $_SESSION['city_preference_set'] = true;
        }
        
        // Set secure session cookie parameters
        $this->setSecureSessionParams();
    }
    
    // Set secure session parameters
    private function setSecureSessionParams() {
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        session_set_cookie_params([
            'lifetime' => 0, // Until browser closes
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $is_https, // Only over HTTPS if available
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Strict' // CSRF protection
        ]);
    }
    
    // Validate CSRF token with timing-safe comparison
    private function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Generate CSRF token (to be called in controller)
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Check if email already exists (for duplicate prevention)
    public function isEmailExists($email, $exclude_user_id = null) {
        $email = $this->sanitizeForDatabase($email);
        
        $query = "SELECT COUNT(*) as count FROM " . $this->table_users . " 
                 WHERE email = :email";
        
        if ($exclude_user_id) {
            $query .= " AND id != :exclude_user_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":email", $email, PDO::PARAM_STR);
        
        if ($exclude_user_id) {
            $stmt->bindValue(":exclude_user_id", $exclude_user_id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    // Check if phone already exists (for duplicate prevention)
    public function isPhoneExists($phone, $exclude_user_id = null) {
        $phone = $this->sanitizeForDatabase($phone);
        
        $query = "SELECT COUNT(*) as count FROM " . $this->table_users . " 
                 WHERE phone = :phone";
        
        if ($exclude_user_id) {
            $query .= " AND id != :exclude_user_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":phone", $phone, PDO::PARAM_STR);
        
        if ($exclude_user_id) {
            $stmt->bindValue(":exclude_user_id", $exclude_user_id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    // Secure logging without sensitive data
    private function logError($context, $error_message, $user_id = null) {
        $log_message = $context . ": " . $error_message;
        if ($user_id) {
            $log_message .= " [User ID: " . $user_id . "]";
        }
        
        // Log without sensitive user data
        error_log($log_message);
    }
    
    // Rate limiting for OTP attempts (additional security)
    public function isRateLimited($user_id, $action = 'otp', $max_attempts = 5, $time_window = 900) {
        // 15 minutes window by default
        $query = "SELECT COUNT(*) as attempts FROM rate_limits 
                 WHERE user_id = :user_id AND action = :action 
                 AND created_at > DATE_SUB(NOW(), INTERVAL :time_window SECOND)";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":action", $action, PDO::PARAM_STR);
            $stmt->bindValue(":time_window", $time_window, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['attempts'] >= $max_attempts;
        } catch (Exception $e) {
            $this->logError("Rate limit check error", $e->getMessage(), $user_id);
            return false; // Fail open for security
        }
    }
    
    // Record OTP attempt for rate limiting
    public function recordOTPAttempt($user_id, $success = true) {
        $query = "INSERT INTO rate_limits (user_id, action, success, ip_address, user_agent, created_at) 
                 VALUES (:user_id, 'otp', :success, :ip, :ua, NOW())";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":success", $success, PDO::PARAM_BOOL);
            $stmt->bindValue(":ip", $this->getClientIP(), PDO::PARAM_STR);
            $stmt->bindValue(":ua", $_SERVER['HTTP_USER_AGENT'] ?? '', PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            $this->logError("Record OTP attempt error", $e->getMessage(), $user_id);
        }
    }
    
    // Get client IP securely
    private function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Check for forwarded IP headers (behind proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
?>