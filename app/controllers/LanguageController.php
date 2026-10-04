<?php
// app/controllers/LanguageController.php

class LanguageController {
    private $db;
    private $pathConfig;
    
    public function __construct($database = null) {
        if ($database) {
            if ($database instanceof PDO) {
                $this->db = $database;
            } else {
                $this->db = $database->getConnection();
            }
        }
        
        // Initialize PathConfig
        $this->pathConfig = PathConfig::getInstance();
    }
    
    public function changeLanguage() {
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get language from POST
            $language = $_POST['language'] ?? '';
            $allowedLanguages = ['en', 'ne'];
            
            if (!in_array($language, $allowedLanguages)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid language']);
                exit;
            }
            
            // Set language in session
            $_SESSION['user_language'] = $language;
            $_SESSION['language_preference_shown'] = true;
            $_SESSION['language_preference_set'] = true;
            $_SESSION['show_language_modal'] = false;
            
            // Save to database if user is logged in
            $userId = $_SESSION['customer_id'] ?? null;
            if ($userId) {
                $this->saveLanguageToDatabase($userId, $language);
            }
            
            error_log("Language changed to: " . $language . " for user: " . ($userId ? $userId : 'guest'));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Language changed successfully',
                'language' => $language,
                'reload_required' => true,
                'base_url' => $this->pathConfig->get('base_url') // Return base URL for client-side use
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Language change error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error changing language: ' . $e->getMessage()]);
            exit;
        }
    }
    
    public function setLanguage() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $language = $input['language'] ?? '';
            $remember = $input['remember'] ?? false;
            
            $allowedLanguages = ['en', 'ne'];
            
            if (!in_array($language, $allowedLanguages)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid language']);
                exit;
            }
            
            $userId = $_SESSION['customer_id'] ?? null;
            
            // Use LanguageHelper to set language
            LanguageHelper::setLanguage($language, $userId, $remember);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Language set successfully',
                'language' => $language,
                'reload_required' => true,
                'base_url' => $this->pathConfig->get('base_url')
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Language setting error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error setting language']);
            exit;
        }
    }
    
    public function skipLanguage() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        LanguageHelper::skipLanguagePreference();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Language selection skipped',
            'base_url' => $this->pathConfig->get('base_url')
        ]);
        exit;
    }
    
    public function getCurrentLanguage() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $currentLanguage = LanguageHelper::getCurrentLanguage();
        
        echo json_encode([
            'success' => true,
            'language' => $currentLanguage,
            'has_preference' => LanguageHelper::hasLanguagePreference(),
            'base_url' => $this->pathConfig->get('base_url'),
            'is_online' => $this->pathConfig->isOnline()
        ]);
        exit;
    }
    
    public function getLanguageAssets() {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        try {
            $language = $_GET['language'] ?? 'en';
            $allowedLanguages = ['en', 'ne'];
            
            if (!in_array($language, $allowedLanguages)) {
                $language = 'en';
            }
            
            // Load language file using PathConfig for correct file path
            $langFile = $this->pathConfig->filePath('admin_storage') . "/../app/translations/{$language}.php";
            
            if (file_exists($langFile)) {
                $translations = include $langFile;
            } else {
                // Fallback to default language
                $defaultLangFile = $this->pathConfig->filePath('admin_storage') . "/../app/translations/en.php";
                $translations = file_exists($defaultLangFile) ? include $defaultLangFile : [];
            }
            
            echo json_encode([
                'success' => true,
                'language' => $language,
                'translations' => $translations,
                'assets_path' => $this->pathConfig->get('assets')
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log("Language assets error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error loading language assets']);
            exit;
        }
    }
    
    private function saveLanguageToDatabase($userId, $language) {
        try {
            if (!$this->db) {
                $database = new Database();
                $this->db = $database->getConnection();
            }
            
            $checkQuery = "SELECT id FROM user_preferences WHERE user_id = :user_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $query = "UPDATE user_preferences SET language = :language, updated_at = NOW() WHERE user_id = :user_id";
            } else {
                $query = "INSERT INTO user_preferences (user_id, language, email_notifications, sms_notifications, newsletter_subscription, theme, created_at, updated_at) 
                         VALUES (:user_id, :language, 1, 1, 1, 'light', NOW(), NOW())";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':language', $language);
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error saving language to database: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper method to get correct URL for language-specific assets
    private function getLanguageAssetUrl($assetPath) {
        return $this->pathConfig->url($assetPath);
    }
    
    // Method to handle language-based redirects
    public function redirectToLanguageVersion($path = '') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $language = $_SESSION['user_language'] ?? 'en';
        $baseUrl = $this->pathConfig->get('base_url');
        
        // Add language parameter to URL if needed
        $redirectUrl = $baseUrl . '/' . ltrim($path, '/');
        if (strpos($redirectUrl, '?') === false) {
            $redirectUrl .= '?lang=' . $language;
        } else {
            $redirectUrl .= '&lang=' . $language;
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}
?>