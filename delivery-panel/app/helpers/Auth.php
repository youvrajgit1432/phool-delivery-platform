<?php
/**
 * Authentication Helper Functions
 * Handles rider authentication logic
 */

/**
 * Check if rider is authenticated
 */
function isRiderAuthenticated() {
    return isset($_SESSION['rider_id']) && !empty($_SESSION['rider_id']);
}

/**
 * Get current rider ID
 */
function getCurrentRiderId() {
    return $_SESSION['rider_id'] ?? null;
}

/**
 * Require authentication
 * Redirects to login if not authenticated
 */
function requireAuth() {
    if (!isRiderAuthenticated()) {
        require_once dirname(__DIR__) . '/helpers/url.php';
        header('Location: ' . app_url('/login'));
        exit;
    }
}

/**
 * Logout current rider
 */
function logout() {
    if (isset($_SESSION['rider_id'])) {
        unset($_SESSION['rider_id']);
    }
    session_destroy();
}

/**
 * Create rider session
 */
function createSession($riderId, $rememberMe = false) {
    $_SESSION['rider_id'] = $riderId;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    if ($rememberMe) {
        setcookie('rider_id', $riderId, time() + (86400 * 30), '/');
    }
}

/**
 * Regenerate session ID (security)
 */
function regenerateSession() {
    session_regenerate_id(true);
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token for forms
 */
function getCsrfTokenField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if email is verified
 */
function isEmailVerified($riderId) {
    // TODO: Query database
    return true;
}

/**
 * Check if documents are verified
 */
function areDocumentsVerified($riderId) {
    // TODO: Query database
    return true;
}

/**
 * Get rider status
 */
function getRiderStatus($riderId) {
    // TODO: Query database
    return 'active';
}
