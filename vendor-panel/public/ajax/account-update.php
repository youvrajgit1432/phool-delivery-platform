<?php
/**
 * AJAX: Account Update Handler
 * Handles updates for store, personal, business, and bank information
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
header('Content-Type: application/json');

function send_json_and_exit($data, $status = 200)
{
    http_response_code($status);
    $buf = ob_get_clean();
    if (!empty($buf)) {
        error_log('[account-update stray output] ' . $buf);
    }
    echo json_encode($data);
    exit;
}

// Ensure bootstrap files exist
$bootstrapAutoload = __DIR__ . '/../../bootstrap/autoload.php';
$bootstrapApp = __DIR__ . '/../../bootstrap/app.php';
if (!file_exists($bootstrapAutoload) || !file_exists($bootstrapApp)) {
    send_json_and_exit(['success' => false, 'message' => 'Server configuration error: bootstrap files missing'], 500);
}

require_once $bootstrapAutoload;
require_once $bootstrapApp;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
use App\Database\Connection;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$vendorId = $_POST['vendor_id'] ?? null;
if (!$vendorId) {
    $vendorId = $_SESSION['vendor_id'] ?? null;
}

if (!$vendorId) {
    send_json_and_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $dbConfig = $GLOBALS['config']['database'] ?? [];
    $db = Connection::getInstance($dbConfig);

    // Verify vendor exists
    $vendorCheck = $db->select('vendors', ['id' => $vendorId], 1);
    if (empty($vendorCheck)) {
        send_json_and_exit(['success' => false, 'message' => 'Vendor not found'], 404);
    }

    // Build update data from POST fields (only if they exist)
    $updateData = [];
    
    // Store information fields
    $storeFields = ['store_name', 'store_category', 'store_description'];
    // Personal information fields
    $personalFields = ['first_name', 'last_name', 'email', 'phone'];
    // Business information fields
    $businessFields = ['registration_number', 'tax_id', 'business_address', 'city', 'state', 'postal_code', 'country'];
    // Bank information fields
    $bankFields = ['bank_name', 'account_holder', 'account_number', 'ifsc_code'];
    
    $allFields = array_merge($storeFields, $personalFields, $businessFields, $bankFields);
    
    foreach ($allFields as $field) {
        if (isset($_POST[$field])) {
            $updateData[$field] = trim($_POST[$field]);
        }
    }

    if (empty($updateData)) {
        send_json_and_exit(['success' => false, 'message' => 'No data to update'], 400);
    }

    // Validate required fields if they are being updated
    if (isset($updateData['email']) && !filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
        send_json_and_exit(['success' => false, 'message' => 'Invalid email format'], 400);
    }

    if (isset($updateData['phone']) && !preg_match('/^[0-9]{10,}$/', $updateData['phone'])) {
        send_json_and_exit(['success' => false, 'message' => 'Invalid phone number'], 400);
    }

    // Update the vendor record
    $stmt = $db->update('vendors', $updateData, ['id' => $vendorId]);
    
    send_json_and_exit(['success' => true, 'message' => 'Account information updated successfully'], 200);

} catch (Throwable $e) {
    error_log('[account-update] Error: ' . $e->getMessage());
    send_json_and_exit(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
?>
