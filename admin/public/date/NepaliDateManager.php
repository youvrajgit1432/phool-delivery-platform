<?php
/**
 * Nepali Date Manager
 * Quick functions to get and update current Nepali date
 * Use this in other parts of your application
 */

class NepaliDateManager {
    private static $pdo = null;
    
    /**
     * Initialize PDO connection
     */
    private static function initDB() {
        if (self::$pdo === null) {
            require_once __DIR__ . '/../../bootstrap/app.php';
            self::$pdo = getDBConnection();
        }
    }
    
    /**
     * Get current Nepali date from database
     * @return array|false Array with date info or false if not found
     */
    public static function getCurrentNepaliDate() {
        self::initDB();
        
        try {
            $stmt = self::$pdo->prepare("SELECT * FROM current_nepali_date LIMIT 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get current date in Nepali format
     * @return string Example: "Poush 20, 2082"
     */
    public static function getFormattedNepaliDate() {
        $date = self::getCurrentNepaliDate();
        if ($date) {
            return $date['month_name'] . ' ' . $date['day'] . ', ' . $date['year'];
        }
        return 'N/A';
    }
    
    /**
     * Get current Nepali year
     * @return int Example: 2082
     */
    public static function getCurrentNepaliYear() {
        $date = self::getCurrentNepaliDate();
        return $date ? $date['year'] : null;
    }
    
    /**
     * Get current Nepali month number
     * @return int Example: 9 (for Poush)
     */
    public static function getCurrentNepaliMonth() {
        $date = self::getCurrentNepaliDate();
        return $date ? $date['month'] : null;
    }
    
    /**
     * Get current Nepali day
     * @return int Example: 20
     */
    public static function getCurrentNepaliDay() {
        $date = self::getCurrentNepaliDate();
        return $date ? $date['day'] : null;
    }
    
    /**
     * Get current Nepali month name
     * @return string Example: "Poush"
     */
    public static function getCurrentMonthName() {
        $date = self::getCurrentNepaliDate();
        return $date ? $date['month_name'] : null;
    }
    
    /**
     * Get current weekday name
     * @return string Example: "Friday"
     */
    public static function getCurrentWeekday() {
        $date = self::getCurrentNepaliDate();
        return $date ? $date['weekday_name'] : null;
    }
    
    /**
     * Get date as array [year, month, day]
     * @return array Example: [2082, 9, 20]
     */
    public static function getDateArray() {
        $date = self::getCurrentNepaliDate();
        if ($date) {
            return [
                'year' => $date['year'],
                'month' => $date['month'],
                'day' => $date['day']
            ];
        }
        return null;
    }
    
    /**
     * Format date for specific use cases
     * @param string $format Format string (yyyy, mm, dd, mname, wday)
     * @return string Formatted date
     */
    public static function formatDate($format = 'yyyy-mm-dd') {
        $date = self::getCurrentNepaliDate();
        if (!$date) return 'N/A';
        
        $result = $format;
        $result = str_replace('yyyy', $date['year'], $result);
        $result = str_replace('mm', str_pad($date['month'], 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('dd', str_pad($date['day'], 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('mname', $date['month_name'], $result);
        $result = str_replace('wday', $date['weekday_name'], $result);
        
        return $result;
    }
    
    /**
     * Check if today is a specific Nepali month
     * @param int $monthNumber Month number (1-12)
     * @return bool
     */
    public static function isMonth($monthNumber) {
        $date = self::getCurrentNepaliDate();
        return $date && $date['month'] == $monthNumber;
    }
    
    /**
     * Check if it's a specific Nepali date
     * @param int $year
     * @param int $month
     * @param int $day
     * @return bool
     */
    public static function isDate($year, $month, $day) {
        $date = self::getCurrentNepaliDate();
        return $date && 
               $date['year'] == $year && 
               $date['month'] == $month && 
               $date['day'] == $day;
    }
    
    /**
     * Update the current Nepali date manually
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $weekday
     * @param string $monthName
     * @param string $weekdayName
     * @return bool
     */
    public static function updateDate($year, $month, $day, $weekday, $monthName, $weekdayName) {
        self::initDB();
        
        try {
            $englishDate = date('Y-m-d');
            $stmt = self::$pdo->prepare("
                UPDATE current_nepali_date SET 
                    english_date = ?,
                    year = ?, 
                    month = ?, 
                    day = ?, 
                    weekday = ?,
                    month_name = ?,
                    weekday_name = ?
                WHERE id = 1
            ");
            
            return $stmt->execute([
                $englishDate,
                $year,
                $month,
                $day,
                $weekday,
                $monthName,
                $weekdayName
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Example usage:
/*
// In any PHP file, use:
require_once 'path/to/NepaliDateManager.php';

// Get current date
$currentDate = NepaliDateManager::getCurrentNepaliDate();

// Get formatted date
echo NepaliDateManager::getFormattedNepaliDate();  // Output: "Poush 20, 2082"

// Get individual components
echo NepaliDateManager::getCurrentNepaliYear();     // Output: 2082
echo NepaliDateManager::getCurrentMonthName();      // Output: "Poush"
echo NepaliDateManager::getCurrentWeekday();        // Output: "Friday"

// Check if it's a specific month
if (NepaliDateManager::isMonth(9)) {
    echo "We are in Poush month";
}

// Format date custom way
echo NepaliDateManager::formatDate('mname dd, yyyy');  // Output: "Poush 20, 2082"
echo NepaliDateManager::formatDate('yyyy-mm-dd');      // Output: "2082-09-20"
*/
?>
