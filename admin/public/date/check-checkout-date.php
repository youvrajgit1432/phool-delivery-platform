<?php
/**
 * Diagnostic script to verify Nepali date setup for checkout page
 */

require_once '../../bootstrap/app.php';

$pdo = getDBConnection();

echo "=== NEPALI DATE CHECKOUT DIAGNOSTIC ===\n\n";

try {
    // Check if current_nepali_date table exists
    echo "1. Checking current_nepali_date table...\n";
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'current_nepali_date'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "   ERROR: current_nepali_date table does not exist!\n";
    } else {
        echo "   ✓ current_nepali_date table exists\n";
    }
    
    // Check if current_nepali_date has data
    echo "\n2. Checking current_nepali_date data...\n";
    $stmt = $pdo->prepare("SELECT * FROM current_nepali_date LIMIT 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $date = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✓ Data found:\n";
        echo "     Year: " . $date['year'] . "\n";
        echo "     Month: " . $date['month'] . "\n";
        echo "     Day: " . $date['day'] . "\n";
        echo "     Weekday: " . $date['weekday'] . "\n";
        echo "     Last Updated: " . ($date['last_updated'] ?? 'Not set') . "\n";
    } else {
        echo "   ERROR: No data in current_nepali_date table!\n";
        echo "   Insert this data:\n";
        echo "   INSERT INTO current_nepali_date (english_date, year, month, day, weekday, month_name, weekday_name)\n";
        echo "   VALUES ('2025-10-17', 2082, 7, 1, 4, 'Kartik', 'Friday');\n";
    }
    
    // Check nepali_calendar_years table
    echo "\n3. Checking nepali_calendar_years table...\n";
    $stmt = $pdo->prepare("SELECT * FROM nepali_calendar_years ORDER BY year ASC");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "   ✓ Available years:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "     Year " . $row['year'] . " (ID: " . $row['id'] . ")\n";
        }
    } else {
        echo "   ERROR: No years in nepali_calendar_years table!\n";
    }
    
    // Test the converter class
    echo "\n4. Testing NepaliDateConverter...\n";
    require_once 'NepaliDateConverter.php';
    
    $today = date('Y-m-d');
    $nepaliToday = NepaliDateConverter::convertToNepali($today);
    echo "   English date: " . $today . "\n";
    echo "   Converted to Nepali: " . $nepaliToday['formatted'] . "\n";
    echo "   Full format: " . NepaliDateConverter::formatNepaliDate(
        $nepaliToday['year'],
        $nepaliToday['month'],
        $nepaliToday['day'],
        $nepaliToday['weekday']
    ) . "\n";
    
    echo "\n=== ALL CHECKS COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
