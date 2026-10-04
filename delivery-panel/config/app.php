<?php
/**
 * App Configuration
 * Core application settings
 */

return [
    'name' => getenv('APP_NAME') ?: 'Phool Delivery Rider Panel',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') === 'true',
    'url' => getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform/delivery-panel',
    
    'timezone' => 'Asia/Kathmandu',
    'locale' => 'en',
    'fallback_locale' => 'en',
    
    // Encryption
    'encryption_key' => getenv('ENCRYPTION_KEY'),
    'encryption_algorithm' => getenv('ENCRYPTION_ALGORITHM') ?: 'AES-256-CBC',
    
    // Cache settings
    'cache' => [
        'driver' => getenv('CACHE_DRIVER') ?: 'file',
        'ttl' => (int)(getenv('CACHE_TTL') ?: 3600),
    ],
    
    // Delivery specific settings
    'delivery' => [
        'min_rating' => (float)(getenv('DELIVERY_MIN_RATING') ?: 3.5),
        'max_penalty' => (int)(getenv('DELIVERY_MAX_PENALTY') ?: 10000),
        'otp_length' => (int)(getenv('DELIVERY_VERIFICATION_OTP_LENGTH') ?: 6),
        'otp_validity_minutes' => (int)(getenv('DELIVERY_OTP_VALIDITY_MINUTES') ?: 5),
    ],
    
    // Geofencing
    'geofence' => [
        'radius_meters' => (int)(getenv('GEOFENCE_RADIUS_METERS') ?: 100),
        'location_update_interval' => (int)(getenv('LOCATION_UPDATE_INTERVAL_SECONDS') ?: 60),
    ],
    
    // Feature flags
    'features' => [
        'cash_advance' => getenv('ENABLE_CASH_ADVANCE') === 'true',
        'wallet' => getenv('ENABLE_WALLET') === 'true',
        'referral' => getenv('ENABLE_REFERRAL') === 'true',
        'performance_bonus' => getenv('ENABLE_PERFORMANCE_BONUS') === 'true',
    ],
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => getenv('RATE_LIMIT_ENABLED') === 'true',
        'requests' => (int)(getenv('RATE_LIMIT_REQUESTS') ?: 100),
        'period' => (int)(getenv('RATE_LIMIT_PERIOD') ?: 3600),
    ],
    
    // File uploads
    'upload' => [
        'max_size' => (int)(getenv('MAX_UPLOAD_SIZE') ?: 5242880),
        'mime_types' => array_map('trim', explode(',', getenv('ALLOWED_MIME_TYPES') ?: 'image/jpeg,image/png,image/gif,application/pdf')),
        'path' => getenv('UPLOAD_PATH') ?: '../storage/uploads',
    ],
];
