<?php
/**
 * Simple file-based caching for TNTT API
 */

class Cache {
    private static string $dir = __DIR__ . '/../cache';

    public static function init(): void {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
    }

    public static function get(string $key): ?array {
        $file = self::$dir . '/' . md5($key) . '.json';
        if (!file_exists($file)) return null;

        $data = json_decode(file_get_contents($file), true);
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }
        return $data['value'];
    }

    public static function set(string $key, $value, int $ttl = 300): void {
        self::init();
        $file = self::$dir . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode([
            'value' => $value,
            'expires' => time() + $ttl,
        ]));
    }

    public static function del(string $key): void {
        $file = self::$dir . '/' . md5($key) . '.json';
        if (file_exists($file)) unlink($file);
    }

    public static function flush(): void {
        foreach (glob(self::$dir . '/*.json') as $file) {
            unlink($file);
        }
        // Ghi lại mốc thời gian để client polling phát hiện thay đổi
        self::init();
        file_put_contents(self::$dir . '/sync.txt', time());
    }
}
