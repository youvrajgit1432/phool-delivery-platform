<?php

namespace App\Middleware;

class VendorOwnershipMiddleware
{
    /**
     * Verify vendor owns the resource
     */
    public function handle($vendorId)
    {
        if ($_SESSION['vendor_id'] != $vendorId) {
            http_response_code(403);
            die('Unauthorized access');
        }
    }
}
?>
