<?php
// app/controllers/CityController.php

class CityController {
    private $db;
    private $pathConfig;
    
    public function __construct($database = null) {
        // Safely initialize database connection
        try {
            if ($database instanceof PDO) {
                $this->db = $database;
            } elseif ($database && method_exists($database, 'getConnection')) {
                $this->db = $database->getConnection();
            } else {
                // Only create new connection if none provided AND we have Database class
                if (class_exists('Database')) {
                    $database = new Database();
                    $this->db = $database->getConnection();
                } else {
                    $this->db = null;
                }
            }
        } catch (Exception $e) {
            error_log("CityController database connection failed: " . $e->getMessage());
            $this->db = null;
        }
        
        // Safely initialize PathConfig
        try {
            $this->pathConfig = PathConfig::getInstance();
        } catch (Exception $e) {
            error_log("PathConfig initialization failed: " . $e->getMessage());
            $this->pathConfig = null;
        }
    }
    
    public function setCity() {
        // Clear output buffer safely
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        
        try {
            // Safe session start
            if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            
            // Get input data safely
            $input = file_get_contents('php://input');
            if (empty($input)) {
                throw new Exception('No input data received');
            }
            
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input: ' . json_last_error_msg());
            }
            
            $cityId = $data['city_id'] ?? '';
            $cityName = $data['city_name'] ?? '';
            $deliveryFee = $data['delivery_fee'] ?? 0;
            $remember = $data['remember'] ?? false;
            
            if (empty($cityId) || empty($cityName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid city data']);
                exit;
            }
            
            // Set city in session
            $_SESSION['user_city_id'] = $cityId;
            $_SESSION['user_city_name'] = $cityName;
            $_SESSION['delivery_fee'] = floatval($deliveryFee);
            $_SESSION['city_preference_shown'] = true;
            $_SESSION['city_preference_set'] = true;
            $_SESSION['show_city_modal'] = false;
            
            // Save to database if user is logged in and wants to remember
            $userId = $_SESSION['customer_id'] ?? null;
            if ($userId && $remember && $this->db) {
                $this->saveCityToDatabase($userId, $cityId, $cityName, $deliveryFee);
            }
            
            // Save to session for guest users
            if (!$userId && $remember) {
                $_SESSION['guest_city_preference'] = [
                    'city_id' => $cityId,
                    'city_name' => $cityName,
                    'delivery_fee' => $deliveryFee
                ];
            }
            
            error_log("City preference set to: " . $cityName . " for user: " . ($userId ? $userId : 'guest'));
            
            $response = [
                'success' => true, 
                'message' => 'City preference set successfully',
                'city_name' => $cityName,
                'delivery_fee' => $deliveryFee,
                'reload_required' => false // Changed to false to prevent page reload
            ];
            
            // Add base_url only if pathConfig is available
            if ($this->pathConfig) {
                $response['base_url'] = $this->pathConfig->get('base_url');
            }
            
            echo json_encode($response);
            exit;
            
        } catch (Exception $e) {
            error_log("City preference setting error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error setting city preference: ' . $e->getMessage()]);
            exit;
        }
    }
    
    public function skipCity() {
        // Clear output buffer safely
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        
        // Safe session start
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        $_SESSION['city_preference_shown'] = true;
        $_SESSION['city_preference_set'] = false;
        $_SESSION['show_city_modal'] = false;
        
        $response = [
            'success' => true, 
            'message' => 'City selection skipped'
        ];
        
        // Add base_url only if pathConfig is available
        if ($this->pathConfig) {
            $response['base_url'] = $this->pathConfig->get('base_url');
        }
        
        echo json_encode($response);
        exit;
    }
    
    public function getCurrentCity() {
        // Clear output buffer safely
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        
        // Safe session start
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        
        $currentCity = [
            'city_id' => $_SESSION['user_city_id'] ?? null,
            'city_name' => $_SESSION['user_city_name'] ?? '',
            'delivery_fee' => $_SESSION['delivery_fee'] ?? 0
        ];
        
        $response = [
            'success' => true,
            'city' => $currentCity,
            'has_preference' => isset($_SESSION['city_preference_set']) && $_SESSION['city_preference_set'] === true
        ];
        
        // Add base_url only if pathConfig is available
        if ($this->pathConfig) {
            $response['base_url'] = $this->pathConfig->get('base_url');
        }
        
        echo json_encode($response);
        exit;
    }
    
    private function saveCityToDatabase($userId, $cityId, $cityName, $deliveryFee) {
        if (!$this->db) return false;
        
        try {
            // Check if preference exists
            $checkQuery = "SELECT id FROM user_city_preferences WHERE user_id = :user_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing
                $query = "UPDATE user_city_preferences SET city_id = :city_id, is_default = 1, updated_at = NOW() WHERE user_id = :user_id";
            } else {
                // Insert new
                $query = "INSERT INTO user_city_preferences (user_id, city_id, is_default, created_at, updated_at) 
                         VALUES (:user_id, :city_id, 1, NOW(), NOW())";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':city_id', $cityId);
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error saving city to database: " . $e->getMessage());
            return false;
        }
    }
}
?>