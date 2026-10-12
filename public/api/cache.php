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

    /** Xoá mọi tệp cache dữ liệu (không đụng tới các mốc đồng bộ). */
    private static function purge(): void {
        foreach (glob(self::$dir . '/*.json') as $file) {
            @unlink($file);
        }
    }

    private static function writeMarker(string $name, string $value): void {
        $file = self::$dir . '/' . $name;
        if (@file_put_contents($file, $value) === false) {
            error_log('Cache: Cannot write sync file: ' . $file);
        }
    }

    /** Đọc một mốc đồng bộ ('sync.txt' hoặc 'sync_att.txt'); chưa có thì '0'. */
    public static function marker(string $name): string {
        self::init();
        $v = @file_get_contents(self::$dir . '/' . $name);
        return ($v === false || $v === '') ? '0' : trim($v);
    }

    /** Mọi thứ đổi: xoá cache + đổi mốc CHUNG để mọi máy nạp lại toàn bộ. */
    public static function flush(): void {
        self::init();
        self::purge();
        // Ghi lại mốc thời gian để client polling phát hiện thay đổi
        self::writeMarker('sync.txt', (string) time());
    }

    /**
     * Chỉ ĐIỂM DANH đổi: xoá cache dữ liệu và đổi mốc RIÊNG của điểm danh
     * (sync_att.txt, đơn vị mili-giây, luôn tăng) để các máy khác chỉ nạp lại
     * phần điểm danh/điểm (data.php?part=heavy) thay vì cả gói.
     *
     * $anhHuongCore = true khi bản ghi còn làm đổi dữ liệu ở phần "core" (Sổ
     * Mộc: ví/chuỗi trong stampSummaries) -> đổi luôn mốc chung như flush().
     *
     * Trả về mốc điểm danh mới để máy vừa ghi nhận ra thay đổi của chính nó và
     * không nạp lại vô ích.
     */
    public static function flushAttendance(bool $anhHuongCore = false): string {
        self::init();
        self::purge();
        $moi = (string) max((int) round(microtime(true) * 1000), (int) self::marker('sync_att.txt') + 1);
        self::writeMarker('sync_att.txt', $moi);
        if ($anhHuongCore) self::writeMarker('sync.txt', (string) time());
        return $moi;
    }
}
