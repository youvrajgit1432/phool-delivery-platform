<?php
namespace Phool\DeliveryPanel\Services;

class EarningsCalculationService {
    /**
     * Base delivery fee: NPR 100 per order
     */
    const BASE_DELIVERY_FEE = 100;

    /**
     * Distance rate: NPR 40 per km
     */
    const DISTANCE_RATE = 40;

    /**
     * On-time delivery bonus: NPR 50
     */
    const ON_TIME_BONUS = 50;

    /**
     * Calculate earnings for single order
     * 
     * @param array $orderData
     * @return array Earnings breakdown
     */
    public function calculateOrderEarnings($orderData) {
        $earnings = [
            'base_fee' => self::BASE_DELIVERY_FEE,
            'distance_charge' => 0,
            'on_time_bonus' => 0,
            'performance_bonus' => 0,
            'deductions' => 0,
            'net_earnings' => self::BASE_DELIVERY_FEE
        ];

        // Calculate distance charge if distance provided
        if (isset($orderData['distance_km'])) {
            $earnings['distance_charge'] = $orderData['distance_km'] * self::DISTANCE_RATE;
        }

        // Add on-time bonus if delivery was on time
        if (isset($orderData['on_time']) && $orderData['on_time'] === true) {
            $earnings['on_time_bonus'] = self::ON_TIME_BONUS;
        }

        // Calculate performance bonus based on rating
        if (isset($orderData['rating'])) {
            $earnings['performance_bonus'] = $this->calculatePerformanceBonus($orderData['rating']);
        }

        // Apply deductions for failed deliveries, cancellations, etc
        if (isset($orderData['deductions'])) {
            $earnings['deductions'] = $orderData['deductions'];
        }

        // Net earnings
        $earnings['net_earnings'] = 
            $earnings['base_fee'] + 
            $earnings['distance_charge'] + 
            $earnings['on_time_bonus'] + 
            $earnings['performance_bonus'] - 
            $earnings['deductions'];

        return $earnings;
    }

    /**
     * Calculate performance bonus based on rating
     * 
     * @param float $rating Average rating (1-5)
     * @return float Bonus amount in NPR
     */
    public function calculatePerformanceBonus($rating) {
        if ($rating >= 4.8) return 100;
        if ($rating >= 4.5) return 75;
        if ($rating >= 4.0) return 50;
        if ($rating >= 3.5) return 25;
        return 0;
    }

    /**
     * Calculate daily earnings
     * 
     * @param int $riderId
     * @param string $date (Y-m-d format)
     * @return array
     */
    public function getDailyEarnings($riderId, $date) {
        // Query rider_earnings for the date
        // Aggregate base_fee, distance_surge, on_time_bonus, net_earnings
        return [];
    }

    /**
     * Calculate monthly earnings with payout date
     * 
     * @param int $riderId
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getMonthlyEarnings($riderId, $month, $year) {
        // Query rider_earnings for month
        // Calculate total, average, pending payout
        return [];
    }

    /**
     * Get payout details
     * 
     * @param int $riderId
     * @param string|null $status pending/completed/failed
     * @return array
     */
    public function getPayoutDetails($riderId, $status = null) {
        // Query payouts table for rider
        // Include bank details, payout amount, date
        return [];
    }

    /**
     * Calculate incentive for hitting targets
     * 
     * @param int $riderId
     * @param string $period week/month
     * @return float
     */
    public function calculateTargetIncentive($riderId, $period = 'month') {
        // Check if rider hit delivery target
        // Return bonus amount
        return 0;
    }

    /**
     * Apply surge pricing
     * 
     * @param float $baseEarnings
     * @param float $surgeFactor Peak hours factor (1.0-1.5)
     * @return float
     */
    public function applySurgeMultiplier($baseEarnings, $surgeFactor = 1.0) {
        return $baseEarnings * $surgeFactor;
    }
}
