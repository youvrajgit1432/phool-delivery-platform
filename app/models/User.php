<?php
// app/models/User.php

class User {
    private $conn;
    private $table_name = "users";
    
    public $id;
    public $name;
    public $email;
    public $phone;
    public $password;
    public $address;
    public $city;
    public $street;
    public $created_at;
    public $updated_at;
    public $email_verified;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create new user
    public function create() {
        // Validate required fields
        if (!$this->validateUserData()) {
            return false;
        }
        
        $query = "INSERT INTO " . $this->table_name . " 
                 SET name=:name, email=:email, phone=:phone, password=:password, 
                     address=:address, city=:city, street=:street, created_at=NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and validate inputs
        $this->name = $this->sanitizeInput($this->name);
        $this->email = $this->sanitizeAndValidateEmail($this->email);
        $this->phone = $this->sanitizeAndValidatePhone($this->phone);
        $this->password = $this->sanitizeInput($this->password);
        $this->address = $this->sanitizeInput($this->address);
        $this->city = $this->sanitizeInput($this->city);
        $this->street = $this->sanitizeInput($this->street);
        
        // Validate inputs
        if (!$this->email || !$this->name) {
            return false;
        }
        
        // Hash password
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Bind parameters
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":street", $this->street);
        
        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
        } catch (PDOException $e) {
            // Log error instead of exposing it
            error_log("User creation error: " . $e->getMessage());
        }
        
        return false;
    }
    
    // Check if email exists
    public function emailExists() {
        if (!$this->email) {
            return false;
        }
        
        $query = "SELECT id, name, password, email_verified FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        
        try {
            $stmt->execute();
            
            $num = $stmt->rowCount();
            
            if ($num > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->password = $row['password'];
                $this->email_verified = $row['email_verified'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("Email exists check error: " . $e->getMessage());
        }
        
        return false;
    }
    
    // Check if phone exists
    public function phoneExists() {
        if (!$this->phone) {
            return false;
        }
        
        $query = "SELECT id FROM " . $this->table_name . " WHERE phone = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->phone);
        
        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Phone exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user by ID
    public function readOne() {
        if (!$this->id || !is_numeric($this->id)) {
            return false;
        }
        
        $query = "SELECT name, email, phone, address, city, street, created_at, email_verified 
                 FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        try {
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $this->name = $row['name'];
                $this->email = $row['email'];
                $this->phone = $row['phone'];
                $this->address = $row['address'];
                $this->city = $row['city'];
                $this->street = $row['street'];
                $this->created_at = $row['created_at'];
                $this->email_verified = $row['email_verified'];
                return true;
            }
        } catch (PDOException $e) {
            error_log("User read error: " . $e->getMessage());
        }
        
        return false;
    }
    
    // Update user profile
    public function update() {
        if (!$this->id || !is_numeric($this->id)) {
            return false;
        }
        
        // Validate required fields
        if (!$this->validateUserData(true)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table_name . " 
                 SET name=:name, email=:email, phone=:phone, 
                     address=:address, city=:city, street=:street, updated_at=NOW() 
                 WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and validate inputs
        $this->name = $this->sanitizeInput($this->name);
        $this->email = $this->sanitizeAndValidateEmail($this->email);
        $this->phone = $this->sanitizeAndValidatePhone($this->phone);
        $this->address = $this->sanitizeInput($this->address);
        $this->city = $this->sanitizeInput($this->city);
        $this->street = $this->sanitizeInput($this->street);
        
        // Validate inputs
        if (!$this->email || !$this->name) {
            return false;
        }
        
        // Bind parameters
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":street", $this->street);
        $stmt->bindParam(":id", $this->id);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            return false;
        }
    }
    
    // Find or create user from social login
    public function findOrCreateFromSocial($provider, $socialData) {
        // Validate provider and social data
        if (!$this->validateSocialProvider($provider) || !$this->validateSocialData($socialData)) {
            return false;
        }
        
        // First, try to find by social account
        $query = "SELECT u.* FROM users u 
                 JOIN user_social_accounts usa ON u.id = usa.user_id 
                 WHERE usa.provider = ? AND usa.provider_id = ? 
                 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $provider);
        $stmt->bindParam(2, $socialData['id']);
        
        try {
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // User found, update social account info
                $this->updateSocialAccount($user['id'], $provider, $socialData);
                $this->id = $user['id'];
                $this->name = $user['name'];
                $this->email = $user['email'];
                return $user;
            }
            
            // Try to find by email
            if (!empty($socialData['email'])) {
                $this->email = $this->sanitizeAndValidateEmail($socialData['email']);
                if ($this->email && $this->emailExists()) {
                    // Link social account to existing user
                    $this->linkSocialAccount($this->id, $provider, $socialData);
                    return $this->readOne() ? [
                        'id' => $this->id,
                        'name' => $this->name,
                        'email' => $this->email
                    ] : false;
                }
            }
            
            // Create new user
            return $this->createFromSocial($provider, $socialData);
            
        } catch (PDOException $e) {
            error_log("Social login error: " . $e->getMessage());
            return false;
        }
    }

    // Create user from social login
    private function createFromSocial($provider, $socialData) {
        $this->name = $this->sanitizeInput($socialData['name'] ?? 'Social User');
        $this->email = $this->sanitizeAndValidateEmail($socialData['email'] ?? null);
        $this->email_verified = 1; // Social emails are typically verified
        
        // Validate required fields
        if (!$this->name) {
            return false;
        }
        
        // Generate a random password for social users
        $this->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (name, email, password, email_verified, created_at) 
                 VALUES (:name, :email, :password, :email_verified, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":email_verified", $this->email_verified);
        
        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                $this->linkSocialAccount($this->id, $provider, $socialData);
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'email' => $this->email
                ];
            }
        } catch (PDOException $e) {
            error_log("Social user creation error: " . $e->getMessage());
        }
        
        return false;
    }

    // Link social account to user - FIXED VERSION
    private function linkSocialAccount($userId, $provider, $socialData) {
        if (!$userId || !$this->validateSocialProvider($provider)) {
            return false;
        }
        
        $query = "INSERT INTO user_social_accounts 
                 (user_id, provider, provider_id, email, name, profile_picture, access_token, refresh_token, created_at) 
                 VALUES (:user_id, :provider, :provider_id, :email, :name, :profile_picture, :access_token, :refresh_token, NOW())
                 ON DUPLICATE KEY UPDATE 
                 email = VALUES(email), name = VALUES(name), profile_picture = VALUES(profile_picture), 
                 access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), updated_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and validate inputs
        $provider_id = $this->sanitizeInput($socialData['id']);
        $email = $this->sanitizeAndValidateEmail($socialData['email'] ?? null);
        $name = $this->sanitizeInput($socialData['name'] ?? null);
        $profile_picture = $this->sanitizeUrl($socialData['picture'] ?? null);
        
        // Handle tokens securely
        $access_token = $socialData['access_token'] ? $this->encryptToken($socialData['access_token']) : null;
        $refresh_token = $socialData['refresh_token'] ? $this->encryptToken($socialData['refresh_token'] ?? null) : null;
        
        // Validate required fields
        if (!$provider_id) {
            return false;
        }
        
        // Bind parameters with proper handling for NULL values
        $stmt->bindValue(":user_id", $userId);
        $stmt->bindValue(":provider", $provider);
        $stmt->bindValue(":provider_id", $provider_id);
        $stmt->bindValue(":email", $email);
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":profile_picture", $profile_picture);
        $stmt->bindValue(":access_token", $access_token);
        $stmt->bindValue(":refresh_token", $refresh_token);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Social account linking error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update social account information
    private function updateSocialAccount($userId, $provider, $socialData) {
        if (!$userId || !$this->validateSocialProvider($provider)) {
            return false;
        }
        
        $query = "UPDATE user_social_accounts 
                 SET email = :email, name = :name, profile_picture = :profile_picture, 
                     access_token = :access_token, refresh_token = :refresh_token, updated_at = NOW()
                 WHERE user_id = :user_id AND provider = :provider";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and validate inputs
        $email = $this->sanitizeAndValidateEmail($socialData['email'] ?? null);
        $name = $this->sanitizeInput($socialData['name'] ?? null);
        $profile_picture = $this->sanitizeUrl($socialData['picture'] ?? null);
        
        // Handle tokens securely
        $access_token = $socialData['access_token'] ? $this->encryptToken($socialData['access_token']) : null;
        $refresh_token = $socialData['refresh_token'] ? $this->encryptToken($socialData['refresh_token'] ?? null) : null;
        
        // Bind parameters with proper handling for NULL values
        $stmt->bindValue(":user_id", $userId);
        $stmt->bindValue(":provider", $provider);
        $stmt->bindValue(":email", $email);
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":profile_picture", $profile_picture);
        $stmt->bindValue(":access_token", $access_token);
        $stmt->bindValue(":refresh_token", $refresh_token);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Social account update error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user by social account
    public function getBySocialAccount($provider, $providerId) {
        if (!$this->validateSocialProvider($provider) || !$providerId) {
            return false;
        }
        
        $query = "SELECT u.* FROM users u 
                 JOIN user_social_accounts usa ON u.id = usa.user_id 
                 WHERE usa.provider = ? AND usa.provider_id = ? 
                 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $provider);
        $stmt->bindParam(2, $providerId);
        
        try {
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get by social account error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get user by email
    public function getByEmail($email) {
        $email = $this->sanitizeAndValidateEmail($email);
        if (!$email) {
            return false;
        }
        
        $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        
        try {
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get by email error: " . $e->getMessage());
            return false;
        }
    }
    
    // Security helper methods
    
    private function sanitizeInput($input) {
        if ($input === null) {
            return null;
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    private function sanitizeAndValidateEmail($email) {
        $email = $this->sanitizeInput($email);
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return null;
    }
    
    private function sanitizeAndValidatePhone($phone) {
        $phone = $this->sanitizeInput($phone);
        // Basic phone validation - adjust based on requirements
        if ($phone && preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $phone)) {
            return $phone;
        }
        return null;
    }
    
    private function sanitizeUrl($url) {
        if (!$url) {
            return null;
        }
        $url = $this->sanitizeInput($url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return null;
    }
    
    private function validateUserData($isUpdate = false) {
        // Basic validation rules
        if (!$this->name || strlen($this->name) < 2 || strlen($this->name) > 255) {
            return false;
        }
        
        if (!$this->email || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        if ($this->phone && !preg_match('/^[0-9\-\+\s\(\)]{10,20}$/', $this->phone)) {
            return false;
        }
        
        // For creation, password is required
        if (!$isUpdate && (!$this->password || strlen($this->password) < 6)) {
            return false;
        }
        
        return true;
    }
    
    private function validateSocialProvider($provider) {
        $allowedProviders = ['google', 'facebook', 'twitter', 'github'];
        return in_array($provider, $allowedProviders);
    }
    
    private function validateSocialData($socialData) {
        return !empty($socialData['id']) && is_string($socialData['id']);
    }
    
    private function encryptToken($token) {
        // In a real application, use proper encryption
        // This is a basic example - consider using libsodium or OpenSSL
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'your-encryption-key-here';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decryptToken($encryptedToken) {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'your-encryption-key-here';
        $data = base64_decode($encryptedToken);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
?>