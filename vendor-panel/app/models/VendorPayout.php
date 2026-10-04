<?php

namespace App\Models;

class VendorPayout
{
    protected $table = 'vendor_payouts';
    protected $primaryKey = 'id';
    protected $fillable = ['vendor_id', 'amount', 'status', 'payout_date', 'transaction_id'];

    /**
     * Get vendor
     */
    public function vendor()
    {
        // Relationship with vendor
    }
}
?>
