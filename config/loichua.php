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

// Các sách Kinh Thánh cần fetch nhiều chương
const MULTI_CHAPTER_BOOKS = ['Gl', '2 Sm', '1 V', '2 V', '1 Cr', '2 Cr', 'Ed', 'Nê', '1 Mac', '2 Mac'];

// Mapping tên sách Việt sang mã repo
const BOOK_CODE_MAP = [
    'Sáng Thế' => 'Gn', 'Xuất Ê-díp-tô Ký' => 'Ex', 'Lê-vi' => 'Lv',
    'Dân Số' => 'Nm', 'Đệ Nhị Luật' => 'Dt', 'Giô-suê' => 'Jos',
    'Các Thủ Lãnh' => 'Jdg', 'Ru-tơ' => 'Rt', '1 Sa-mu-en' => '1 Sm',
    '2 Sa-mu-en' => '2 Sm', '1 Các Vua' => '1 Kgs', '2 Các Vua' => '2 Kgs',
    '1 Sử Ký' => '1 Chr', '2 Sử Ký' => '2 Chr', 'É-ra' => 'Ezr',
    'Nê-hê-mi-a' => 'Neh', 'Tô-bít' => 'Tb', 'Giu-đích' => 'Jdt',
    'Ê-xê-tê' => 'Est', 'Giób' => 'Job', 'Thánh Vịnh' => 'Ps',
    'Châm Ngôn' => 'Prv', 'Giáo Lý' => 'Eccl', 'Nhã Ca' => 'Sg',
    'Khôn Ngoan' => 'Wis', 'Hô-sê-a' => 'Hos', 'Áp-đam' => 'Amos',
    'Mít-da' => 'Mic', 'Giô-en' => 'Joel', 'Áp-đi-a' => 'Obad',
    'Giô-na' => 'Jonah', 'Mích-a' => 'Mic', 'Na-chum' => 'Nah',
    'Ha-ba-cúc' => 'Hab', 'Sô-phô-ni-a' => 'Zeph', 'Aggai' => 'Hag',
    'Da-ca-ri-a' => 'Zech', 'Ma-la-chi' => 'Mal', '1 Ma-ca-bê' => '1 Mac',
    '2 Ma-ca-bê' => '2 Mac', 'Ma-thi-ô' => 'Mt', 'Mác-cô' => 'Mk',
    'Luca' => 'Lk', 'Gio-an' => 'Jn', 'Tông Đồ Công Vụ' => 'Acts',
    'Rô-ma' => 'Rom', '1 Cô-rinh-tô' => '1 Cor', '2 Cô-rinh-tô' => '2 Cor',
    'Ga-la-ti' => 'Gal', 'Ê-phê-sô' => 'Eph', 'Phi-líp' => 'Phil',
    'Cô-lô-sê' => 'Col', '1 Thê-sa-lô-ni-ca' => '1 Thess',
    '2 Thê-sa-lô-ni-ca' => '2 Thess', '1 Ti-mô-thê' => '1 Tim',
    '2 Ti-mô-thê' => '2 Tim', 'Tít' => 'Titus', 'Phi-lê-môn' => 'Phlm',
    'Hê-bơ-rơ' => 'Heb', 'Gia-cô-bê' => 'Jas', '1 Phêrô' => '1 Pet',
    '2 Phêrô' => '2 Pet', '1 Gio-an' => '1 Jn', '2 Gio-an' => '2 Jn',
    '3 Gio-an' => '3 Jn', 'Giu-đa' => 'Jude', 'Khải Huyền' => 'Rev',
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

    // Fetch from CDN
    $calUrl = sprintf(
        '%s/calendars/lichvn/%d/%02d/%02d.json',
        GOSPEL_DATA_CDN, $year, $month, $day
    );

    $calData = loi_chua_fetch($calUrl);
    if ($calData === null) {
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
    if (empty($calData['readings']) || !is_array($calData['readings'])) {
        return null;
    }

    $readings = [];

    foreach ($calData['readings'] as $reading) {
        // Skip suy niệm (no data available)
        if (($reading['type'] ?? '') === 'Suy niệm') {
            continue;
        }

        $text = loi_chua_fetch_verses($reading);
        if ($text === null) {
            // If verse fetch fails, use reference only
            $text = '';
        }

        $readings[] = [
            'type' => $reading['type'] ?? '',
            'book' => $reading['book'] ?? '',
            'bookName' => $reading['bookName'] ?? '',
            'chapter' => $reading['chapter'] ?? 0,
            'verses' => $reading['selectedVerses'] ?? $reading['verses'] ?? '',
            'text' => $text,
            'ref' => loi_chua_format_ref($reading),
        ];
    }

    return [
        'date' => $date,
        'season' => $calData['season'] ?? '',
        'week' => $calData['week'] ?? '',
        'color' => $calData['color'] ?? 'trang',
        'colorLabel' => loi_chua_color_label($calData['color'] ?? 'trang'),
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
function loi_chua_fetch_verses(array $reading): ?string
{
    $book = $reading['book'] ?? '';
    $chapter = $reading['chapter'] ?? 0;
    $selectedVerses = $reading['selectedVerses'] ?? $reading['verses'] ?? '';

    if (empty($book) || empty($chapter)) {
        return null;
    }

    // Fetch chapter
    $url = sprintf('%s/bibles/cgkpv2011/%s/%d.json', GOSPEL_DATA_CDN, $book, $chapter);
    $data = loi_chua_fetch($url);

    if ($data === null || !is_array($data)) {
        return null;
    }

    // Parse selected verses (e.g., "1-11" or "1-2,7-14")
    $text = loi_chua_extract_verses($data, $selectedVerses);

    return $text;
}

/**
 * Extract verses từ chapter data theo selectedVerses pattern
 */
function loi_chua_extract_verses(array $chapterData, string $selectedVerses): string
{
    if (empty($selectedVerses)) {
        // Return all verses
        return implode(' ', array_values($chapterData));
    }

    // Parse "1-11" or "1-2,7-14,15-18" format
    $verses = [];
    $parts = explode(',', $selectedVerses);

    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, '-') !== false) {
            // Range: "1-11"
            [$start, $end] = explode('-', $part, 2);
            $start = (int)trim($start);
            $end = (int)trim($end);

            for ($v = $start; $v <= $end; $v++) {
                if (isset($chapterData[$v])) {
                    $verses[] = $chapterData[$v];
                }
            }
        } else {
            // Single verse
            $v = (int)trim($part);
            if (isset($chapterData[$v])) {
                $verses[] = $chapterData[$v];
            }
        }
    }

    return implode(' ', $verses);
}

/**
 * Format reference string
 */
function loi_chua_format_ref(array $reading): string
{
    $book = $reading['book'] ?? '';
    $chapter = $reading['chapter'] ?? 0;
    $verses = $reading['selectedVerses'] ?? $reading['verses'] ?? '';

    if (empty($book)) {
        return '';
    }

    $ref = $book . ' ' . $chapter;
    if (!empty($verses)) {
        $ref .= ':' . str_replace(' ', '', $verses);
    }

    return $ref;
}

/**
 * Get color label
 */
function loi_chua_color_label(string $color): string
{
    $labels = [
        'trang' => 'Màu trắng',
        'xanh' => 'Màu xanh',
        'đỏ' => 'Màu đỏ',
        'tím' => 'Màu tím',
        'hồng' => 'Màu hồng',
    ];

    return $labels[$color] ?? 'Màu trắng';
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
    $today = $now->format('Y-m-d');

    $minDate = $now->modify('-30 days')->format('Y-m-d');
    $maxDate = (new DateTime('now', $tz))->modify('+7 days')->format('Y-m-d');

    return ($date >= $minDate && $date <= $maxDate);
}
