<?php
/**
 * Nepali Date Converter
 * Converts between English (Gregorian) and Nepali (Bikram Sambat) calendar systems
 */

class NepaliDateConverter {
    
    /**
     * Nepali month information for year 2082
     * Month names and their days
     */
    private static $nepali_months = [
        1 => ['name' => 'Baishakh', 'days' => 31],
        2 => ['name' => 'Jestha', 'days' => 31],
        3 => ['name' => 'Ashadh', 'days' => 31],
        4 => ['name' => 'Shrawan', 'days' => 32],
        5 => ['name' => 'Bhadra', 'days' => 31],
        6 => ['name' => 'Ashwin', 'days' => 30],
        7 => ['name' => 'Kartik', 'days' => 29],
        8 => ['name' => 'Mangsir', 'days' => 30],
        9 => ['name' => 'Poush', 'days' => 29],
        10 => ['name' => 'Magh', 'days' => 29],
        11 => ['name' => 'Phalgun', 'days' => 30],
        12 => ['name' => 'Chaitra', 'days' => 31]
    ];
    
    /**
     * Get Nepali month name
     */
    public static function getNepaliMonthName($month) {
        return self::$nepali_months[$month]['name'] ?? 'Unknown';
    }
    
    /**
     * Get Nepali month days
     */
    public static function getNepaliMonthDays($month) {
        return self::$nepali_months[$month]['days'] ?? 0;
    }
    
    /**
     * Get all Nepali months
     */
    public static function getAllNepaliMonths() {
        return self::$nepali_months;
    }
    
    /**
     * Convert English date to Nepali date
     * English date 2025-10-17 (Oct 17, 2025) = Nepali 2082-07-01
     * 
     * @param string $englishDate Format: Y-m-d
     * @return array Array with year, month, day, weekday_number
     */
    public static function convertToNepali($englishDate, $pdo = null) {
        // Reference point: English date 2025-10-17 = Nepali 2082-07-01 (Kartik 1, 2082)
        $reference_english = '2025-10-17';
        $reference_nepali = ['year' => 2082, 'month' => 7, 'day' => 1, 'weekday' => 4]; // Friday
        
        // Calculate days difference
        $englishDateTime = new DateTime($englishDate);
        $referenceDateTime = new DateTime($reference_english);
        $daysDiff = $englishDateTime->diff($referenceDateTime)->days;
        
        // Handle negative dates (past dates)
        if ($englishDateTime < $referenceDateTime) {
            $daysDiff = -$daysDiff;
        }
        
        // Start from reference Nepali date
        $year = $reference_nepali['year'];
        $month = $reference_nepali['month'];
        $day = $reference_nepali['day'];
        $weekday = $reference_nepali['weekday'];
        
        // If daysDiff is positive, increment; if negative, decrement
        if ($daysDiff > 0) {
            for ($i = 0; $i < $daysDiff; $i++) {
                $day++;
                $weekday = ($weekday + 1) % 7;
                
                // Check if month ended
                if ($day > self::getNepaliMonthDays($month)) {
                    $day = 1;
                    $month++;
                    
                    if ($month > 12) {
                        $year++;
                        $month = 1;
                    }
                }
            }
        } elseif ($daysDiff < 0) {
            $daysDiff = abs($daysDiff);
            for ($i = 0; $i < $daysDiff; $i++) {
                $day--;
                $weekday = ($weekday - 1 + 7) % 7;
                
                // Check if month started
                if ($day < 1) {
                    $month--;
                    if ($month < 1) {
                        $year--;
                        $month = 12;
                    }
                    $day = self::getNepaliMonthDays($month);
                }
            }
        }
        
        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'weekday' => $weekday,
            'month_name' => self::getNepaliMonthName($month),
            'formatted' => sprintf('%04d-%02d-%02d', $year, $month, $day)
        ];
    }
    
    /**
     * Get current Nepali date
     */
    public static function getCurrentNepaliDate($pdo = null) {
        return self::convertToNepali(date('Y-m-d'), $pdo);
    }
    
    /**
     * Get weekday name
     */
    public static function getWeekdayName($weekday) {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$weekday] ?? 'Unknown';
    }
    
    /**
     * Format Nepali date as readable string
     */
    public static function formatNepaliDate($year, $month, $day, $weekday) {
        $monthName = self::getNepaliMonthName($month);
        $weekdayName = self::getWeekdayName($weekday);
        
        return "{$weekdayName}, {$monthName} {$day}, {$year} BS";
    }
}
?>
