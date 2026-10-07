<?php
/**
 * LỜI CHÚA HÔM NAY - Service Layer
 *
 * Lấy dữ liệu Lời Chúa theo lịch phụng vụ Việt Nam.
 *
 * Nguồn: gospel-data/ndagnhat qua jsDelivr
 * Cache: storage/loichua/{date}.json (ngoài web root)
 *
 * @see docs/superpowers/specs/2026-07-10-loi-chua-hom-nay-design.md
 */

// Pin commit SHA cho gospel-data - tránh breaking changes
define('GOSPEL_DATA_VERSION', 'main');
define('GOSPEL_DATA_CDN', 'https://cdn.jsdelivr.net/gh/ndagnhat/gospel-data@' . GOSPEL_DATA_VERSION);
define('GOSPEL_DATA_TIMEOUT', 5);
define('GOSPEL_DATA_MAX_SIZE', 1024 * 1024); // 1MB

// Múi giờ Việt Nam
define('TZ_HCMC', new DateTimeZone('Asia/Ho_Chi_Minh'));

// Mapping type từ API sang tiếng Việt
const READING_TYPE_MAP = [
    'first_reading' => 'Bài Đọc I',
    'second_reading' => 'Bài Đọc II',
    'psalm' => 'Đáp Ca',
    'gospel' => 'Tin Mừng',
    'reading' => 'Bài Đọc',
];

// Mapping màu áo lễ
const COLOR_MAP = [
    'green' => ['key' => 'xanh', 'label' => 'Màu xanh'],
    'white' => ['key' => 'trang', 'label' => 'Màu trắng'],
    'red' => ['key' => 'đỏ', 'label' => 'Màu đỏ'],
    'purple' => ['key' => 'tím', 'label' => 'Màu tím'],
    'rose' => ['key' => 'hồng', 'label' => 'Màu hồng'],
    'gold' => ['key' => 'vàng', 'label' => 'Màu vàng'],
];

/**
 * Lấy dữ liệu lời chúa cho một ngày
 *
 * @param string $date YYYY-MM-DD
 * @return array|null
 */
function loi_chua_get(string $date): ?array
{
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }

    $parts = explode('-', $date);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    if (!checkdate($month, $day, $year)) {
        return null;
    }

    // Check cache first
    $cached = loi_chua_cache_get($date);
    if ($cached !== null) {
        return $cached;
    }

    // Fetch calendar từ CDN
    $calUrl = sprintf(
        '%s/calendars/lichvn/%d/%02d/%02d.json',
        GOSPEL_DATA_CDN, $year, $month, $day
    );

    $calData = loi_chua_fetch($calUrl);
    if ($calData === null || !isset($calData['readings'])) {
        return null;
    }

    // Build response
    $result = loi_chua_build_response($date, $calData);

    // Cache if valid
    if ($result !== null) {
        loi_chua_cache_set($date, $result);
    }

    return $result;
}

/**
 * Build response từ calendar data
 */
function loi_chua_build_response(string $date, array $calData): ?array
{
    $readings = [];

    foreach ($calData['readings'] as $reading) {
        // Skip suy niệm (no data available)
        $type = $reading['type'] ?? '';
        if ($type === 'reflection' || $type === 'suy_niem') {
            continue;
        }

        $text = loi_chua_fetch_verses($reading);

        // Map type sang tiếng Việt
        $typeLabel = READING_TYPE_MAP[$type] ?? ucfirst($type);

        $readings[] = [
            'type' => $typeLabel,
            'book' => $reading['bookCode'] ?? '',
            'bookName' => $reading['bookName'] ?? '',
            'chapter' => $reading['startChapter'] ?? 0,
            'verses' => $reading['displayReference'] ?? '',
            'text' => $text,
            'ref' => $reading['displayReference'] ?? '',
        ];
    }

    // Map color
    $colorKey = $calData['color'] ?? 'white';
    $colorInfo = COLOR_MAP[$colorKey] ?? ['key' => 'trang', 'label' => 'Màu trắng'];

    return [
        'date' => $date,
        'season' => $calData['seasonName'] ?? $calData['season'] ?? '',
        'week' => $calData['week'] ?? '',
        'color' => $colorInfo['key'],
        'colorLabel' => $colorInfo['label'],
        'readings' => $readings,
        'source' => [
            'calendar' => 'gospel-data/ndagnhat (jsDelivr)',
            'bible' => 'CGKPV 2011',
        ],
    ];
}

