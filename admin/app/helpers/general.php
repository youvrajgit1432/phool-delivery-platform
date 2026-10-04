<?php
// app/helpers/general.php

/**
 * Get admin name by ID
 */
function getAdminName($admin_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : 'Unknown';
}

/**
 * Send OTP to customer for verification
 */
function sendOTP($customer_id, $method = 'sms') {
    global $pdo;
    
    // Generate 6-digit OTP
    $otp = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store OTP in database
    $stmt = $pdo->prepare("INSERT INTO customer_otps (customer_id, otp_code, method, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$customer_id, $otp, $method, $expires_at]);
    
    // Get customer details
    $stmt = $pdo->prepare("SELECT phone, email FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    // Send OTP via SMS or Email
    if ($method === 'sms' && !empty($customer['phone'])) {
        // Implement SMS sending logic here
        // $message = "Your verification code is: $otp. Valid for 10 minutes.";
        // sendSMS($customer['phone'], $message);
        return true;
    } elseif ($method === 'email' && !empty($customer['email'])) {
        // Implement email sending logic here
        // $subject = "Your Verification Code";
        // $message = "Your verification code is: <strong>$otp</strong><br>Valid for 10 minutes.";
        // sendEmail($customer['email'], $subject, $message);
        return true;
    }
    
    return false;
}

/**
 * Verify OTP code
 */
function verifyOTP($customer_id, $otp_code) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM customer_otps WHERE customer_id = ? AND otp_code = ? AND expires_at > NOW() AND used = 0");
    $stmt->execute([$customer_id, $otp_code]);
    $otp_record = $stmt->fetch();
    
    if ($otp_record) {
        // Mark OTP as used
        $pdo->prepare("UPDATE customer_otps SET used = 1, used_at = NOW() WHERE id = ?")->execute([$otp_record['id']]);
        
        // Mark customer as verified
        $pdo->prepare("UPDATE customers SET verified = 1, verification_method = 'otp', verified_at = NOW() WHERE id = ?")->execute([$customer_id]);
        
        return true;
    }
    
    return false;
}

/**
 * Get order statistics for dashboard
 */
function getOrderStats($timeframe = 'today') {
    global $pdo;
    
    $where = "";
    $params = [];
    
    switch ($timeframe) {
        case 'today':
            $where = "WHERE DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(total_amount) as total_revenue
        FROM orders 
        $where
    ");
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * Get low stock alerts
 */
function getLowStockAlerts($threshold = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE stock_quantity <= min_stock_alert 
        AND status = 'active'
        ORDER BY stock_quantity ASC
        LIMIT 10
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'NPR') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Get customer types with counts
 */
function getCustomerTypeCounts() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            customer_type,
            COUNT(*) as count
        FROM customers 
        WHERE status = 'active'
        GROUP BY customer_type
    ");
    $stmt->execute();
    
    $result = $stmt->fetchAll();
    $counts = [];
    
    foreach ($result as $row) {
        $counts[$row['customer_type']] = $row['count'];
    }
    
    return $counts;
}