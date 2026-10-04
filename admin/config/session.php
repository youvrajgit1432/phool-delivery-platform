<?php
// config/session.php

class SessionManager {
    private $sessionTimeout = 3600; // 1 hour in seconds
    private $regenerateInterval = 1800; // 30 minutes in seconds
    
    public function __construct() {
        $this->initializeSession();
    }
    
    private function initializeSession() {
        // Set secure session parameters
        session_set_cookie_params([
            'lifetime' => $this->sessionTimeout,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize session tracking
        $this->initializeSessionTracking();
        
        // Check for session expiration
        $this->checkSessionExpiry();
        
        // Regenerate session ID periodically
        $this->regenerateSessionId();
        
        // Set security headers
        $this->setSecurityHeaders();
    }
    
    private function initializeSessionTracking() {
        if (!isset($_SESSION['session_data'])) {
            $_SESSION['session_data'] = [
                'created' => time(),
                'last_activity' => time(),
                'requests' => 0
            ];
        } else {
            $_SESSION['session_data']['last_activity'] = time();
            $_SESSION['session_data']['requests']++;
        }
    }
    
    private function checkSessionExpiry() {
        if (isset($_SESSION['session_data']['last_activity'])) {
            $inactiveTime = time() - $_SESSION['session_data']['last_activity'];
            
            if ($inactiveTime > $this->sessionTimeout) {
                $this->breakSession();
                // Start new session automatically
                session_regenerate_id(true);
                $this->initializeSessionTracking();
            }
        }
    }
    
    private function regenerateSessionId() {
        if (!isset($_SESSION['session_data']['created'])) {
            session_regenerate_id(true);
            $_SESSION['session_data']['created'] = time();
        } elseif (time() - $_SESSION['session_data']['created'] > $this->regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['session_data']['created'] = time();
        }
    }
    
    private function setSecurityHeaders() {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    public function breakSession() {
        // Clear all session data
        $_SESSION = array();
        
        // Destroy the session
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    public function getSessionStatus() {
        if (!isset($_SESSION['session_data'])) {
            return [
                'status' => 'NO_SESSION',
                'message' => 'No active session'
            ];
        }
        
        $currentTime = time();
        $lastActivity = $_SESSION['session_data']['last_activity'];
        $inactiveTime = $currentTime - $lastActivity;
        $timeRemaining = $this->sessionTimeout - $inactiveTime;
        
        $status = 'ACTIVE';
        if ($timeRemaining <= 0) {
            $status = 'EXPIRED';
        } elseif ($timeRemaining < 300) { // 5 minutes warning
            $status = 'WARNING';
        }
        
        return [
            'status' => $status,
            'created' => date('Y-m-d H:i:s', $_SESSION['session_data']['created']),
            'last_activity' => date('Y-m-d H:i:s', $lastActivity),
            'inactive_time' => $this->formatTime($inactiveTime),
            'time_remaining' => $this->formatTime(max(0, $timeRemaining)),
            'request_count' => $_SESSION['session_data']['requests'],
            'will_break_in' => $this->formatTime(max(0, $timeRemaining)) . ' of inactivity'
        ];
    }
    
    public function forceSessionBreak() {
        $this->breakSession();
        // Start new session
        session_start();
        session_regenerate_id(true);
        $this->initializeSessionTracking();
        
        return "Session manually broken and new session started";
    }
    
    private function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    }
    
    public function setSessionData($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public function getSessionData($key) {
        return $_SESSION[$key] ?? null;
    }
    
    public function removeSessionData($key) {
        unset($_SESSION[$key]);
    }
}

// Initialize session manager
$sessionManager = new SessionManager();

// Utility function to check session status (optional)
function checkSession() {
    global $sessionManager;
    return $sessionManager->getSessionStatus();
}

// Example usage in other files:
// require_once 'config/session.php';
// 
// // Set session data
// $sessionManager->setSessionData('user_id', 123);
// 
// // Get session status
// $status = $sessionManager->getSessionStatus();
// echo "Session will break after: " . $status['time_remaining'] . " of inactivity";
// 
// // Force break session
// if ($someCondition) {
//     $sessionManager->forceSessionBreak();
// }
?>