/**
 * Fetch verses từ bible
 */
function loi_chua_fetch_verses(array $reading): string
{
    $book = $reading['bookCode'] ?? '';
    $startChapter = $reading['startChapter'] ?? 0;
    $selectedVerses = $reading['selectedVerses'] ?? [];

    if (empty($book) || empty($startChapter)) {
        return '';
    }

    // Fetch chapter
    $url = sprintf('%s/bibles/cgkpv2011/%s/%d.json', GOSPEL_DATA_CDN, $book, $startChapter);
    $data = loi_chua_fetch($url);

    if ($data === null || !isset($data['verses'])) {
        return '';
    }

    // Extract verses from the array
    return loi_chua_extract_verses($data['verses'], $selectedVerses);
}

/**
 * Extract verses từ chapter data theo selectedVerses array
 */
function loi_chua_extract_verses(array $versesData, array $selectedVerses): string
{
    if (empty($selectedVerses)) {
        // Return all verses
        $texts = array_map(fn($v) => $v['text'] ?? '', $versesData);
        return implode(' ', array_filter($texts));
    }

    // Build index by verse number
    $verseIndex = [];
    foreach ($versesData as $v) {
        $num = $v['number'] ?? 0;
        if ($num > 0) {
            $verseIndex[$num] = $v['text'] ?? '';
        }
    }

    // Extract selected verses
    $texts = [];
    foreach ($selectedVerses as $v) {
        if (isset($verseIndex[$v])) {
            $texts[] = $verseIndex[$v];
        }
    }

    return implode(' ', $texts);
}

/**
 * Validate date is within allowed range
 */
function loi_chua_validate_date(string $date): bool
{
    // Check format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode('-', $date);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    // Check valid date
    if (!checkdate($month, $day, $year)) {
        return false;
    }

    // Check range (hôm nay - 30, hôm nay + 7)
    $tz = TZ_HCMC;
    $now = new DateTime('now', $tz);

    $minDate = (clone $now)->modify('-30 days')->format('Y-m-d');
    $maxDate = (clone $now)->modify('+7 days')->format('Y-m-d');

    return ($date >= $minDate && $date <= $maxDate);
}

/**
 * Fetch JSON từ URL
 */
function loi_chua_fetch(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => GOSPEL_DATA_TIMEOUT,
            'ignore_errors' => true,
            'user_agent' => 'GlyphUtTrung/1.0 (LoiChua Service)',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    // Check content length
    if (strlen($response) > GOSPEL_DATA_MAX_SIZE) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}

/**
 * Cache: Get từ file
 */
function loi_chua_cache_get(string $date): ?array
{
    $cacheFile = loi_chua_cache_path($date);

    if (!file_exists($cacheFile)) {
        return null;
    }

    $content = @file_get_contents($cacheFile);
    if ($content === false) {
        return null;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}

/**
 * Cache: Save vào file
 */
function loi_chua_cache_set(string $date, array $data): bool
{
    $cacheFile = loi_chua_cache_path($date);
    $dir = dirname($cacheFile);

    if (!is_dir($dir)) {
        return false;
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tempFile = $cacheFile . '.tmp';

    $result = @file_put_contents($tempFile, $json);
    if ($result === false) {
        return false;
    }

    // Atomic rename
    return @rename($tempFile, $cacheFile);
}

/**
 * Cache: Get file path
 */
function loi_chua_cache_path(string $date): string
{
    return dirname(__DIR__) . '/storage/loichua/' . $date . '.json';
}
