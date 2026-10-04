<?php
namespace Phool\DeliveryPanel\Middleware;

class RiderAuthMiddleware {
    /**
     * Handle incoming request - verify rider is authenticated
     * 
     * @param array $request
     * @return bool
     */
    public function handle($request) {
        if (!isset($_SESSION['rider_id']) || empty($_SESSION['rider_id'])) {
            header('Location: ' . app_url('/login'));
            exit;
        }
        return true;
    }

    /**
     * Verify session validity and rider status
     * 
     * @return bool
     */
    public function verify() {
        if (!isset($_SESSION['rider_id'])) {
            return false;
        }
        
        // Optional: Check if rider account is still active
        // $rider = Rider::find($_SESSION['rider_id']);
        // return $rider && $rider->status === 'active';
        
        return true;
    }

    /**
     * Refresh session timeout
     */
    public function refreshSession() {
        $_SESSION['last_activity'] = time();
    }
}
