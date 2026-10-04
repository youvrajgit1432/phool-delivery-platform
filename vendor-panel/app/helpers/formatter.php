<?php
/**
 * Formatting Helper Functions
 */

/**
 * Format currency
 */
function format_currency($amount, $currency = 'INR')
{
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Format date
 */
function format_date($date, $format = 'd-m-Y')
{
    return date($format, strtotime($date));
}

/**
 * Format status
 */
function format_status($status)
{
    $statuses = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'completed' => 'Completed',
    ];
    return $statuses[$status] ?? ucfirst($status);
}
?>
