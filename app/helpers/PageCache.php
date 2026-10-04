<?php
// app/helpers/PageCache.php
// PERFORMANCE FIX #3: Full-page output caching
// Caches entire HTML response to file for 5 minutes
// Invalidate manually when content changes

class PageCache {
    private static $cacheDir = null;
    private static $enabled = true;
    
    /**
     * Initialize cache directory
     */
    public static function init($cacheDirectory = null) {
        if ($cacheDirectory === null) {
            $cacheDirectory = __DIR__ . '/../../storage/cache';
        }
        
        self::$cacheDir = $cacheDirectory;
        
        // Create cache directory if it doesn't exist
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache file path for current page
     */
    private static function getCacheKey() {
        $userId = isset($_SESSION['customer_id']) ? '_user_' . $_SESSION['customer_id'] : '_guest';
        $cityId = isset($_SESSION['user_city_id']) ? $_SESSION['user_city_id'] : '1';
        $language = defined('LANGUAGE') ? LANGUAGE : 'en';
        
        // Create unique key for different user states and languages
        $key = 'page_' . md5($_SERVER['REQUEST_URI'] . $userId . $cityId . $language);
        
        return self::$cacheDir . '/' . $key . '.html';
    }
    
    /**
     * Start output buffering for page cache
     * Call this at the beginning of your page
     */
    public static function start() {
        if (!self::$enabled) return;
        
        self::init();
        
        $cacheFile = self::getCacheKey();
        
        // Return cached page if it exists and is fresh
        if (file_exists($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            
            // Cache is valid for 5 minutes (300 seconds)
            if ($fileAge < 300) {
                header('X-Cache: HIT');
                readfile($cacheFile);
                exit;
            }
        }
        
        // Cache miss - start buffering output
        header('X-Cache: MISS');
        ob_start();
    }
    
    /**
     * Save buffered output to cache file
     * Call this at the end of your page (before closing)
     */
    public static function end($ttl = 300) {
        if (!self::$enabled) {
            ob_end_flush();
            return;
        }
        
        self::init();
        
        $output = ob_get_clean();
        $cacheFile = self::getCacheKey();
        
        // Write to cache file
        file_put_contents($cacheFile, $output, LOCK_EX);
        
        // Output the page
        echo $output;
    }
    
    /**
     * Invalidate cache for current page
     */
    public static function invalidate() {
        self::init();
        $cacheFile = self::getCacheKey();
        
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
    
    /**
     * Clear ALL cache files
     */
    public static function clearAll() {
        self::init();
        
        $cacheFiles = glob(self::$cacheDir . '/*.html');
        foreach ($cacheFiles as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Disable caching (for testing)
     */
    public static function disable() {
        self::$enabled = false;
    }
    
    /**
     * Enable caching
     */
    public static function enable() {
        self::$enabled = true;
    }
}

// Initialize on load
PageCache::init();
?>
