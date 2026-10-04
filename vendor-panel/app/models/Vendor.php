<?php

namespace App\Models;

class Vendor
{
    protected $table = 'vendors';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'email', 'phone', 'store_name', 'description', 'logo_url', 'banner_url'];

    /**
     * Get vendor's products
     */
    public function products()
    {
        // Relationship with products
    }

    /**
     * Get vendor's orders
     */
    public function orders()
    {
        // Relationship with orders
    }

    /**
     * Get vendor's payouts
     */
    public function payouts()
    {
        // Relationship with payouts
    }
}
?>
