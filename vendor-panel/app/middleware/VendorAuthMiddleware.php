<?php

namespace App\Middleware;

class VendorAuthMiddleware
{
    /**
     * Check if vendor is authenticated
     */
    public function handle()
    {
        if (!isset($_SESSION['vendor_id'])) {
            header('Location: /phool-delivery-platform/vendor-panel/login');
            exit;
        }
    }
}
?>
