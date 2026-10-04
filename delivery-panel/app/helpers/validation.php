<?php
/**
 * Validation Helper Functions
 * Validate rider inputs
 */

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Nepal format)
 */
function validatePhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    // Nepal phone numbers are 10 digits starting with 9
    return preg_match('/^9\d{9}$/', $phone) === 1;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 digit, 1 special char
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password) === 1;
}

/**
 * Validate coordinate
 */
function validateCoordinate($latitude, $longitude) {
    return (is_numeric($latitude) && is_numeric($longitude) &&
            $latitude >= -90 && $latitude <= 90 &&
            $longitude >= -180 && $longitude <= 180);
}

/**
 * Validate OTP
 */
function validateOTP($otp, $length = 6) {
    return strlen($otp) === $length && is_numeric($otp);
}

/**
 * Validate vehicle number
 */
function validateVehicleNumber($vehicleNumber) {
    // Basic Nepal vehicle number validation
    // Format: BA-01-PA-1234 or similar
    return preg_match('/^[A-Z]{2}-\d{2}-[A-Z]{2}-\d{4}$/', $vehicleNumber) === 1;
}

/**
 * Validate license number
 */
function validateLicenseNumber($licenseNumber) {
    // Basic Nepal license number validation
    return strlen($licenseNumber) >= 5 && strlen($licenseNumber) <= 20;
}

/**
 * Validate bank account number
 */
function validateAccountNumber($accountNumber) {
    // Remove spaces and special characters
    $accountNumber = preg_replace('/\D/', '', $accountNumber);
    
    // Typically 10-18 digits
    return strlen($accountNumber) >= 10 && strlen($accountNumber) <= 18;
}

/**
 * Validate IFSC code
 */
function validateIFSCCode($ifscCode) {
    // Format: 4 letters + 0 + 6 digits
    return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifscCode) === 1;
}

/**
 * Validate address
 */
function validateAddress($address) {
    return strlen($address) >= 10 && strlen($address) <= 500;
}

/**
 * Validate special instructions
 */
function validateSpecialInstructions($instructions) {
    return strlen($instructions) <= 500;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $maxSize = 5242880, $allowedMimes = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'File not found'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds limit'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowedMimes)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    return ['valid' => true];
}

/**
 * Validate delivery status transition
 */
function validateStatusTransition($currentStatus, $newStatus) {
    $validTransitions = [
        'assigned' => ['accepted', 'cancelled'],
        'accepted' => ['picked_up', 'cancelled'],
        'picked_up' => ['on_the_way', 'failed'],
        'on_the_way' => ['arrived', 'failed'],
        'arrived' => ['delivered', 'failed'],
        'failed' => ['assigned'],
        'cancelled' => [],
        'delivered' => [],
        'returned' => [],
    ];
    
    return isset($validTransitions[$currentStatus]) && 
           in_array($newStatus, $validTransitions[$currentStatus]);
}

/**
 * Validate COD amount
 */
function validateCODAmount($amount, $orderTotal) {
    return $amount >= 0 && $amount <= $orderTotal;
}

/**
 * Validate time slot
 */
function validateTimeSlot($from, $to) {
    $fromTime = strtotime($from);
    $toTime = strtotime($to);
    
    return $fromTime !== false && $toTime !== false && $fromTime < $toTime;
}
