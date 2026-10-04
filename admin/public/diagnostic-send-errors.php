<?php
/**
 * Diagnostic script to check email/SMS send failures
 */
require_once __DIR__ . '/../bootstrap/app.php';

$db = getDBConnection();

echo "<h2>🔍 Credential Send Diagnostics</h2>\n\n";

// 1. Check SMTP Configuration
echo "<h3>1. SMTP Configuration in system_settings:</h3>\n";
$smtpStmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'sms_%'");
$smtpStmt->execute();
$smtpSettings = $smtpStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($smtpSettings)) {
    echo "<span style='color:red;'>❌ NO SMTP or SMS settings found in database!</span><br>";
} else {
    echo "<pre>";
    foreach ($smtpSettings as $setting) {
        $value = $setting['setting_value'];
        // Mask sensitive values
        if (strpos($setting['setting_key'], 'password') !== false || strpos($setting['setting_key'], 'key') !== false) {
            $value = strlen($value) > 0 ? '***' . substr($value, -4) : '(empty)';
        }
        echo $setting['setting_key'] . " => " . $value . "\n";
    }
    echo "</pre>";
}

// 2. Check recent credential send attempts
echo "<h3>2. Recent Credential Notifications (last 10):</h3>\n";
$notStmt = $db->prepare("
    SELECT id, vendor_id, notification_type, title, message, data, created_at 
    FROM vendor_notifications 
    WHERE notification_type = 'credentials' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$notStmt->execute();
$notifications = $notStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifications)) {
    echo "<span style='color:orange;'>⚠️ No credential notifications found!</span><br>";
} else {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Vendor ID</th><th>Title</th><th>Message</th><th>Data (JSON)</th><th>Time</th></tr>";
    foreach ($notifications as $n) {
        $data = json_decode($n['data'], true) ?: [];
        $dataStr = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "<tr>";
        echo "<td>{$n['id']}</td>";
        echo "<td>{$n['vendor_id']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['message']}</td>";
        echo "<td><pre style='background:#f5f5f5; padding:10px; border-radius:3px; max-height:150px; overflow:auto;'>" . htmlspecialchars($dataStr) . "</pre></td>";
        echo "<td>" . $n['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Parse failure details
echo "<h3>3. Failure Analysis:</h3>\n";
$failureStmt = $db->prepare("
    SELECT vendor_id, title, message, data, created_at 
    FROM vendor_notifications 
    WHERE notification_type = 'credentials' AND (title LIKE '%failed%' OR message LIKE '%failed%')
    ORDER BY created_at DESC 
    LIMIT 5
");
$failureStmt->execute();
$failures = $failureStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($failures)) {
    echo "<span style='color:green;'>✅ No recent credential send failures recorded.</span><br>";
} else {
    echo "<span style='color:red;'>❌ Found " . count($failures) . " failure(s):</span><br><br>";
    foreach ($failures as $f) {
        echo "<div style='background:#ffe0e0; padding:15px; margin:10px 0; border-radius:5px; border-left:4px solid red;'>";
        echo "<strong>Vendor ID: " . $f['vendor_id'] . "</strong> (" . $f['created_at'] . ")<br>";
        echo "<strong>Title:</strong> " . $f['title'] . "<br>";
        echo "<strong>Message:</strong> " . $f['message'] . "<br>";
        $data = json_decode($f['data'], true) ?: [];
        if (!empty($data['email_error'])) {
            echo "<strong style='color:red;'>Email Error:</strong> " . htmlspecialchars($data['email_error']) . "<br>";
        }
        if (!empty($data['sms_response'])) {
            echo "<strong>SMS Response:</strong> <pre>" . htmlspecialchars(json_encode($data['sms_response'], JSON_PRETTY_PRINT)) . "</pre>";
        }
        echo "</div>";
    }
}

// 4. Check vendor data
echo "<h3>4. Recent Vendors (last 5):</h3>\n";
$vendorStmt = $db->prepare("
    SELECT id, store_name, email, phone, created_at 
    FROM vendors 
    ORDER BY created_at DESC 
    LIMIT 5
");
$vendorStmt->execute();
$vendors = $vendorStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($vendors)) {
    echo "<span style='color:orange;'>⚠️ No vendors found!</span><br>";
} else {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Store Name</th><th>Email</th><th>Phone</th><th>Created</th></tr>";
    foreach ($vendors as $v) {
        echo "<tr><td>{$v['id']}</td><td>{$v['store_name']}</td><td>{$v['email']}</td><td>{$v['phone']}</td><td>{$v['created_at']}</td></tr>";
    }
    echo "</table>";
}

?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #666; padding-bottom: 10px; }
h3 { color: #555; margin-top: 20px; }
table { margin: 10px 0; }
th { background: #f0f0f0; font-weight: bold; }
tr:nth-child(even) { background: #f9f9f9; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>
