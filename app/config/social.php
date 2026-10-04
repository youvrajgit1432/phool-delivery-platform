<?php
// app/config/social.php — sanitized public configuration.
// OAuth credentials are supplied via environment variables.

return [
    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI') ?: ((getenv('APP_URL') ?: 'http://localhost/phool-delivery-platform') . '/public/auth/google-callback'),
    ],
];
