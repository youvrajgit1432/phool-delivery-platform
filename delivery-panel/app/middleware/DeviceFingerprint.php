<?php
namespace Phool\DeliveryPanel\Middleware;

class DeviceFingerprint {
    /**
     * Generate device fingerprint from user agent and IP
     * 
     * @return string
     */
    public static function generate() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        $fingerprint = hash('sha256', $userAgent . $ipAddress . $acceptLanguage);
        return $fingerprint;
    }

    /**
     * Store device fingerprint in session
     * 
     * @return string
     */
    public static function store() {
        if (!isset($_SESSION['device_fingerprint'])) {
            $_SESSION['device_fingerprint'] = self::generate();
        }
        return $_SESSION['device_fingerprint'];
    }

    /**
     * Verify current device matches stored fingerprint
     * 
     * @return bool
     */
    public static function verify() {
        if (!isset($_SESSION['device_fingerprint'])) {
            return false;
        }

        $currentFingerprint = self::generate();
        $storedFingerprint = $_SESSION['device_fingerprint'];

        return hash_equals($currentFingerprint, $storedFingerprint);
    }

    /**
     * Middleware to handle device changes
     * 
     * @return bool
     */
    public function handle() {
        // Store fingerprint on first visit
        if (!isset($_SESSION['device_fingerprint'])) {
            self::store();
            return true;
        }

        // Verify fingerprint matches
        if (!self::verify()) {
            // Device fingerprint mismatch - potential security concern
            // Log the event and optionally require re-authentication
            error_log('Device fingerprint mismatch for rider: ' . ($_SESSION['rider_id'] ?? 'unknown'));
            
            // Optional: Force re-login on device change
            // unset($_SESSION['rider_id']);
            // header('Location: ' . app_url('/login?reason=device_change'));
            // exit;
        }

        return true;
    }

    /**
     * Get current device info
     * 
     * @return array
     */
    public static function getDeviceInfo() {
        return [
            'fingerprint' => self::generate(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'platform' => self::detectPlatform(),
            'browser' => self::detectBrowser()
        ];
    }

    /**
     * Detect device platform
     */
    private static function detectPlatform() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (stripos($ua, 'windows') !== false) return 'Windows';
        if (stripos($ua, 'mac') !== false) return 'MacOS';
        if (stripos($ua, 'linux') !== false) return 'Linux';
        if (stripos($ua, 'android') !== false) return 'Android';
        if (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) return 'iOS';
        
        return 'Unknown';
    }

    /**
     * Detect browser type
     */
    private static function detectBrowser() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (stripos($ua, 'firefox') !== false) return 'Firefox';
        if (stripos($ua, 'chrome') !== false) return 'Chrome';
        if (stripos($ua, 'safari') !== false) return 'Safari';
        if (stripos($ua, 'edge') !== false) return 'Edge';
        
        return 'Unknown';
    }
}
