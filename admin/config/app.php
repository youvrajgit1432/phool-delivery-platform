<?php
// config/app.php

return [
    'name' => 'Phool Delivery',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') ?: false,
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => 'Asia/Kathmandu',
    'locale' => 'en',
    'key' => getenv('APP_KEY') ?: 'base64:YourRandomKeyHere=',
];              




