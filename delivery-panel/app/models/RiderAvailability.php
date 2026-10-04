<?php
namespace Phool\DeliveryPanel\Models;

class RiderAvailability {
    protected $table = 'rider_availability';
    protected $primaryKey = 'id';

    protected $fillable = [
        'rider_id', 'is_available', 'current_lat', 'current_lng',
        'current_city', 'availability_status', 'went_online_at'
    ];

    public static function findByRiderId($riderId) {
        // Get availability record for rider
    }

    public static function updateLocation($riderId, $lat, $lng, $city = null) {
        // Update rider's current location
    }

    public static function setAvailable($riderId, $status = true) {
        // Toggle online/offline status
    }

    public function getLastLocationUpdate() {
        // Get how old location data is
    }
}
