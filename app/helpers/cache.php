<?php
// app/helpers/cache.php
class Cache {
    private static function cacheDir() {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private static function keyToFile($key) {
        $hash = hash('sha256', $key);
        return rtrim(self::cacheDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    public static function set($key, $value, $ttl = 300) {
        $file = self::keyToFile($key);
        $data = [
            'expires_at' => time() + (int)$ttl,
            'payload' => $value
        ];
        $serialized = serialize($data);
        $fp = fopen($file, 'c');
        if (!$fp) return false;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, $serialized);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public static function get($key) {
        $file = self::keyToFile($key);
        if (!file_exists($file)) return false;
        $fp = fopen($file, 'r');
        if (!$fp) return false;
        flock($fp, LOCK_SH);
        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if (!$contents) return false;
        $data = @unserialize($contents);
        if (!is_array($data) || !isset($data['expires_at'])) return false;
        if ($data['expires_at'] < time()) {
            @unlink($file);
            return false;
        }
        return $data['payload'];
    }

    public static function delete($key) {
        $file = self::keyToFile($key);
        if (file_exists($file)) {@unlink($file);}
    }
}

?>
