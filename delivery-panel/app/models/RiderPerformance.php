<?php
namespace Phool\DeliveryPanel\Models;

class RiderPerformance {
    protected $table = 'rider_performance';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rider_id', 'date', 'deliveries_completed', 'deliveries_failed',
        'on_time_rate', 'success_rate', 'average_rating', 'total_distance'
    ];

    public static function getTodayPerformance($riderId) {
        // Get today's performance metrics
    }

    public static function getMonthlyPerformance($riderId, $month = null, $year = null) {
        // Get monthly performance summary
    }

    public static function updateDailyPerformance($riderId, $date = null) {
        // Recalculate daily performance metrics
    }

    public function getPerformanceTrend($riderId, $days = 30) {
        // Get trend line for last N days
    }
}
