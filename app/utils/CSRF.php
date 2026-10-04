<?php
// app/utils/CSRF.php

class CSRF {
    /**
     * Generate a CSRF token
     */
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a CSRF token
     */
    public static function validateToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get the CSRF token field for forms
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validate POST request with CSRF token
     */
    public static function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!self::validateToken($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
}
?>