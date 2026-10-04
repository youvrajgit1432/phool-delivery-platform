<?php
namespace Phool\DeliveryPanel\Models;

class RiderReview {
    protected $table = 'reviews';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rider_id', 'order_id', 'user_id', 'rating', 'comment',
        'status', 'is_anonymous', 'created_at'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_anonymous' => 'boolean',
        'created_at' => 'datetime'
    ];

    public static function getByRider($riderId, $limit = 20) {
        // Get all reviews for a rider
    }

    public static function getAverageRating($riderId) {
        // Calculate average rating for rider
    }

    public static function getRecentReviews($riderId, $days = 30) {
        // Get reviews from last N days
    }

    public static function getPendingApproval() {
        // Get reviews awaiting admin approval
    }
}
