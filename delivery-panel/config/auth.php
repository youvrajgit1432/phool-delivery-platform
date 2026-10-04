<?php
/**
 * Authentication Configuration
 * Rider authentication and authorization settings
 */

return [
    'guard' => getenv('AUTH_GUARD') ?: 'rider',
    
    'guards' => [
        'rider' => [
            'driver' => 'session',
            'provider' => 'riders',
        ],
    ],
    
    'providers' => [
        'riders' => [
            'driver' => 'database',
            'model' => 'Phool\\DeliveryPanel\\Models\\Rider',
            'table' => 'riders',
        ],
    ],
    
    'tokens' => [
        'expiration' => (int)(getenv('TOKEN_EXPIRATION') ?: 3600),
        'refresh_expiration' => (int)(getenv('REFRESH_TOKEN_EXPIRATION') ?: 604800),
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key-change-this',
    ],
    
    'password' => [
        'algorithm' => 'bcrypt',
        'cost' => 10,
    ],
    
    'email_verification' => [
        'enabled' => true,
        'resend_timeout' => 3600,
    ],
    
    'phone_verification' => [
        'enabled' => true,
        'otp_length' => (int)(getenv('DELIVERY_VERIFICATION_OTP_LENGTH') ?: 6),
        'otp_timeout' => 300,
    ],
    
    'two_factor' => [
        'enabled' => false,
        'provider' => 'sms',
    ],
];
