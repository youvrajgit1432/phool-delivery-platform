<?php
// app/models/Security.php

class Security {
    private $conn;
    private $table_users = "customers";
    private $table_login_history = "login_history";
    private $table_security_settings = "user_security_settings";
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get security settings
    public function getSecuritySettings($user_id) {
        // Validate user_id
        if (!$this->validateUserId($user_id)) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Get user's password status
        $user = $this->getUserProfile($user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Get 2FA settings
        $two_factor_enabled = $this->is2FAEnabled($user_id);
        
        return [
            'success' => true,
            'two_factor_enabled' => $two_factor_enabled,
            'last_password_change' => $this->getLastPasswordChange($user_id),
            'has_default_password' => $user['has_default_password'] ?? false
        ];
    }
    
    // Validate user ID
    private function validateUserId($user_id) {
        return is_numeric($user_id) && $user_id > 0;
    }
    
    // Get user profile
    private function getUserProfile($user_id) {
        $query = "SELECT id, name, email, phone, password, registration_type, 
                         email_verified_at, phone_verified_at, created_at, updated_at 
                  FROM " . $this->table_users . " 
                  WHERE id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $user['email_verified'] = !empty($user['email_verified_at']);
                $user['phone_verified'] = !empty($user['phone_verified_at']);
                $user['has_default_password'] = $this->hasDefaultPassword($user['password'], $user['registration_type']);
            }
            
            return $user;
        } catch (PDOException $e) {
            error_log("Database error in getUserProfile: " . $e->getMessage());
            return false;
        }
    }
    
    // Check if user has default password
    private function hasDefaultPassword($password_hash, $registration_type) {
        // For guest users or users with default password
        $default_password = 'DemoGuest';
        
        // Check if password matches the default password
        if ($registration_type === 'guest') {
            return true;
        }
        
        // Check if the stored password matches the default password hash
        if (password_verify($default_password, $password_hash)) {
            return true;
        }
        
        return false;
    }
    
    // Check if 2FA is enabled
    private function is2FAEnabled($user_id) {
        $query = "SELECT two_factor_enabled FROM " . $this->table_security_settings . " 
                  WHERE user_id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (bool)$result['two_factor_enabled'] : false;
        } catch (PDOException $e) {
            error_log("Database error in is2FAEnabled: " . $e->getMessage());
            return false;
        }
    }
    
    // Get last password change date
    private function getLastPasswordChange($user_id) {
        $query = "SELECT updated_at FROM " . $this->table_users . " 
                  WHERE id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['updated_at'] : null;
        } catch (PDOException $e) {
            error_log("Database error in getLastPasswordChange: " . $e->getMessage());
            return null;
        }
    }
    
    // Change password
    public function changePassword($user_id, $data) {
        // Validate user_id
        if (!$this->validateUserId($user_id)) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        // Sanitize input data
        $data = $this->sanitizeInput($data);
        
        // Validate required fields
        $required = ['new_password', 'confirm_password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }
        
        // Check if new passwords match
        if ($data['new_password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        // Validate password strength
        $password_validation = $this->validatePassword($data['new_password']);
        if (!$password_validation['valid']) {
            return ['success' => false, 'message' => $password_validation['message']];
        }
        
        // Get user data to check if they have default password
        $user = $this->getUserProfile($user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $has_default_password = $user['has_default_password'] ?? false;
        
        // If user doesn't have default password, verify current password
        if (!$has_default_password) {
            if (empty($data['current_password'])) {
                return ['success' => false, 'message' => 'Current password is required'];
            }
            
            // Verify current password
            if (!$this->verifyCurrentPassword($user_id, $data['current_password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
        }
        
        // Update password
        return $this->updatePassword($user_id, $data['new_password'], $has_default_password);
    }
    
    // Validate password strength
    private function validatePassword($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        // Additional security checks
        if (preg_match('/\s/', $password)) {
            return ['valid' => false, 'message' => 'Password should not contain spaces'];
        }
        
        // Check for common patterns
        $common_patterns = ['123456', 'password', 'qwerty', 'abc123'];
        foreach ($common_patterns as $pattern) {
            if (stripos($password, $pattern) !== false) {
                return ['valid' => false, 'message' => 'Password contains common patterns that are easy to guess'];
            }
        }
        
        return ['valid' => true, 'message' => 'Password is strong'];
    }
    
    // Verify current password
    private function verifyCurrentPassword($user_id, $current_password) {
        $query = "SELECT password FROM " . $this->table_users . " 
                  WHERE id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $user_data && password_verify($current_password, $user_data['password']);
        } catch (PDOException $e) {
            error_log("Database error in verifyCurrentPassword: " . $e->getMessage());
            return false;
        }
    }
    
    // Update password in database
    private function updatePassword($user_id, $new_password, $has_default_password) {
        $update_query = "UPDATE " . $this->table_users . " 
                        SET password = :password, 
                            registration_type = 'direct',
                            updated_at = NOW() 
                        WHERE id = :user_id";
        
        try {
            $this->conn->beginTransaction();
            
            $update_stmt = $this->conn->prepare($update_query);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt->bindValue(":password", $hashed_password);
            $update_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                // Log password change event
                $this->logSecurityEvent($user_id, 'password_change', 'Password changed successfully');
                
                // Invalidate all other sessions for this user (optional security measure)
                $this->invalidateOtherSessions($user_id);
                
                $this->conn->commit();
                
                return [
                    'success' => true, 
                    'message' => $has_default_password ? 
                        'Password set successfully! You can now use your new password to login.' : 
                        'Password changed successfully'
                ];
            }
            
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to change password'];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Database error in updatePassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Toggle 2FA
    public function toggle2FA($user_id, $enable) {
        // Validate user_id
        if (!$this->validateUserId($user_id)) {
            return ['success' => false, 'message' => 'Invalid user ID'];
        }
        
        try {
            $this->conn->beginTransaction();
            
            // Check if settings exist
            $check_query = "SELECT id FROM " . $this->table_security_settings . " 
                           WHERE user_id = :user_id";
            
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing settings
                $query = "UPDATE " . $this->table_security_settings . " 
                         SET two_factor_enabled = :enabled, 
                             updated_at = NOW() 
                         WHERE user_id = :user_id";
            } else {
                // Insert new settings
                $query = "INSERT INTO " . $this->table_security_settings . " 
                         (user_id, two_factor_enabled, created_at, updated_at)
                         VALUES (:user_id, :enabled, NOW(), NOW())";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":enabled", $enable ? 1 : 0, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Log 2FA event
                $event_type = $enable ? '2fa_enabled' : '2fa_disabled';
                $event_description = $enable ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled';
                $this->logSecurityEvent($user_id, $event_type, $event_description);
                
                $this->conn->commit();
                return [
                    'success' => true, 
                    'message' => $enable ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled'
                ];
            }
            
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to update 2FA settings'];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Database error in toggle2FA: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Get login history
    public function getLoginHistory($user_id, $limit = 10) {
        // Validate user_id
        if (!$this->validateUserId($user_id)) {
            return ['success' => false, 'message' => 'Invalid user ID', 'data' => []];
        }
        
        // Validate and sanitize limit
        $limit = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 10]
        ]);
        
        $query = "SELECT * FROM " . $this->table_login_history . " 
                  WHERE user_id = :user_id 
                  ORDER BY login_time DESC 
                  LIMIT :limit";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sanitize output data
            $history = $this->sanitizeOutput($history);
            
            return ['success' => true, 'data' => $history];
        } catch (PDOException $e) {
            error_log("Database error in getLoginHistory: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve login history', 'data' => []];
        }
    }
    
    // Log security event
    private function logSecurityEvent($user_id, $event_type, $description) {
        $allowed_events = ['password_change', '2fa_enabled', '2fa_disabled', 'login', 'logout', 'profile_update'];
        
        if (!in_array($event_type, $allowed_events)) {
            error_log("Invalid security event type: " . $event_type);
            return false;
        }
        
        $query = "INSERT INTO security_events 
                 (user_id, event_type, description, ip_address, user_agent, created_at)
                 VALUES (:user_id, :event_type, :description, :ip_address, :user_agent, NOW())";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":event_type", $event_type);
            $stmt->bindValue(":description", $description);
            $stmt->bindValue(":ip_address", $_SERVER['REMOTE_ADDR'] ?? '');
            $stmt->bindValue(":user_agent", $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error in logSecurityEvent: " . $e->getMessage());
            return false;
        }
    }
    
    // Sanitize input data
    private function sanitizeInput($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    // Sanitize output data
    private function sanitizeOutput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeOutput($value);
                } else if (is_string($value)) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        }
        return $data;
    }
    
    // Invalidate other sessions (security measure)
    private function invalidateOtherSessions($user_id) {
        // This is a placeholder for session invalidation logic
        // In a real implementation, you might:
        // 1. Store session tokens in database and mark others as invalid
        // 2. Use Redis to track active sessions
        // 3. Implement session regeneration
        
        // For now, we'll just regenerate the session ID if session exists
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        
        return true;
    }
    
    // Additional security method: Check if user is locked out
    public function isAccountLocked($user_id) {
        $query = "SELECT failed_attempts, lockout_until FROM " . $this->table_security_settings . " 
                  WHERE user_id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['lockout_until']) {
                $lockout_until = strtotime($result['lockout_until']);
                if ($lockout_until > time()) {
                    return true; // Account is still locked
                }
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Database error in isAccountLocked: " . $e->getMessage());
            return false;
        }
    }
}