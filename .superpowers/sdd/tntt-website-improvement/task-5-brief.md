# Task 5 Brief: Performance - Caching Layer

## Task Description
Add a simple file-based caching layer to improve API response times for frequently accessed data.

## Files to Create/Modify

### Create: `public/api/cache.php`
```php
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
    }
}
```

### Modify: `public/api/data.php`
Add caching around the main data query:

```php
// Check cache first
$cacheKey = "data_{$yid}_{$me['id']}";
if ($cached = Cache::get($cacheKey)) {
    json_out($cached);
}

// ... existing code ...

// Cache result
Cache::set($cacheKey, $result, 300); // 5 minutes
json_out($result);
```

### Modify: `public/api/_bootstrap.php`
Include the cache class:

```php
require_once __DIR__ . '/cache.php';
```

## Requirements
- File-based cache stored in `public/cache/` directory
- Cache entries expire after TTL (default 5 minutes)
- Cache is invalidated when underlying data changes
- Safe to run in XAMPP environment

## Acceptance Criteria
1. Cache class implements get/set/del/flush methods
2. Cache is checked before executing expensive queries
3. Cache is populated after successful queries
4. Cache is invalidated when data changes
5. Cache directory is created automatically if missing

## Notes
- This is a simple implementation suitable for XAMPP
- For production, consider Redis or Memcached
- Cache key should include year_id and member_id for user-specific data
