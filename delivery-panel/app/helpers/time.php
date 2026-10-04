<?php
/**
 * Time Helper Functions
 * Handle time calculations and conversions
 */

/**
 * Get current timestamp in Nepal timezone
 */
function nowInNepal() {
    $tz = new DateTimeZone('Asia/Kathmandu');
    $now = new DateTime('now', $tz);
    return $now->format('Y-m-d H:i:s');
}

/**
 * Convert to Nepal timezone
 */
function toNepaliTime($dateTime) {
    $tz = new DateTimeZone('Asia/Kathmandu');
    $date = new DateTime($dateTime, new DateTimeZone('UTC'));
    $date->setTimeZone($tz);
    return $date->format('Y-m-d H:i:s');
}

/**
 * Get human readable time difference
 */
function getTimeAgo($dateTime) {
    $time = strtotime($dateTime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($dateTime);
    }
}

/**
 * Get time until (future)
 */
function getTimeUntil($dateTime) {
    $time = strtotime($dateTime);
    $now = time();
    $diff = $time - $now;
    
    if ($diff < 0) {
        return 'Overdue';
    }
    
    if ($diff < 60) {
        return 'Less than a minute';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '');
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '');
    }
}

/**
 * Get business hours status
 */
function getBusinessHoursStatus($openTime = '09:00', $closeTime = '21:00') {
    $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
    $currentTime = $now->format('H:i');
    
    $open = strtotime($openTime);
    $close = strtotime($closeTime);
    $current = strtotime($currentTime);
    
    if ($current >= $open && $current < $close) {
        $minutesUntilClose = floor(($close - $current) / 60);
        return [
            'status' => 'open',
            'message' => 'Open - Closes in ' . $minutesUntilClose . ' minutes',
        ];
    } else {
        $nextOpen = $current >= $close ? $open + 86400 : $open;
        $minutesUntilOpen = floor(($nextOpen - $current) / 60);
        return [
            'status' => 'closed',
            'message' => 'Closed - Opens in ' . $minutesUntilOpen . ' minutes',
        ];
    }
}

/**
 * Is working hours
 */
function isWorkingHours($openTime = '09:00', $closeTime = '21:00') {
    $status = getBusinessHoursStatus($openTime, $closeTime);
    return $status['status'] === 'open';
}

/**
 * Get day of week
 */
function getDayOfWeek($dateTime = null) {
    if ($dateTime === null) {
        $dateTime = nowInNepal();
    }
    
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $timestamp = strtotime($dateTime);
    return $days[date('w', $timestamp)];
}

/**
 * Is weekend
 */
function isWeekend($dateTime = null) {
    if ($dateTime === null) {
        $dateTime = nowInNepal();
    }
    
    $timestamp = strtotime($dateTime);
    $dayOfWeek = date('w', $timestamp);
    return $dayOfWeek === 0 || $dayOfWeek === 6;
}

/**
 * Get week number
 */
function getWeekNumber($dateTime = null) {
    if ($dateTime === null) {
        $dateTime = nowInNepal();
    }
    
    return date('W', strtotime($dateTime));
}

/**
 * Get month name
 */
function getMonthName($month = null) {
    if ($month === null) {
        $month = date('m');
    }
    
    $months = ['January', 'February', 'March', 'April', 'May', 'June',
               'July', 'August', 'September', 'October', 'November', 'December'];
    
    return $months[$month - 1] ?? '';
}

/**
 * Check if date is today
 */
function isToday($dateTime) {
    $today = date('Y-m-d');
    $date = date('Y-m-d', strtotime($dateTime));
    return $today === $date;
}

/**
 * Check if date is tomorrow
 */
function isTomorrow($dateTime) {
    $tomorrow = date('Y-m-d', time() + 86400);
    $date = date('Y-m-d', strtotime($dateTime));
    return $tomorrow === $date;
}

/**
 * Check if date is yesterday
 */
function isYesterday($dateTime) {
    $yesterday = date('Y-m-d', time() - 86400);
    $date = date('Y-m-d', strtotime($dateTime));
    return $yesterday === $date;
}

/**
 * Get date range for current month
 */
function getCurrentMonthRange() {
    $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
    $first = $now->modify('first day of this month')->format('Y-m-d');
    $last = $now->modify('last day of this month')->format('Y-m-d');
    
    return ['from' => $first, 'to' => $last];
}

/**
 * Get date range for current week
 */
function getCurrentWeekRange() {
    $now = new DateTime('now', new DateTimeZone('Asia/Kathmandu'));
    $week = $now->format('W');
    $year = $now->format('Y');
    
    $first = new DateTime();
    $first->setISODate($year, $week, 1);
    $first = $first->format('Y-m-d');
    
    $last = new DateTime();
    $last->setISODate($year, $week, 7);
    $last = $last->format('Y-m-d');
    
    return ['from' => $first, 'to' => $last];
}

/**
 * Add days to date
 */
function addDaysToDate($date, $days) {
    return date('Y-m-d', strtotime($date . ' + ' . $days . ' days'));
}

/**
 * Subtract days from date
 */
function subtractDaysFromDate($date, $days) {
    return date('Y-m-d', strtotime($date . ' - ' . $days . ' days'));
}

/**
 * Check if date is past
 */
function isPastDate($date) {
    return strtotime($date) < time();
}

/**
 * Check if date is future
 */
function isFutureDate($date) {
    return strtotime($date) > time();
}

/**
 * Get delivery window status
 */
function getDeliveryWindowStatus($assignedTime, $estimatedTime) {
    $deadline = strtotime($assignedTime) + ($estimatedTime * 60);
    $now = time();
    
    $percentageComplete = min(100, max(0, ($now - strtotime($assignedTime)) / ($deadline - strtotime($assignedTime)) * 100));
    
    return [
        'percentage' => round($percentageComplete),
        'status' => $now >= $deadline ? 'late' : 'on_time',
        'remaining' => max(0, $deadline - $now),
    ];
}
