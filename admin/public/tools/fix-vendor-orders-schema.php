<?php
/**
 * Migration: Verify vendor_orders table has all necessary columns
 * This script adds missing columns if they don't exist
 */

require_once '../../config/database.php';

try {
    // Create database connection
    $dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== Vendor Orders Schema Migration ===\n\n";

    // Check current columns
    $result = $db->query("DESCRIBE vendor_orders");
    $existing_columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }

    echo "Current columns in vendor_orders:\n";
    echo implode(", ", $existing_columns) . "\n\n";

    // Define required columns for order data (needed for rider assignment)
    $required_columns = [
        'customer_name' => "VARCHAR(255) DEFAULT NULL COMMENT 'Customer name'",
        'customer_phone' => "VARCHAR(20) DEFAULT NULL COMMENT 'Customer phone number'",
        'customer_address' => "TEXT DEFAULT NULL COMMENT 'Customer delivery address'",
        'total_items' => "INT(11) DEFAULT NULL COMMENT 'Total items in order'",
        'total_amount' => "DECIMAL(10,2) DEFAULT NULL COMMENT 'Total order amount'",
        'payment_method' => "VARCHAR(50) DEFAULT NULL COMMENT 'Payment method used'"
    ];

    // Add missing columns
    $added_columns = [];
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $sql = "ALTER TABLE vendor_orders ADD COLUMN $column $definition";
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
    $result = $db->query("DESCRIBE vendor_orders");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  • {$row['Field']} ({$row['Type']}) - " . ($row['Null'] == 'YES' ? 'nullable' : 'required') . "\n";
    }

    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
