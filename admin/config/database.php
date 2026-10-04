<?php
// config/database.php — sanitized public configuration.
// Single environment-driven configuration; no environment-specific secrets.

function getDatabaseConfig() {
    return [
        'driver'    => 'mysql',
        'host'      => getenv('DB_HOST') ?: 'localhost',
        'port'      => getenv('DB_PORT') ?: '3306',
        'database'  => getenv('DB_NAME') ?: 'phool_delivery_demo',
        'username'  => getenv('DB_USER') ?: 'root',
        'password'  => getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ];
}

return getDatabaseConfig();
?>
