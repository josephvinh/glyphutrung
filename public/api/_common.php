<?php
/**
 * NỀN DÙNG CHUNG cho cả endpoint JSON lẫn trang HTML.
 *
 * Trước đây phần mở phiên, tiêu đề bảo mật và current_member() bị chép
 * ra hai bản trong _bootstrap.php và _bootstrap_page.php. Hai bản đã
 * lệch nhau (một bản trả $me, bản kia trả $me ?: null) — cùng một tên
 * hàm mà hành xử khác nhau tuỳ file gọi. Gộp về đây để chỉ còn một bản.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/student_code.php';

/* ---------- Phiên đăng nhập ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Bật secure khi chạy HTTPS trên AZDIGI
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* ---------- Tiêu đề bảo mật ----------
   Đặt ở tầng PHP để không lệ thuộc mod_headers của máy chủ. */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    // CSP đặt SONG SONG với .htaccess: máy chủ nào thiếu mod_headers thì
    // tầng PHP vẫn siết. Cho 'unsafe-eval' vì Alpine.js dựng biểu thức bằng
    // AsyncFunction; 'unsafe-inline' vì có <script> nhúng dữ liệu boot.
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data: https: blob:; "
         . "font-src 'self'; connect-src 'self'; "
         . "base-uri 'self'; form-action 'self'; frame-ancestors 'none';");
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/** Người đang đăng nhập, hoặc null. Kết quả nhớ lại trong một request. */
function current_member(): ?array
{
    if (empty($_SESSION['member_id'])) return null;

    static $me = null;
    if ($me !== null) return $me;

    $me = db_one(
        'SELECT m.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                t.label AS title_label, b.name AS block_name, c.name AS class_name
           FROM members m
           JOIN roles r ON r.code = m.role_code
           LEFT JOIN titles t ON t.id = m.title_id
           LEFT JOIN blocks b ON b.id = m.block_id
           LEFT JOIN classes c ON c.id = m.class_id
          WHERE m.id = ?',
        [$_SESSION['member_id']]
    );
    return $me ?: null;
}

/* ============================================================================
   KIÊM NHIỆM — truy vấn bảng member_assignments

   Một thành viên có thể giữ nhiều vai trò và phụ trách nhiều lớp/khối cùng
   lúc. Hàm dưới trả về các phân công ĐANG HIỆU LỰC (to_date IS NULL).
   ========================================================================== */

/** Tất cả phân công đang active của một thành viên */
function effective_assignments(int $memberId): array
{
    return db_all(
        "SELECT a.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                b.name AS block_name, c.name AS class_name
           FROM member_assignments a
           JOIN roles r ON r.code = a.role_code
           LEFT JOIN blocks b ON b.id = a.block_id
           LEFT JOIN classes c ON c.id = a.class_id
          WHERE a.member_id = ? AND a.to_date IS NULL
          ORDER BY a.is_primary DESC, a.from_date DESC",
        [$memberId]
    );
}

/** Có đang giữ vai trò X không (active) */
function has_active_role(int $memberId, string $roleCode): bool
{
    $row = db_one(
        "SELECT 1 FROM member_assignments
          WHERE member_id = ? AND role_code = ? AND to_date IS NULL
          LIMIT 1",
        [$memberId, $roleCode]
    );
    return $row !== null;
}

/** Khi đánh dấu 1 assignment là primary, gỡ primary của các assignment khác */
function enforce_single_primary(int $memberId, int $primaryAssignmentId): void
{
    db_run(
        "UPDATE member_assignments
            SET is_primary = (id = ?)
          WHERE member_id = ? AND to_date IS NULL",
        [$primaryAssignmentId, $memberId]
    );
}

