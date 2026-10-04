<?php
namespace Phool\DeliveryPanel\Models;

class RiderEarning {
    protected $table = 'rider_earnings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rider_id', 'rider_order_id', 'order_id', 'base_delivery_fee',
        'distance_surge', 'on_time_bonus', 'total_earnings', 'net_earnings',
        'payment_status', 'payout_date'
    ];

    public static function getTodayEarnings($riderId) {
        // Sum earnings for today
    }

    public static function getMonthEarnings($riderId, $month = null, $year = null) {
        // Sum earnings for specific month
    }

    public static function getPayoutStatus($riderId) {
        // Get pending and paid earnings
    }
}
