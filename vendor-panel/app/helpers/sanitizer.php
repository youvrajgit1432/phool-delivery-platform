<?php
/**
 * Sanitization Helper Functions
 */

/**
 * Sanitize string input
 */
function sanitize($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize email
 */
function sanitize_email($email)
{
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize URL
 */
function sanitize_url($url)
{
    return filter_var($url, FILTER_SANITIZE_URL);
}
?>
