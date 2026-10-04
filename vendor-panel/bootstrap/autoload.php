<?php
/**
 * Bootstrap Autoload
 */

// Load environment variables from .env file
if (file_exists(dirname(dirname(__FILE__)) . '/.env')) {
    $env_file = dirname(dirname(__FILE__)) . '/.env';
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            if (!getenv($key)) {
                putenv($key . '=' . $value);
            }
        }
    }
}

// Define base paths
define('BASE_PATH', dirname(dirname(__FILE__)));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('CACHE_PATH', STORAGE_PATH . '/cache');
define('VIEWS_PATH', APP_PATH . '/views');

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = APP_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helper functions
if (file_exists(APP_PATH . '/helpers/functions.php')) {
    require_once APP_PATH . '/helpers/functions.php';
}
?>
