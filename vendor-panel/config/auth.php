<?php
/**
 * Authentication Configuration
 */

return [
    'guards' => [
        'vendor' => [
            'driver' => 'session',
            'provider' => 'vendors',
        ],
    ],
    'providers' => [
        'vendors' => [
            'driver' => 'database',
            'model' => \App\Models\Vendor::class,
            'table' => 'vendors',
        ],
    ],
    'passwords' => [
        'vendors' => [
            'provider' => 'vendors',
            'table' => 'password_resets',
            'expire' => 60,
        ],
    ],
];
?>
