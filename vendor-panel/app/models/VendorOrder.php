<?php

namespace App\Models;

class VendorOrder
{
    protected $table = 'vendor_orders';
    protected $primaryKey = 'id';
    protected $fillable = ['vendor_id', 'order_id', 'status', 'notes'];

    /**
     * Get order details
     */
    public function order()
    {
        // Relationship with main order
    }

    /**
     * Get vendor
     */
    public function vendor()
    {
        // Relationship with vendor
    }
}
?>
