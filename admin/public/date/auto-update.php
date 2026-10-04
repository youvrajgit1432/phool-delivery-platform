<?php
/**
 * Auto-Update Nepali Date Script
 * This script should be run by cron job every day at 00:00 (midnight)
 * Cron command: 0 0 * * * cd /path/to/admin/public && php date/auto-update.php
 */

require_once __DIR__ . '/../../bootstrap/app.php';

try {
    $pdo = getDBConnection();
    
    // Get today's English date
    $today = date('Y-m-d');
    
    // Include converter
    require_once __DIR__ . '/NepaliDateConverter.php';
    
    // Convert to Nepali date
    $nepaliDate = NepaliDateConverter::convertToNepali($today, $pdo);
    
    // Check if current_nepali_date table exists, if not create it
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'current_nepali_date'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Create table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `current_nepali_date` (
            `id` int(11) NOT NULL,
            `english_date` date NOT NULL,
            `year` int(11) NOT NULL,
            `month` int(11) NOT NULL,
            `day` int(11) NOT NULL,
            `weekday` int(11) NOT NULL,
            `month_name` varchar(50) NOT NULL,
            `weekday_name` varchar(20) NOT NULL,
            `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
        
        $pdo->exec("ALTER TABLE `current_nepali_date` ADD PRIMARY KEY (`id`)");
        $pdo->exec("ALTER TABLE `current_nepali_date` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1");
    }
    
    // Update or insert current date
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM current_nepali_date");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE current_nepali_date SET 
            english_date = ?,
            year = ?, 
            month = ?, 
            day = ?, 
            weekday = ?,
            month_name = ?,
            weekday_name = ?,
            last_updated = NOW()
            WHERE id = 1");
        
        $stmt->execute([
            $today,
            $nepaliDate['year'],
            $nepaliDate['month'],
            $nepaliDate['day'],
            $nepaliDate['weekday'],
            $nepaliDate['month_name'],
            NepaliDateConverter::getWeekdayName($nepaliDate['weekday']),
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO current_nepali_date 
            (english_date, year, month, day, weekday, month_name, weekday_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $today,
            $nepaliDate['year'],
            $nepaliDate['month'],
            $nepaliDate['day'],
            $nepaliDate['weekday'],
            $nepaliDate['month_name'],
            NepaliDateConverter::getWeekdayName($nepaliDate['weekday']),
        ]);
    }
    
    // Log update
    $logMessage = date('Y-m-d H:i:s') . " - Updated Nepali date to: {$nepaliDate['formatted']} ({$nepaliDate['month_name']} {$nepaliDate['day']}, {$nepaliDate['year']} BS)\n";
    error_log($logMessage, 3, __DIR__ . '/nepali_date_update.log');
    
    echo $logMessage;
    
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    error_log($errorMessage, 3, __DIR__ . '/nepali_date_update.log');
    echo $errorMessage;
}
?>
