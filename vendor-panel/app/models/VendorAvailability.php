<?php

namespace App\Models;

class VendorAvailability
{
    protected $table = 'vendor_availability';
    protected $primaryKey = 'id';
    protected $fillable = ['vendor_id', 'product_id', 'available_from', 'available_to', 'is_available'];

    /**
     * Get related product
     */
    public function product()
    {
        // Relationship with product
    }
}
?>
