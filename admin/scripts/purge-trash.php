<?php
/**
 * Purge trashed products older than 30 days.
 * Intended to be run from CLI (cron) as PHP script.
 */

require_once __DIR__ . '/../bootstrap/app.php';

$db = getDBConnection();

try {
    // Ensure deleted_at column exists
    $colStmt = $db->prepare("SHOW COLUMNS FROM vendor_products LIKE 'deleted_at'");
    $colStmt->execute();
    $col = $colStmt->fetch();

    if (!$col) {
        echo "No deleted_at column on vendor_products. Nothing to purge.\n";
        exit(0);
    }

    // Delete permanently rows with deleted_at older than 30 days
    $stmt = $db->prepare("DELETE FROM vendor_products WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $count = $stmt->rowCount();

    echo "Purged $count trashed products older than 30 days.\n";

} catch (Exception $e) {
    echo "Error purging trash: " . $e->getMessage() . "\n";
    exit(1);
}

?>
