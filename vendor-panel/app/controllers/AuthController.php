<?php

namespace App\Controllers;

class AuthController
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Handle login request
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Invalid request method'];
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($email) || empty($password)) {
            return ['error' => 'Email and password are required'];
        }

        // TODO: Query database to find vendor
        // $vendor = findVendorByEmail($email);
        // if (!$vendor || !password_verify($password, $vendor['password'])) {
        //     return ['error' => 'Invalid credentials'];
        // }

        // Check if account is active
        // if ($vendor['status'] !== 'active') {
        //     return ['error' => 'Your account is not active. Please contact support.'];
        // }

        // Set session
        $_SESSION['vendor_id'] = $vendor['id'] ?? 1;
        $_SESSION['vendor_data'] = $vendor ?? [];

        return ['success' => true, 'redirect' => '/phool-delivery-platform/vendor-panel/public/index.php'];
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        session_destroy();
        header('Location: /phool-delivery-platform/vendor-panel/public/login.php');
        exit;
    }

    /**
     * Show vendor registration form
     */
    public function showRegistration()
    {
        require_once __DIR__ . '/../views/auth/register.php';
    }

    /**
     * Handle vendor registration
     */
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Invalid request method'];
        }

        $data = $_POST;

        // Validation
        $validation_errors = $this->validateRegistration($data);
        if (!empty($validation_errors)) {
            return ['error' => implode(', ', $validation_errors)];
        }

        // Hash password
        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);

        // Prepare vendor data
        $vendor_data = [
            'first_name' => sanitize($data['first_name']),
            'last_name' => sanitize($data['last_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize($data['phone']),
            'store_name' => sanitize($data['store_name']),
            'store_category' => sanitize($data['store_category']),
            'store_description' => sanitize($data['store_description'] ?? ''),
            'registration_number' => sanitize($data['registration_number']),
            'tax_id' => sanitize($data['tax_id']),
            'business_address' => sanitize($data['business_address']),
            'city' => sanitize($data['city']),
            'state' => sanitize($data['state']),
            'postal_code' => sanitize($data['postal_code']),
            'country' => sanitize($data['country']),
            'password' => $password_hash,
            'bank_name' => sanitize($data['bank_name']),
            'account_number' => sanitize($data['account_number']),
            'ifsc_code' => sanitize($data['ifsc_code']),
            'account_holder' => sanitize($data['account_holder']),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // TODO: Insert vendor into database
        // $result = insertVendor($vendor_data);
        // if ($result) {
        //     // TODO: Send verification email
        //     return [
        //         'success' => true,
        //         'message' => 'Registration successful! Please verify your email.',
        //         'redirect' => '/phool-delivery-platform/vendor-panel/public/login.php'
        //     ];
        // }

        return ['success' => true, 'message' => 'Registration pending approval'];
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword()
    {
        require_once __DIR__ . '/../views/auth/forgot-password.php';
    }

    /**
     * Handle forgot password request
     */
    public function forgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['error' => 'Invalid request method'];
        }

        $email = $_POST['email'] ?? '';

        if (empty($email)) {
            return ['error' => 'Email is required'];
        }

        // TODO: Find vendor by email
        // TODO: Generate reset token
        // TODO: Send reset email

        return ['success' => true, 'message' => 'Check your email for reset instructions'];
    }

    /**
     * Validate registration data
     */
    private function validateRegistration($data)
    {
        $errors = [];

        $required_fields = ['first_name', 'last_name', 'email', 'phone', 'store_name', 
                          'registration_number', 'tax_id', 'business_address', 'city', 'state', 
                          'postal_code', 'country', 'password', 'password_confirm'];

        foreach ($required_fields as $field) {
            if (empty($data[$field] ?? '')) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email
        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors[] = 'Invalid email format';
        }

        // Validate phone
        if (!empty($data['phone']) && !validate_phone($data['phone'])) {
            $errors[] = 'Phone must be 10 digits';
        }

        // Validate password
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Validate password match
        if (!empty($data['password']) && $data['password'] !== ($data['password_confirm'] ?? '')) {
            $errors[] = 'Passwords do not match';
        }

        return $errors;
    }
}
?>
