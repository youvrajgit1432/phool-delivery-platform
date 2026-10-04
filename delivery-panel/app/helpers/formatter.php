<?php
/**
 * Formatter Helper Functions
 * Format data for display
 */

/**
 * Format currency
 */
function formatCurrency($amount, $currency = '₹') {
    return $currency . number_format($amount, 2);
}

/**
 * Format distance
 */
function formatDistance($km) {
    if ($km < 1) {
        return round($km * 1000) . ' m';
    }
    return number_format($km, 2) . ' km';
}

/**
 * Format time duration
 */
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($mins === 0) {
        return $hours . ' hr' . ($hours > 1 ? 's' : '');
    }
    
    return $hours . ' hr ' . $mins . ' min';
}

/**
 * Format datetime
 */
function formatDateTime($dateTime, $format = 'Y-m-d H:i') {
    if (is_string($dateTime)) {
        $dateTime = strtotime($dateTime);
    }
    
    return date($format, $dateTime);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (is_string($date)) {
        $date = strtotime($date);
    }
    
    return date($format, $date);
}

/**
 * Format time
 */
function formatTime($time, $format = 'H:i') {
    if (is_string($time)) {
        $time = strtotime($time);
    }
    
    return date($format, $time);
}

/**
 * Format rating
 */
function formatRating($rating) {
    $rating = round($rating, 1);
    $stars = '';
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '★';
        } else {
            $stars .= '☆';
        }
    }
    
    return $stars . ' (' . $rating . ')';
}

/**
 * Format percentage
 */
function formatPercentage($value) {
    return number_format($value, 2) . '%';
}

/**
 * Format phone number
 */
function formatPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    if (strlen($phone) === 10) {
        return '+977 ' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 4) . ' ' . substr($phone, 6);
    }
    
    return $phone;
}

/**
 * Format address
 */
function formatAddress($address) {
    // Limit address length
    if (strlen($address) > 100) {
        return substr($address, 0, 100) . '...';
    }
    
    return $address;
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'assigned' => 'badge-warning',
        'accepted' => 'badge-info',
        'picked_up' => 'badge-primary',
        'on_the_way' => 'badge-blue',
        'arrived' => 'badge-cyan',
        'delivered' => 'badge-success',
        'failed' => 'badge-danger',
        'cancelled' => 'badge-dark',
        'returned' => 'badge-secondary',
    ];
    
    return $classes[$status] ?? 'badge-light';
}

/**
 * Get status label
 */
function getStatusLabel($status) {
    $labels = [
        'assigned' => 'Assigned',
        'accepted' => 'Accepted',
        'picked_up' => 'Picked Up',
        'on_the_way' => 'On The Way',
        'arrived' => 'Arrived',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];
    
    return $labels[$status] ?? $status;
}

/**
 * Get status icon
 */
function getStatusIcon($status) {
    $icons = [
        'assigned' => '📋',
        'accepted' => '✓',
        'picked_up' => '📦',
        'on_the_way' => '🚗',
        'arrived' => '📍',
        'delivered' => '✓✓',
        'failed' => '✗',
        'cancelled' => '⊘',
        'returned' => '↩',
    ];
    
    return $icons[$status] ?? '?';
}
