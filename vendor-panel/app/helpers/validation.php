<?php
/**
 * Validation Helper Functions
 */

/**
 * Validate email
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number
 */
function validate_phone($phone)
{
    return preg_match('/^[0-9\-\+\(\)]{10,}$/', $phone);
}

/**
 * Validate URL
 */
function validate_url($url)
{
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Validate required fields
 */
function validate_required($data)
{
    return !empty($data);
}
?>
