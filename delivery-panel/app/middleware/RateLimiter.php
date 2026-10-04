<?php
namespace Phool\DeliveryPanel\Middleware;

class RateLimiter {
    protected $limits = [
        'default' => 60,      // 60 requests per minute
        'auth' => 5,          // 5 login attempts per minute
        'api' => 100,         // 100 API calls per minute
        'location' => 30      // 30 location updates per minute
    ];

    /**
     * Check request rate limit
     * 
     * @param string $type
     * @param string|null $identifier
     * @return bool
     */
    public function handle($type = 'default', $identifier = null) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $key = "rate_limit_{$type}_{$identifier}";
        $limit = $this->limits[$type] ?? $this->limits['default'];

        // Check from session or cache
        if (isset($_SESSION[$key])) {
            $count = $_SESSION[$key]['count'];
            $timestamp = $_SESSION[$key]['timestamp'];

            // Reset if past minute
            if (time() - $timestamp >= 60) {
                $_SESSION[$key] = ['count' => 1, 'timestamp' => time()];
                return true;
            }

            // Check limit
            if ($count >= $limit) {
                http_response_code(429); // Too Many Requests
                return false;
            }

            $_SESSION[$key]['count']++;
        } else {
            $_SESSION[$key] = ['count' => 1, 'timestamp' => time()];
        }

        return true;
    }

    /**
     * Throttle authentication attempts
     */
    public function throttleAuth($email) {
        return $this->handle('auth', $email);
    }

    /**
     * Throttle location updates
     */
    public function throttleLocation($riderId) {
        return $this->handle('location', "rider_{$riderId}");
    }

    /**
     * Get remaining requests
     */
    public function getRemaining($type = 'default', $identifier = null) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        $key = "rate_limit_{$type}_{$identifier}";
        $limit = $this->limits[$type] ?? $this->limits['default'];

        if (!isset($_SESSION[$key])) {
            return $limit;
        }

        return max(0, $limit - $_SESSION[$key]['count']);
    }
}
