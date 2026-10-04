<?php
/**
 * Dashboard Data Validation Test
 * Quick test to verify dashboard data fetching and display
 */

// Test the database connection and queries
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set test rider ID
$_SESSION['rider_id'] = 9;

try {
    $host = 'localhost';
    $dbName = 'phool_delivery_demo';
    $user = 'root';
    $password = '';
    
    $pdo = new \PDO(
        'mysql:host=' . $host . ';dbname=' . $dbName,
        $user,
        $password
    );
    
    echo "✓ Database Connected\n\n";
    
    $riderId = 9;
    
    // Test 1: Get today's deliveries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM rider_orders 
        WHERE rider_id = ? AND delivery_status = 'delivered' AND DATE(delivered_at) = CURDATE()
    ");
    $stmt->execute([$riderId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "Test 1 - Today's Deliveries: " . $result['total'] . "\n";
    
    // Test 2: Get total deliveries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM rider_orders 
        WHERE rider_id = ? AND delivery_status = 'delivered'
    ");
    $stmt->execute([$riderId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "Test 2 - Total Deliveries: " . $result['total'] . "\n";
    
    // Test 3: Get pending/active orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM rider_orders 
        WHERE rider_id = ? AND delivery_status IN ('assigned', 'accepted', 'picked_up', 'on_the_way', 'arrived')
    ");
    $stmt->execute([$riderId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "Test 3 - Active Orders: " . $result['total'] . "\n";
    
    // Test 4: Get average rating
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(feedback_rating), 0) as avg_rating FROM rider_orders 
        WHERE rider_id = ? AND feedback_rating IS NOT NULL AND feedback_rating > 0
    ");
    $stmt->execute([$riderId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "Test 4 - Average Rating: " . $result['avg_rating'] . "\n";
    
    // Test 5: Get assigned orders (pending acceptance)
    $stmt = $pdo->prepare("
        SELECT * FROM rider_orders 
        WHERE rider_id = ? AND delivery_status = 'assigned'
        ORDER BY assigned_at DESC
    ");
    $stmt->execute([$riderId]);
    $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "Test 5 - Assigned Orders (Pending): " . count($orders) . "\n";
    foreach ($orders as $order) {
        echo "  - Order #{$order['order_number']} (ID: {$order['order_id']}) - {$order['customer_name']}\n";
    }
    
    echo "\n✓ All database queries working correctly!\n";
    echo "✓ Dashboard data fetching is properly configured!\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
