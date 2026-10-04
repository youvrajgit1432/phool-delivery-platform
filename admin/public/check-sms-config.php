<?php
/**
 * Check SMS Configuration Details
 */
require_once __DIR__ . '/../bootstrap/app.php';

$db = getDBConnection();

echo "<h2>📱 SMS Configuration Check</h2>\n\n";

// 1. Check all SMS-related settings
echo "<h3>SMS Settings in system_settings:</h3>\n";
$smsStmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'sms_%' ORDER BY setting_key");
$smsStmt->execute();
$smsSettings = $smsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($smsSettings)) {
    echo "<span style='color:red;'>❌ NO SMS settings found!</span><br>";
} else {
    echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
    echo "<tr><th>Setting Key</th><th>Value</th></tr>";
    foreach ($smsSettings as $setting) {
        $value = $setting['setting_value'];
        // Mask sensitive values
        if (strpos($setting['setting_key'], 'key') !== false) {
            $value = strlen($value) > 0 ? substr($value, 0, 5) . '...' . substr($value, -5) : '(empty)';
        }
        echo "<tr><td>{$setting['setting_key']}</td><td><code>" . htmlspecialchars($value) . "</code></td></tr>";
    }
    echo "</table>";
}

// 2. Test SMS Send
echo "<h3>Manual SMS Send Test:</h3>\n";
echo "<form method='POST'>";
echo "<input type='text' name='phone' placeholder='Phone (e.g., 9844634579)' value='9844634579'>";
echo "<input type='submit' name='test_sms' value='Test Send'>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sms'])) {
    $phone = $_POST['phone'] ?? '';
    echo "<div style='background:#e3f2fd; padding:15px; margin:15px 0; border-radius:5px; border-left:4px solid #2196F3;'>";
    echo "<strong>Testing SMS to: " . htmlspecialchars($phone) . "</strong><br><br>";
    
    try {
        require_once __DIR__ . '/../app/services/SparrowSMSService.php';
        $smsService = new SparrowSMSService($db);
        
        // Format phone
        $phoneFormatted = $phone;
        if (strpos($phoneFormatted, '977') !== 0) {
            $phoneFormatted = '977' . $phoneFormatted;
        }
        
        echo "Formatted phone: " . htmlspecialchars($phoneFormatted) . "<br>";
        echo "Sending test message...<br><br>";
        
        $result = $smsService->sendSMS($phoneFormatted, "Test SMS from Phool Delivery admin panel");
        
        echo "<strong>Result:</strong><br>";
        echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
        
        if ($result['success']) {
            echo "<span style='color:green;'>✅ SMS sent successfully!</span>";
        } else {
            echo "<span style='color:red;'>❌ SMS send failed!</span>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    echo "</div>";
}

?>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #666; padding-bottom: 10px; }
h3 { color: #555; margin-top: 20px; }
table { margin: 10px 0; }
th { background: #f0f0f0; font-weight: bold; }
tr:nth-child(even) { background: #f9f9f9; }
code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
input { padding: 8px; margin: 5px; font-size: 14px; }
</style>