/** Lấy phân công CHÍNH (primary) của thành viên — dùng cho permission mặc định */
function primary_assignment(int $memberId): ?array
{
    return db_one(
        "SELECT a.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                b.name AS block_name, c.name AS class_name
           FROM member_assignments a
           JOIN roles r ON r.code = a.role_code
           LEFT JOIN blocks b ON b.id = a.block_id
           LEFT JOIN classes c ON c.id = a.class_id
          WHERE a.member_id = ? AND a.to_date IS NULL AND a.is_primary = 1
          LIMIT 1",
        [$memberId]
    );
}

/* ============================================================================
   QUYỀN THEO TỪNG PHÂN CÔNG (per-assignment)

   Nguyên tắc: KHÔNG lấy max cấp-quyền của mọi vai trò rồi ghép với hợp
   phạm-vi của mọi vai trò — làm vậy sẽ "lai" thành quyền không vai trò nào
   thực có (leo thang). Thay vào đó, mỗi dòng phân công được xét như một đơn
   vị: chỉ khi CÙNG một dòng vừa đủ cấp-quyền trên module vừa phủ được lớp
   thì mới cho phép.
   ========================================================================== */

/** Thứ hạng cấp quyền để so sánh: none < view < edit */
function level_rank(string $level): int
{
    return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
}

/** Cấp quyền của MỘT vai trò trên MỘT module */
function permission_of_role(string $roleCode, string $moduleKey): string
{
    $row = db_one(
        'SELECT level FROM permissions WHERE module_key = ? AND role_code = ?',
        [$moduleKey, $roleCode]
    );
    return $row['level'] ?? 'none';
}

/** Một dòng phân công (có role_scope/block_id/class_id) có phủ lớp này không */
function assignment_covers_class(array $a, int $classId): bool
{
    switch ($a['role_scope'] ?? '') {
        case 'toàn đoàn':
            return true;
        case 'khối':
            if (empty($a['block_id'])) return false;
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [$classId]);
            return $c && (int) $c['block_id'] === (int) $a['block_id'];
        case 'lớp':
            return !empty($a['class_id']) && (int) $a['class_id'] === $classId;
    }
    return false;
}

/** Các phân công active; nếu chưa có thì suy từ members (tương thích ngược) */
function member_scopes(array $me): array
{
    $rows = effective_assignments((int) $me['id']);
    if (!empty($rows)) return $rows;

    return [[
        'role_code'  => $me['role_code'],
        'role_scope' => $me['role_scope'] ?? 'lớp',
        'block_id'   => $me['block_id'] ?? null,
        'class_id'   => $me['class_id'] ?? null,
    ]];
}

/** Có được thao tác (need) trên module cho MỘT lớp cụ thể không — chống leo thang */
function can_access_class(array $me, string $moduleKey, int $classId, string $need = 'view'): bool
{
    $needRank = level_rank($need);
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        if (assignment_covers_class($a, $classId)) return true;
    }
    return false;
}

/** Tập lớp được (need) trên module — null nghĩa là không giới hạn (toàn đoàn) */
function accessible_class_ids(array $me, string $moduleKey, string $need = 'view'): ?array
{
    $needRank = level_rank($need);
    $ids = [];
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        switch ($a['role_scope'] ?? '') {
            case 'toàn đoàn':
                return null;
            case 'khối':
                if (!empty($a['block_id'])) {
                    $ids = array_merge($ids, array_column(
                        db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id'));
                }
                break;
            case 'lớp':
                if (!empty($a['class_id'])) $ids[] = (int) $a['class_id'];
                break;
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

/** Hợp phạm vi THUẦN (không xét module) — dùng cho ranh giới XEM hồ sơ mình phụ trách */
function responsible_class_ids(array $me): ?array
{
    $ids = [];
    foreach (member_scopes($me) as $a) {
        switch ($a['role_scope'] ?? '') {
            case 'toàn đoàn':
                return null;
            case 'khối':
                if (!empty($a['block_id'])) {
                    $ids = array_merge($ids, array_column(
                        db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id'));
                }
                break;
            case 'lớp':
                if (!empty($a['class_id'])) $ids[] = (int) $a['class_id'];
                break;
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}
