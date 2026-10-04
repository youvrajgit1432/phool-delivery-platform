<?php

namespace App\Middleware;

class CSRFProtection
{
    /**
     * Validate CSRF token
     */
    public static function generateToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyToken($token)
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
?>
