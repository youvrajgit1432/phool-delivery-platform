<?php
/**
 * AJAX: Vendor Registration Handler
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate all required fields
$required_fields = ['first_name', 'last_name', 'email', 'phone', 'store_name', 'store_category', 
                   'registration_number', 'tax_id', 'business_address', 'city', 'state', 
                   'postal_code', 'country', 'password', 'password_confirm', 'bank_name', 
                   'account_number', 'ifsc_code', 'account_holder'];

foreach ($required_fields as $field) {
    if (empty($_POST[$field] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

// Validate email format
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

// Validate phone number (10 digits)
if (!preg_match('/^\d{10}$/', $_POST['phone'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone number must be 10 digits']);
    exit;
}

// Validate password length
if (strlen($_POST['password']) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

// Validate password match
if ($_POST['password'] !== $_POST['password_confirm']) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}

// Validate terms
if (empty($_POST['terms'])) {
    http_response_code(400);
    echo json_encode(['error' => 'You must agree to Terms & Conditions']);
    exit;
}

// TODO: Check if email already exists in database
// $existingVendor = checkEmailExists($_POST['email']);
// if ($existingVendor) {
//     http_response_code(409);
//     echo json_encode(['error' => 'Email already registered']);
//     exit;
// }

// Hash password
$password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

// Prepare vendor data
$vendor_data = [
    'first_name' => sanitize($_POST['first_name']),
    'last_name' => sanitize($_POST['last_name']),
    'email' => sanitize_email($_POST['email']),
    'phone' => sanitize($_POST['phone']),
    'store_name' => sanitize($_POST['store_name']),
    'store_category' => sanitize($_POST['store_category']),
    'store_description' => sanitize($_POST['store_description'] ?? ''),
    'registration_number' => sanitize($_POST['registration_number']),
    'tax_id' => sanitize($_POST['tax_id']),
    'business_address' => sanitize($_POST['business_address']),
    'city' => sanitize($_POST['city']),
    'state' => sanitize($_POST['state']),
    'postal_code' => sanitize($_POST['postal_code']),
    'country' => sanitize($_POST['country']),
    'password' => $password_hash,
    'bank_name' => sanitize($_POST['bank_name']),
    'account_number' => sanitize($_POST['account_number']),
    'ifsc_code' => sanitize($_POST['ifsc_code']),
    'account_holder' => sanitize($_POST['account_holder']),
    'status' => 'pending', // New vendors are pending approval
    'created_at' => date('Y-m-d H:i:s'),
];

// TODO: Insert into database
// $insertQuery = insertVendor($vendor_data);
// if ($insertQuery) {
//     echo json_encode([
//         'success' => true,
//         'message' => 'Registration successful! Please wait for admin approval.',
//         'redirect' => '/phool-delivery-platform/vendor-panel/public/login.php?registered=1'
//     ]);
// } else {
//     http_response_code(500);
//     echo json_encode(['error' => 'Registration failed. Please try again.']);
// }

// For now, return success
echo json_encode([
    'success' => true,
    'message' => 'Registration successful! Please check your email to verify your account.',
    'data' => $vendor_data
]);
?>
