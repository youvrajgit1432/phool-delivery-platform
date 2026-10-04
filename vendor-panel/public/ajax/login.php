<?php
/**
 * AJAX: Vendor Login Handler
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$identifier = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email/phone and password are required']);
    exit;
}

// Use the configured DB connection
$dbConfig = require __DIR__ . '/../../config/database.php';
$db = \App\Database\Connection::getInstance($dbConfig);

try {
    // Search by email OR phone (allow user to supply either)
    $stmt = $db->query('SELECT * FROM vendors WHERE email = ? OR phone = ? LIMIT 1', [$identifier, $identifier]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    $stored = $vendor['password'] ?? '';

    $passwordOk = false;
    if (!empty($stored)) {
        if (password_verify($password, $stored)) {
            $passwordOk = true;
        } elseif ($stored === $password) {
            // fallback for legacy plain-text (avoid recommending plain-text, but allow login)
            $passwordOk = true;
        }
    }

    if (!$passwordOk) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Check account status
    if (isset($vendor['status']) && strtolower($vendor['status']) !== 'active') {
        http_response_code(403);
        echo json_encode(['error' => 'Your account is not active. Please contact support.']);
        exit;
    }

    // Set session (remove password before saving)
    unset($vendor['password']);
    $_SESSION['vendor_id'] = (int)($vendor['id'] ?? 0);
    $_SESSION['vendor_data'] = $vendor;

    // Update last login
    $db->query('UPDATE vendors SET last_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), $_SESSION['vendor_id']]);

    // Debug: log session info
    error_log('[login] session_id=' . session_id() . ' vendor_id=' . $_SESSION['vendor_id']);

    // Remember me cookie
    if (isset($_POST['remember']) && $_POST['remember'] === '1') {
        setcookie('vendor_email', $identifier, time() + (30 * 24 * 60 * 60), '/phool-delivery-platform/vendor-panel/');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => '/phool-delivery-platform/vendor-panel/dashboard'
    ]);
    exit;
} catch (\Exception $e) {
    error_log('[login][error] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
    exit;
}
?>
