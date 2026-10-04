<?php
/**
 * CSRF Protection Helper Functions
 */

/**
 * Generate CSRF token input field
 */
function csrf_field()
{
    $token = \App\Middleware\CSRFProtection::generateToken();
    return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token
 */
function csrf_token()
{
    return \App\Middleware\CSRFProtection::generateToken();
}
?>
