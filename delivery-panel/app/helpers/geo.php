<?php
/**
 * Geolocation Helper Functions
 * Calculate distances and locations
 */

/**
 * Calculate distance between two points using Haversine formula
 * Returns distance in kilometers
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadiusKm = 6371;
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadiusKm * $c;
    
    return $distance;
}

/**
 * Calculate distance in meters
 */
function calculateDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    return calculateDistance($lat1, $lon1, $lat2, $lon2) * 1000;
}

/**
 * Check if point is within geofence
 */
function isWithinGeofence($lat, $lon, $centerLat, $centerLon, $radiusMeters = 100) {
    $distance = calculateDistanceMeters($lat, $lon, $centerLat, $centerLon);
    return $distance <= $radiusMeters;
}

/**
 * Get bearing between two points (degrees)
 */
function getBearing($lat1, $lon1, $lat2, $lon2) {
    $dLon = deg2rad($lon2 - $lon1);
    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    
    $y = sin($dLon) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
    
    $bearing = atan2($y, $x);
    $bearing = rad2deg($bearing);
    $bearing = fmod($bearing + 360, 360);
    
    return $bearing;
}

/**
 * Get compass direction from bearing
 */
function getCompassDirection($bearing) {
    $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
                   'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    
    $index = round($bearing / 22.5) % 16;
    return $directions[$index];
}

/**
 * Calculate estimated delivery time based on distance
 * Returns estimated time in minutes
 */
function estimateDeliveryTime($distanceKm, $avgSpeedKmh = 30) {
    // Add extra time for traffic, pickup, etc.
    $travelTime = ($distanceKm / $avgSpeedKmh) * 60;
    $bufferTime = 10; // 10 minutes buffer
    
    return ceil($travelTime + $bufferTime);
}

/**
 * Calculate delivery fee based on distance and zone
 */
function calculateDeliveryFee($distanceKm, $baseFee, $perKmCharge) {
    if ($distanceKm <= 1) {
        return $baseFee;
    }
    
    $additionalDistance = $distanceKm - 1;
    return $baseFee + ($additionalDistance * $perKmCharge);
}

/**
 * Get nearby delivery zones
 * Checks distance to zone centers
 */
function getNearbyZones($lat, $lon, $zoneLatitudes = [], $zoneLongitudes = [], $radiusKm = 5) {
    $nearbyZones = [];
    
    foreach ($zoneLatitudes as $index => $zoneLat) {
        if (!isset($zoneLongitudes[$index])) {
            continue;
        }
        
        $distance = calculateDistance($lat, $lon, $zoneLat, $zoneLongitudes[$index]);
        
        if ($distance <= $radiusKm) {
            $nearbyZones[] = [
                'index' => $index,
                'distance' => $distance,
            ];
        }
    }
    
    // Sort by distance
    usort($nearbyZones, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    return $nearbyZones;
}

/**
 * Validate coordinates format
 */
function validateCoordinates($latitude, $longitude) {
    return is_numeric($latitude) && is_numeric($longitude) &&
           $latitude >= -90 && $latitude <= 90 &&
           $longitude >= -180 && $longitude <= 180;
}

/**
 * Get address from coordinates (reverse geocoding)
 * NOTE: Requires Google Maps API
 */
function getAddressFromCoordinates($latitude, $longitude) {
    $apiKey = getenv('GOOGLE_MAPS_API_KEY');
    
    if (!$apiKey) {
        return null;
    }
    
    $url = "https://maps.googleapis.com/maps/api/geocode/json";
    $params = [
        'latlng' => "$latitude,$longitude",
        'key' => $apiKey,
    ];
    
    // TODO: Make API call using cURL
    return null;
}

/**
 * Get coordinates from address (geocoding)
 * NOTE: Requires Google Maps API
 */
function getCoordinatesFromAddress($address) {
    $apiKey = getenv('GOOGLE_MAPS_API_KEY');
    
    if (!$apiKey) {
        return null;
    }
    
    $url = "https://maps.googleapis.com/maps/api/geocode/json";
    $params = [
        'address' => $address,
        'key' => $apiKey,
    ];
    
    // TODO: Make API call using cURL
    return null;
}

/**
 * Create Google Maps link
 */
function createGoogleMapsLink($latitude, $longitude, $label = null) {
    $url = "https://maps.google.com/?q=$latitude,$longitude";
    
    if ($label) {
        $url .= "&q=" . urlencode($label);
    }
    
    return $url;
}

/**
 * Create navigation link (for mobile)
 */
function createNavigationLink($lat, $lon) {
    // Works for most mobile devices
    return "geo:$lat,$lon?q=$lat,$lon";
}

/**
 * Check if location is in city
 * Simple check based on coordinates
 */
function isLocationInCity($lat, $lon, $cityLat, $cityLon, $cityRadiusKm = 50) {
    $distance = calculateDistance($lat, $lon, $cityLat, $cityLon);
    return $distance <= $cityRadiusKm;
}
