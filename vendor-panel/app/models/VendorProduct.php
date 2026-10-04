<?php

namespace App\Models;

class VendorProduct
{
    protected $table = 'vendor_products';
    protected $primaryKey = 'id';
    protected $fillable = ['vendor_id', 'name', 'description', 'price', 'category', 'image_url', 'is_available'];

    /**
     * Get product vendor
     */
    public function vendor()
    {
        // Relationship with vendor
    }

    /**
     * Get product availability
     */
    public function availability()
    {
        // Relationship with availability
    }
}
?>
