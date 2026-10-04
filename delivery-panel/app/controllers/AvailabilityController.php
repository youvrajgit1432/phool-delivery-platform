<?php
namespace Phool\DeliveryPanel\Controllers;

class AvailabilityController {
    public function toggleOnline() {
        // Toggle rider availability (online/offline)
    }

    public function updateLocation($latitude, $longitude) {
        // Update current location coordinates
    }

    public function getAvailabilityStatus() {
        // Get current availability status and location
    }

    public function setBreak($reason = null) {
        // Set rider on break
    }

    public function endBreak() {
        // End break and return online
    }
}
