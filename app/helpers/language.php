<?php
// app/helpers/language.php
class LanguageHelper {
    private static $currentLanguage = 'en';
    private static $translations = [];
    private static $initialized = false;
    
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user has language preference in session
        if (isset($_SESSION['user_language'])) {
            self::$currentLanguage = $_SESSION['user_language'];
        } elseif (isset($_SESSION['customer_id'])) {
            // If logged in, get from database
            self::loadLanguageFromDatabase();
        } elseif (self::loadGuestPreferences()) {
            // Guest preferences loaded from session
            // Do nothing, preferences already set
        } else {
            // For guest users, try to detect browser language
            self::detectBrowserLanguage();
        }
        
        // Load translations
        self::loadTranslations();
        self::$initialized = true;
    }
    
    // NEW METHOD: Load guest preferences from session
    private static function loadGuestPreferences() {
        if (isset($_SESSION['guest_language_preference'])) {
            $pref = $_SESSION['guest_language_preference'];
            // Check if preference is not too old (30 days)
            if (isset($pref['set_at']) && (time() - $pref['set_at']) < (30 * 24 * 60 * 60)) {
                self::$currentLanguage = $pref['language'];
                $_SESSION['user_language'] = $pref['language'];
                $_SESSION['language_preference_set'] = true;
                return true;
            }
        }
        return false;
    }
    
    private static function detectBrowserLanguage() {
        $browserLang = 'en'; // default
        
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        }
        
        // Only allow 'en' and 'ne'
        $allowedLanguages = ['en', 'ne'];
        if (!in_array($browserLang, $allowedLanguages)) {
            $browserLang = 'en';
        }
        
        self::$currentLanguage = $browserLang;
        $_SESSION['user_language'] = $browserLang;
        
        // Check if we should show modal for first-time users
        if (!isset($_SESSION['language_preference_shown']) && !isset($_SESSION['language_preference_set'])) {
            $_SESSION['show_language_modal'] = true;
        }
    }
    
    private static function loadLanguageFromDatabase() {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Updated query to use 'preferred_language' from customers table
            $query = "SELECT preferred_language FROM customers WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['customer_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($row['preferred_language'])) {
                    self::$currentLanguage = $row['preferred_language'];
                    $_SESSION['user_language'] = $row['preferred_language'];
                    $_SESSION['language_preference_set'] = true;
                }
            } else {
                // If no preference in DB, detect from browser
                self::detectBrowserLanguage();
            }
        } catch (Exception $e) {
            error_log("Error loading language from database: " . $e->getMessage());
            // Fallback to browser detection
            self::detectBrowserLanguage();
        }
    }
    
    private static function loadTranslations() {
        $langFile = __DIR__ . "/../translations/" . self::$currentLanguage . ".php";
        
        if (file_exists($langFile)) {
            self::$translations = include($langFile);
        } else {
            // Fallback to English
            $enFile = __DIR__ . "/../translations/en.php";
            if (file_exists($enFile)) {
                self::$translations = include($enFile);
            } else {
                self::$translations = [];
            }
        }
    }
    
    public static function getCurrentLanguage() {
        self::initialize();
        return self::$currentLanguage;
    }
    
    public static function setLanguage($language, $userId = null, $remember = false) {
        $allowedLanguages = ['en', 'ne'];
        
        if (in_array($language, $allowedLanguages)) {
            self::$currentLanguage = $language;
            $_SESSION['user_language'] = $language;
            $_SESSION['language_preference_shown'] = true;
            $_SESSION['language_preference_set'] = true;
            $_SESSION['show_language_modal'] = false;
            
            // If user is logged in or wants to remember, save to database
            if ($userId || $remember) {
                self::saveLanguageToDatabase($userId, $language);
            }
            
            // For guest users, store in session for persistence
            if (!$userId && $remember) {
                $_SESSION['guest_language_preference'] = [
                    'language' => $language,
                    'set_at' => time()
                ];
            }
            
            // Reset initialized flag to force reload of translations
            self::$initialized = false;
            
            // Reload translations
            self::loadTranslations();
            
            return true;
        }
        
        return false;
    }
    
    // ENHANCED METHOD: Auto-switch language after login with improved error handling
    public static function autoSwitchLanguageAfterLogin($userId) {
        try {
            $userLanguage = self::getUserLanguageFromDB($userId);
            if ($userLanguage) {
                // Set the language in session and helper
                self::$currentLanguage = $userLanguage;
                $_SESSION['user_language'] = $userLanguage;
                $_SESSION['language_preference_set'] = true;
                
                // Reset initialized flag to force reload of translations
                self::$initialized = false;
                
                // Reload translations with new language
                self::loadTranslations();
                
                error_log("Language auto-switched to: " . $userLanguage . " for user: " . $userId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error in autoSwitchLanguageAfterLogin: " . $e->getMessage());
            return false;
        }
    }
    
    // NEW METHOD: Get user language preference from database
    private static function getUserLanguageFromDB($customerId) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT preferred_language FROM customers WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $customerId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['preferred_language'] ?? null;
            }
        } catch (Exception $e) {
            error_log("Error fetching user language from DB: " . $e->getMessage());
        }
        
        return null;
    }
    
    // ENHANCED METHOD: Check if language modal should be shown with improved conditions
    public static function shouldShowLanguageModal() {
        self::initialize();
        
        // Don't show if preference is already set
        if (isset($_SESSION['language_preference_set']) && $_SESSION['language_preference_set'] === true) {
            return false;
        }
        
        // Don't show for API requests
        if (stripos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            return false;
        }
        
        // Don't show for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }
        
        // For logged-in users, use their preference from database
        if (isset($_SESSION['customer_id'])) {
            try {
                $userLanguage = self::getUserLanguageFromDB($_SESSION['customer_id']);
                if ($userLanguage) {
                    $_SESSION['user_language'] = $userLanguage;
                    $_SESSION['language_preference_set'] = true;
                    return false;
                }
            } catch (Exception $e) {
                error_log("Error getting user language: " . $e->getMessage());
            }
        }
        
        // Show modal only for first-time users who haven't set preference
        return isset($_SESSION['show_language_modal']) && $_SESSION['show_language_modal'] === true;
    }
    
    // NEW METHOD: Force language change without saving to database
    public static function forceLanguage($language) {
        $allowedLanguages = ['en', 'ne'];
        
        if (in_array($language, $allowedLanguages)) {
            self::$currentLanguage = $language;
            $_SESSION['user_language'] = $language;
            
            // Reset initialized flag to force reload of translations
            self::$initialized = false;
            
            // Reload translations
            self::loadTranslations();
            
            return true;
        }
        
        return false;
    }
    
    private static function saveLanguageToDatabase($userId, $language) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Update preferred_language in customers table
            $query = "UPDATE customers SET preferred_language = :language, updated_at = NOW() WHERE id = :user_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':language', $language);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Error saving language to database: " . $e->getMessage());
            return false;
        }
    }
    
    public static function translate($key, $default = '') {
        self::initialize();
        return self::$translations[$key] ?? $default ?? $key;
    }
    
    public static function t($key, $default = '') {
        return self::translate($key, $default);
    }
    
    public static function markLanguagePreferenceShown() {
        $_SESSION['language_preference_shown'] = true;
        $_SESSION['show_language_modal'] = false;
    }
    
    public static function skipLanguagePreference() {
        $_SESSION['language_preference_shown'] = true;
        $_SESSION['show_language_modal'] = false;
        $_SESSION['language_preference_set'] = true;
    }
    
    public static function getAllTranslations() {
        self::initialize();
        return self::$translations;
    }
    
    public static function reloadTranslations() {
        self::$initialized = false;
        self::initialize();
    }
    
    // Method to check if user has set language preference
    public static function hasLanguagePreference() {
        self::initialize();
        return isset($_SESSION['language_preference_set']) && $_SESSION['language_preference_set'] === true;
    }
    
    // NEW METHOD: Clear all language preferences (for testing/debugging)
    public static function clearPreferences() {
        self::$currentLanguage = 'en';
        self::$translations = [];
        self::$initialized = false;
        
        unset(
            $_SESSION['user_language'],
            $_SESSION['language_preference_shown'],
            $_SESSION['language_preference_set'],
            $_SESSION['show_language_modal'],
            $_SESSION['guest_language_preference']
        );
    }
    
    // NEW METHOD: Get localized text from database fields
    public static function getLocalizedText($data, $field) {
        $language = self::getCurrentLanguage();
        
        if ($language === 'ne') {
            return !empty($data[$field . '_ne']) ? $data[$field . '_ne'] : 
                   (!empty($data[$field . '_en']) ? $data[$field . '_en'] : 
                   (!empty($data[$field]) ? $data[$field] : ''));
        } else {
            return !empty($data[$field . '_en']) ? $data[$field . '_en'] : 
                   (!empty($data[$field . '_ne']) ? $data[$field . '_ne'] : 
                   (!empty($data[$field]) ? $data[$field] : ''));
        }
    }
    
    // NEW METHOD: Get localized slug
    public static function getLocalizedSlug($data) {
        $language = self::getCurrentLanguage();
        
        if ($language === 'ne') {
            return !empty($data['slug_ne']) ? $data['slug_ne'] : 
                   (!empty($data['slug']) ? $data['slug'] : '');
        } else {
            return !empty($data['slug_en']) ? $data['slug_en'] : 
                   (!empty($data['slug']) ? $data['slug'] : '');
        }
    }
}
?>