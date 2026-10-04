<?php
// app/config/session.php - FIXED Session Configuration

class SessionConfig {
    private static $initialized = false;
    
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        // Only configure session if it's not already active
        if (session_status() === PHP_SESSION_NONE) {
            // 1 year in seconds
            $one_year = 365 * 24 * 60 * 60; // 31536000

            // Server-side session lifetime
            ini_set('session.gc_maxlifetime', $one_year);

            // Browser cookie lifetime
            ini_set('session.cookie_lifetime', $one_year);

            // Security settings
            ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');

            // Enhanced session cookie parameters
            session_set_cookie_params([
                'lifetime' => $one_year,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        self::$initialized = true;
    }

    /**
     * Initialize all preference flags to prevent modal display issues
     */
    public static function initializePreferenceFlags() {
        // Initialize all preference flags with proper defaults
        $_SESSION['language_preference_set'] = $_SESSION['language_preference_set'] ?? false;
        $_SESSION['city_preference_set'] = $_SESSION['city_preference_set'] ?? false;
        $_SESSION['notification_preference_set'] = $_SESSION['notification_preference_set'] ?? false;
        
        // Initialize user preferences with defaults
        $_SESSION['user_city_id'] = $_SESSION['user_city_id'] ?? 2; // Default to Kathmandu
        $_SESSION['user_city_name'] = $_SESSION['user_city_name'] ?? 'Kathmandu';
        $_SESSION['user_language'] = $_SESSION['user_language'] ?? 'en';
        
        // Initialize other session arrays
        $_SESSION['cart'] = $_SESSION['cart'] ?? [];
        $_SESSION['message_count'] = $_SESSION['message_count'] ?? 0;
        $_SESSION['pwa_install_shown'] = $_SESSION['pwa_install_shown'] ?? false;
        $_SESSION['pwa_last_shown_date'] = $_SESSION['pwa_last_shown_date'] ?? '';
    }
}

// Enhanced session handler for persistent sessions with preference tracking
class PersistentSessionHandler {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        try {
            $query = "SELECT session_data, preference_flags FROM persistent_sessions 
                     WHERE session_id = :session_id AND expires_at > NOW()";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Restore preference flags if they exist
                if (!empty($row['preference_flags'])) {
                    $flags = json_decode($row['preference_flags'], true);
                    if (is_array($flags)) {
                        foreach ($flags as $key => $value) {
                            $_SESSION[$key] = $value;
                        }
                    }
                }
                
                return $row['session_data'];
            }
        } catch (Exception $e) {
            error_log("Session read error: " . $e->getMessage());
        }
        return '';
    }
    
    public function write($sessionId, $sessionData) {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + (365 * 24 * 60 * 60));
            
            // Extract customer_id and preference flags from session data
            $customer_id = $this->extractCustomerId($sessionData);
            $preference_flags = $this->extractPreferenceFlags();
            
            $query = "INSERT INTO persistent_sessions 
                     (session_id, customer_id, session_data, expires_at, last_accessed, preference_flags) 
                     VALUES (:session_id, :customer_id, :session_data, :expires_at, NOW(), :preference_flags)
                     ON DUPLICATE KEY UPDATE 
                     session_data = :session_data, 
                     expires_at = :expires_at,
                     preference_flags = :preference_flags,
                     last_accessed = NOW()";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':session_data', $sessionData);
            $stmt->bindParam(':expires_at', $expiresAt);
            $stmt->bindParam(':preference_flags', $preference_flags);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }
    
    private function extractCustomerId($sessionData) {
        // Try to extract customer_id from session data
        $sessionArray = [];
        @parse_str($sessionData, $sessionArray);
        
        if (isset($sessionArray['customer_id'])) {
            return $sessionArray['customer_id'];
        }
        
        // Fallback: try to unserialize (for different session serialization formats)
        $data = @unserialize($sessionData);
        if ($data && isset($data['customer_id'])) {
            return $data['customer_id'];
        }
        
        return 0; // Default if no customer_id found
    }
    
    private function extractPreferenceFlags() {
        $flags = [
            'language_preference_set' => $_SESSION['language_preference_set'] ?? false,
            'city_preference_set' => $_SESSION['city_preference_set'] ?? false,
            'notification_preference_set' => $_SESSION['notification_preference_set'] ?? false,
            'user_city_id' => $_SESSION['user_city_id'] ?? 2,
            'user_city_name' => $_SESSION['user_city_name'] ?? 'Kathmandu',
            'user_language' => $_SESSION['user_language'] ?? 'en'
        ];
        
        return json_encode($flags);
    }
    
    public function destroy($sessionId) {
        try {
            $query = "DELETE FROM persistent_sessions WHERE session_id = :session_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }
    
    public function gc($maxlifetime) {
        try {
            $query = "DELETE FROM persistent_sessions WHERE expires_at <= NOW()";
            $stmt = $this->db->prepare($query);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize session configuration
SessionConfig::initialize();

// Start session with enhanced handling
if (session_status() === PHP_SESSION_NONE) {
    try {
        // Check if we can initialize database (might not be available in all contexts)
        if (class_exists('Database')) {
            $database = new Database();
            $db = $database->getConnection();
            
            // Set custom session handler
            $sessionHandler = new PersistentSessionHandler($db);
            session_set_save_handler(
                [$sessionHandler, 'open'],
                [$sessionHandler, 'close'],
                [$sessionHandler, 'read'],
                [$sessionHandler, 'write'],
                [$sessionHandler, 'destroy'],
                [$sessionHandler, 'gc']
            );
        }
        
        session_start();
        
        // Initialize preference flags to prevent showing modals repeatedly
        SessionConfig::initializePreferenceFlags();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            // Regenerate every 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
    } catch (Exception $e) {
        error_log("Session start error: " . $e->getMessage());
        // Fallback to normal session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            SessionConfig::initializePreferenceFlags();
        }
    }
}
?>