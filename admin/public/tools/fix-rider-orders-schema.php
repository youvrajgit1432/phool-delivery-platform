<?php
/**
 * Migration: Ensure rider_orders table has all necessary columns
 * This script adds missing columns if they don't exist
 */

require_once '../../config/database.php';

try {
    // Create database connection
    $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== Rider Orders Schema Migration ===\n\n";

    // Check current columns
    $result = $db->query("DESCRIBE rider_orders");
    $existing_columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }

    echo "Current columns in rider_orders:\n";
    echo implode(", ", $existing_columns) . "\n\n";

    // Define required columns for order assignment
    $required_columns = [
        'customer_name' => "VARCHAR(255) COMMENT 'Customer name for delivery'",
        'customer_phone' => "VARCHAR(20) COMMENT 'Customer phone number'",
        'total_items' => "INT(11) DEFAULT 0 COMMENT 'Total items in order'",
        'vendor_name' => "VARCHAR(255) DEFAULT NULL COMMENT 'Vendor store name'",
        'vendor_phone' => "VARCHAR(20) DEFAULT NULL COMMENT 'Vendor phone for coordination'"
    ];

    // Add missing columns
    $added_columns = [];
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $sql = "ALTER TABLE rider_orders ADD COLUMN $column $definition";
                $db->exec($sql);
                $added_columns[] = $column;
                echo "✓ Added column: $column\n";
            } catch (Exception $e) {
                echo "✗ Failed to add column $column: " . $e->getMessage() . "\n";
            }
        } else {
            echo "• Column $column already exists\n";
        }
    }

    if (empty($added_columns)) {
        echo "\n✓ All required columns already exist!\n";
    } else {
        echo "\n✓ Added " . count($added_columns) . " missing column(s):\n";
        echo implode(", ", $added_columns) . "\n";
    }

    // Verify final structure
    echo "\n=== Final Schema ===\n";
    $result = $db->query("DESCRIBE rider_orders");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  • {$row['Field']} ({$row['Type']}) - " . ($row['Null'] == 'YES' ? 'nullable' : 'required') . "\n";
    }

    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
