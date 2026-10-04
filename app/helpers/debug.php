<?php
class DebugHelper {
    private static $debugEnabled = true;
    private static $logFile = '/home2/phooldel/debug.log';
    
    public static function enable() {
        self::$debugEnabled = true;
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logFile);
    }
    
    public static function disable() {
        self::$debugEnabled = false;
    }
    
    public static function log($message, $data = null) {
        if (!self::$debugEnabled) return;
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        
        if ($data !== null) {
            $logMessage .= " | Data: " . self::formatData($data);
        }
        
        $logMessage .= "\n";
        
        // Log to file
        error_log($logMessage, 3, self::$logFile);
        
        // Also log to PHP error log
        error_log($message);
    }
    
    public static function formatData($data) {
        if (is_array($data) || is_object($data)) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        return (string)$data;
    }
    
    public static function dump($variable, $label = '') {
        if (!self::$debugEnabled) return;
        
        $output = "";
        if ($label) {
            $output .= "$label: ";
        }
        
        if (is_array($variable) || is_object($variable)) {
            $output .= print_r($variable, true);
        } else {
            $output .= var_export($variable, true);
        }
        
        self::log("DUMP: " . $output);
    }
    
    public static function checkFile($filePath, $description = '') {
        $exists = file_exists($filePath);
        $readable = is_readable($filePath);
        $writable = is_writable($filePath);
        
        $status = "File Check [$description]: $filePath | " .
                 "Exists: " . ($exists ? 'YES' : 'NO') . " | " .
                 "Readable: " . ($readable ? 'YES' : 'NO') . " | " .
                 "Writable: " . ($writable ? 'YES' : 'NO');
        
        self::log($status);
        return $exists && $readable;
    }
    
    public static function checkDirectory($dirPath, $description = '') {
        $exists = is_dir($dirPath);
        $readable = is_readable($dirPath);
        $writable = is_writable($dirPath);
        
        $status = "Directory Check [$description]: $dirPath | " .
                 "Exists: " . ($exists ? 'YES' : 'NO') . " | " .
                 "Readable: " . ($readable ? 'YES' : 'NO') . " | " .
                 "Writable: " . ($writable ? 'YES' : 'NO');
        
        self::log($status);
        return $exists && $readable;
    }
    
    public static function getMemoryUsage() {
        $memory = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        
        self::log("Memory Usage: " . self::formatBytes($memory) . 
                 " | Peak: " . self::formatBytes($peak));
    }
    
    public static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public static function displayErrors() {
        if (!self::$debugEnabled) return '';
        
        $errors = error_get_last();
        if ($errors) {
            return "<div style='background: #ffebee; border: 2px solid #f44336; padding: 15px; margin: 10px; border-radius: 5px;'>
                    <h3 style='color: #c62828; margin-top: 0;'>Last Error:</h3>
                    <pre style='background: white; padding: 10px; border-radius: 3px;'>" . 
                    print_r($errors, true) . "</pre>
                    </div>";
        }
        return '';
    }
}
?>