<?php

namespace App\Middleware;

class DeviceFingerprint
{
    /**
     * Generate device fingerprint
     */
    public static function generate()
    {
        $fingerprint = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        return hash('sha256', json_encode($fingerprint));
    }

    /**
     * Verify device fingerprint
     */
    public static function verify($stored_fingerprint)
    {
        return hash_equals($stored_fingerprint, self::generate());
    }
}
?>
