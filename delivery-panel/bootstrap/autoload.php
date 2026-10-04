<?php
/**
 * Autoloader Configuration
 * PSR-4 compliant autoloading for the delivery panel
 */

// Composer autoloader
$autoloadFile = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
}

// Manual autoloader for development
spl_autoload_register(function ($class) {
    $prefix = 'Phool\\DeliveryPanel\\';
    $baseDir = dirname(dirname(__FILE__)) . '/app/';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Helper functions autoloader
$helpersDir = dirname(dirname(__FILE__)) . '/app/helpers/';
if (is_dir($helpersDir)) {
    foreach (glob($helpersDir . '*.php') as $helperFile) {
        if (basename($helperFile) !== '__init__.php') {
            require_once $helperFile;
        }
    }
}
