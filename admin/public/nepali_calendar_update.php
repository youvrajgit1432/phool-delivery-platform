<?php
require_once '../bootstrap/app.php';
$pdo = getDBConnection();

// This script should be called by a cron job every 24 hours
function updateNepaliDate() {
    global $pdo;
    
    // Get current Nepali date
    $stmt = $pdo->prepare("SELECT * FROM current_nepali_date LIMIT 1");
    $stmt->execute();
    $current_date = $stmt->fetch();
    
    if (!$current_date) {
        return false; // No current date set
    }
    
    $year = $current_date['year'];
    $month = $current_date['month'];
    $day = $current_date['day'];
    $weekday = $current_date['weekday'];
    
    // Get month data
    $stmt = $pdo->prepare("SELECT * FROM nepali_calendar_months WHERE year = ? AND month_number = ?");
    $stmt->execute([$year, $month]);
    $month_data = $stmt->fetch();
    
    if (!$month_data) {
        return false; // Month data not found
    }
    
    // Increment day
    $day++;
    $weekday = ($weekday + 1) % 7;
    
    // Check if month ended
    if ($day > $month_data['total_days']) {
        $day = 1;
        $month++;
        
        // Check if year ended
        if ($month > 12) {
            $year++;
            $month = 1;
            
            // Get next year's Baishakh first day
            $stmt = $pdo->prepare("SELECT baishakh_first_day FROM nepali_calendar_years WHERE year = ?");
            $stmt->execute([$year]);
            $next_year = $stmt->fetch();
            
            if (!$next_year) {
                return false; // Next year not configured
            }
            
            $weekday = $next_year['baishakh_first_day'];
        } else {
            // Get next month's first weekday
            $stmt = $pdo->prepare("SELECT first_weekday FROM nepali_calendar_months WHERE year = ? AND month_number = ?");
            $stmt->execute([$year, $month]);
            $next_month = $stmt->fetch();
            
            if (!$next_month) {
                return false; // Next month not configured
            }
            
            $weekday = $next_month['first_weekday'];
        }
    }
    
    // Update current date
    $stmt = $pdo->prepare("UPDATE current_nepali_date SET year = ?, month = ?, day = ?, weekday = ?, last_updated = NOW()");
    return $stmt->execute([$year, $month, $day, $weekday]);
}

// Run update
if (updateNepaliDate()) {
    echo "Nepali date updated successfully.";
} else {
    echo "Failed to update Nepali date.";
}
?>