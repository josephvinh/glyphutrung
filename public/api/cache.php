<?php
/**
 * Simple file-based caching for TNTT API
 * Cache stored outside web root (storage/cache/) for security (S1)
 */

class Cache {
    private static string $dir;

    public static function init(): void {
        if (!isset(self::$dir)) {
            self::$dir = dirname(__DIR__, 2) . '/storage/cache';
        }
        if (!is_dir(self::$dir)) {
            if (!mkdir(self::$dir, 0755, true)) {
                error_log('Cache: Cannot create cache directory: ' . self::$dir);
            }
        }
    }

    public static function get(string $key): ?array {
        self::init();
        $file = self::$dir . '/' . md5($key) . '.json';
        if (!file_exists($file)) return null;

        $data = json_decode(file_get_contents($file), true);
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public static function set(string $key, $value, int $ttl = 300): void {
        self::init();
        $file = self::$dir . '/' . md5($key) . '.json';
        $ok = file_put_contents($file, json_encode([
            'value' => $value,
            'expires' => time() + $ttl,
        ]));
        if ($ok === false) {
            error_log('Cache: Cannot write cache file: ' . $file);
        }
    }

    public static function del(string $key): void {
        self::init();
        $file = self::$dir . '/' . md5($key) . '.json';
        if (file_exists($file)) @unlink($file);
    }

    public static function flush(): void {
        self::init();
        foreach (glob(self::$dir . '/*.json') as $file) {
            @unlink($file);
        }
        // Ghi lại mốc thời gian để client polling phát hiện thay đổi
        $syncFile = self::$dir . '/sync.txt';
        $ok = @file_put_contents($syncFile, (string) time());
        if ($ok === false) {
            error_log('Cache: Cannot write sync file: ' . $syncFile);
        }
    }
}
