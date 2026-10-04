<?php
/**
 * Authentication Helper Functions
 */

/**
 * Get current authenticated vendor
 */
function auth()
{
    return $_SESSION['vendor_id'] ?? null;
}

/**
 * Check if vendor is authenticated
 */
function is_authenticated()
{
    return isset($_SESSION['vendor_id']);
}

/**
 * Get vendor session data
 */
function vendor()
{
    return $_SESSION['vendor_data'] ?? null;
}

/**
 * Logout vendor
 */
function logout()
{
    session_destroy();
    header('Location: /phool-delivery-platform/vendor-panel/login');
    exit;
}
?>
