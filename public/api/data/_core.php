<?php
/**
 * DATA API — Core Functions
 *
 * Shared utilities cho data API: output formatting, scope filtering,
 * cache key generation.
 *
 * TUỲ CHỌN PAGINATION:
 * - ?page=1&limit=50 : phân trang danh sách thiếu nhi
 * - ?page=all : trả toàn bộ (backward compatible)
 */

/**
 * Trả JSON kèm ETag (băm nội dung). Máy khách gửi lại If-None-Match: nếu dữ
 * liệu không đổi thì trả 304 rỗng — khỏi tải lại vài trăm KB điểm danh mỗi
 * lần mở lại app. Băm trên chính nội dung nên không bao giờ trả bản cũ sai.
 */
function data_out(array $payload): never
{
    // Bản lấy từ cache đã qua json_decode nên {} rỗng thành []; ép về {} (cả hai map
    // này vốn là object ở nguồn) để nội dung (và ETag) không đổi tuỳ trúng/trượt cache.
    foreach (['programClasses', 'stampSummaries'] as $k) {
        if (isset($payload[$k]) && !$payload[$k]) $payload[$k] = (object) [];
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $etag = '"' . md5($body) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, no-cache');
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
}

/**
 * Lớp được XEM dữ liệu module $mod: giao phạm vi hồ sơ P(me) = allowed_class_ids()
 * với phạm vi các phân công có quyền ≥ view trên $mod (accessible_class_ids()).
 * null = toàn đoàn, [] = không gì.
 *
 * Lấy GIAO: (1) không bao giờ gửi dữ liệu của em mà người xem không nhận hồ sơ
 * (client ghép theo studentId, dữ liệu thừa chỉ là rò rỉ — #78); (2) tôn trọng ma
 * trận quyền chỉnh được trong app THEO TỪNG PHÂN CÔNG. Không dùng permission_of()
 * vì hàm đó gộp quyền mọi vai rồi bỏ qua phạm vi (lai phạm vi vai này với quyền vai kia).
 */
function data_scope_for(array $me, string $mod): ?array
{
    static $bases = [];                      // cùng $me gọi cho 3 module: tính P(me) một lần
    $base = $bases[(int) $me['id']] ??= [allowed_class_ids($me)];
    $base = $base[0];
    if ($base === []) return [];
    $m = accessible_class_ids($me, $mod, 'view');
    if ($m === null) return $base;           // base có thể null (toàn đoàn)
    if ($base === null) return $m;
    return array_values(array_intersect($base, $m));
}

/**
 * Mệnh đề lọc theo lớp qua ghi danh năm $yid: trả [sqlJoin, params] để chèn vào
 * FROM của truy vấn có cột em $studentCol; null = không lọc (toàn đoàn).
 * Lọc bằng id LỚP (vài chục phần tử, bind bằng ?) — không liệt kê id em.
 * uq_enr (year_id, student_id): mỗi em đúng một dòng ghi danh/năm nên JOIN không
 * nhân bản dòng. Gọi với $ids === [] là lỗi của nơi gọi (phải trả [] mà không truy vấn).
 */
function data_class_filter(?array $ids, string $studentCol, int $yid): ?array
{
    if ($ids === null) return null;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return [" JOIN enrollments e ON e.student_id = {$studentCol} AND e.year_id = ? AND e.class_id IN ({$ph})",
            array_merge([$yid], array_map('intval', $ids))];
}

/** Các dòng của một khối dữ liệu theo phạm vi: $sql chứa {JOIN} ngay sau bảng chính. */
function data_scoped_rows(?array $ids, string $sql, string $studentCol, int $yid, array $params): array
{
    if ($ids === []) return [];
    $f = data_class_filter($ids, $studentCol, $yid);
    if ($f === null) return db_all(str_replace('{JOIN}', '', $sql), $params);   // SQL cũ, không đổi
    return db_all(str_replace('{JOIN}', $f[0], $sql), array_merge($f[1], $params));
}

// ================================================================
// CONSTANTS
// ================================================================

// Đổi phiên bản khi đổi hình dạng/phạm vi dữ liệu: khoá cache cũ (có thể đang
// chứa dữ liệu rộng hơn phạm vi mới) không bao giờ được đọc lại và tự hết hạn.
const DATA_CACHE_VER = 'v2';

// Pagination config
const DEFAULT_PAGE_LIMIT = 100;
const MAX_PAGE_LIMIT = 500;
