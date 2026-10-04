<?php
namespace Phool\DeliveryPanel\Middleware;

class CSRFProtection {
    /**
     * Generate CSRF token for forms
     * 
     * @return string
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token from POST request
     * 
     * @param string|null $token
     * @return bool
     */
    public static function verify($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get token for inclusion in forms
     * 
     * @return string
     */
    public static function token() {
        return htmlspecialchars(self::generateToken());
    }

    /**
     * Output hidden CSRF field
     */
    public static function field() {
        echo '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    /**
     * Middleware to validate POST requests
     * 
     * @return bool
     */
    public function handle() {
        // Only check POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }

        // Skip CSRF for specific endpoints if needed
        $skipEndpoints = [
            '/api/webhook',
            '/api/payment-callback'
        ];

        foreach ($skipEndpoints as $endpoint) {
            if (strpos($_SERVER['REQUEST_URI'], $endpoint) !== false) {
                return true;
            }
        }

        if (!$this->verify()) {
            http_response_code(403);
            die('CSRF token validation failed');
        }

        return true;
    }
}
