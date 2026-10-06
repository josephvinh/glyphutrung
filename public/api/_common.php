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
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self), payment=()');
    // CSP đặt SONG SONG với .htaccess: máy chủ nào thiếu mod_headers thì
    // tầng PHP vẫn siết. Cho 'unsafe-eval' vì Alpine.js dựng biểu thức bằng
    // AsyncFunction; 'unsafe-inline' vì có <script> nhúng dữ liệu boot.
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data: https: blob:; "
         . "font-src 'self'; connect-src 'self'; "
         . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");
    if (!empty($_SERVER['HTTPS'])) {
        // Tăng từ 180 ngày → 2 năm (63072000s) để trình duyệt luôn dùng HTTPS
        header('Strict-Transport-Security: max-age=63072000');
        // Announce HTTP/3 (QUIC) support — LiteSpeed/Cloudflare sẽ thực sự hỗ trợ
        header('Alt-Svc: h3=":443"; ma=86400');
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

/**
 * Người này có được THẤY vai/tài khoản Quản trị không.
 * Chỉ chính admin mới thấy admin — mọi người khác bị ẩn hoàn toàn khỏi
 * danh sách nhân sự, danh sách vai và ma trận phân quyền (xem data.php và
 * _bootstrap_page.php). Đây là lớp CHE GIẤU (obscurity), không thay cho
 * kiểm soát truy cập: lá chắn thật là whitelist gán vai + kiểm quyền backend.
 */
function can_see_admin(?array $me): bool
{
    return ($me['role_code'] ?? '') === 'admin';
}

/**
 * Được xem nhật ký thao tác toàn hệ thống — tương đương quyền màn Cài đặt
 * (settings.php: chỉ Quản trị). Dùng cho khoá 'logs' của data.php và logs.php.
 */
function can_view_logs(?array $me): bool
{
    return ($me['role_code'] ?? '') === 'admin';
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

/**
 * Tính lại vai gốc + khối/lớp hiển thị (members.role_code/block_id/class_id/
 * title_id) từ các phân công đang hiệu lực. member_assignments là nguồn thật;
 * các cột trên members chỉ là bản dẫn xuất — mọi chỗ đổi phân công (gán/gỡ
 * chủ nhiệm, trưởng khối, thêm/gỡ GLV, xóa lớp/khối) đều gọi hàm này để hai
 * bên không lệch nhau.
 *
 * Quy tắc:
 *  - admin/bdh: vai gốc cố định, không đụng.
 *  - Có phân công (trừ thu_thu — vai phụ trợ): lấy phân công cao nhất
 *    (trưởng khối > chủ nhiệm > GLV > Dự Bị); ngang cấp thì ưu tiên phân công
 *    chính rồi đến mới nhất.
 *  - Hết phân công: người giữ chức (trưởng khối/chủ nhiệm) hạ về GLV; GLV và
 *    Dự Bị giữ nguyên vai, khối/lớp về rỗng.
 *  - Người chưa từng có phân công nào (tài khoản cũ): không đụng.
 */
function recompute_member_primary(int $memberId): void
{
    $m = db_one('SELECT id, role_code, title_id FROM members WHERE id = ?', [$memberId]);
    if (!$m || in_array($m['role_code'], ['admin', 'bdh'], true)) return;

    $rank = ['truong_khoi' => 4, 'glv_chu_nhiem' => 3, 'glv' => 2, 'du_bi' => 1];

    $best = null;
    $rows = db_all(
        "SELECT a.role_code, a.block_id, a.class_id, c.block_id AS class_block_id
           FROM member_assignments a
           LEFT JOIN classes c ON c.id = a.class_id
          WHERE a.member_id = ? AND a.to_date IS NULL AND a.role_code <> 'thu_thu'
          ORDER BY a.is_primary DESC, a.from_date DESC, a.id DESC",
        [$memberId]
    );
    foreach ($rows as $r) {
        $rk = $rank[$r['role_code']] ?? 0;
        if ($rk > 0 && ($best === null || $rk > $rank[$best['role_code']])) $best = $r;
    }

    if (!$best) {
        // Tài khoản cũ chưa từng có phân công (vai + khối/lớp chỉ nằm ở members,
        // xem fallback trong responsible_blocks): để nguyên, đừng hạ vai. Chỉ
        // tính lại khi họ từng có phân công (nay đã hết) — tức đã đi qua Khối & Lớp.
        $hadAny = db_one("SELECT 1 FROM member_assignments
                           WHERE member_id = ? AND role_code <> 'thu_thu' LIMIT 1", [$memberId]);
        if (!$hadAny) return;
    }

    if ($best) {
        $role    = $best['role_code'];
        $classId = $best['class_id'] !== null ? (int) $best['class_id'] : null;
        $blockId = $best['class_block_id'] !== null ? (int) $best['class_block_id']
                 : ($best['block_id'] !== null ? (int) $best['block_id'] : null);
    } else {
        $role    = in_array($m['role_code'], ['glv', 'du_bi'], true) ? $m['role_code'] : 'glv';
        $classId = null;
        $blockId = null;
    }

    $titleId = $m['title_id'];
    if ($role !== $m['role_code'] || $titleId === null
        || !db_one('SELECT 1 FROM titles WHERE id = ? AND role_code = ?', [$titleId, $role])) {
        $t = db_one('SELECT id FROM titles WHERE role_code = ? ORDER BY sort_order LIMIT 1', [$role]);
        $titleId = $t['id'] ?? null;
    }

    db_run('UPDATE members SET role_code=?, title_id=?, block_id=?, class_id=? WHERE id=?',
           [$role, $titleId, $blockId, $classId, $memberId]);
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

/**
 * Thời điểm (unix) CHỐT SỔ của một buổi trong một ngày.
 *
 * Buổi có nhập GIỜ CHỐT riêng (programs.cutoff_time) thì dùng ĐÚNG giờ đó;
 * để trống mới lấy mặc định giờ bắt đầu + cutoff_minutes (toàn cục, 30').
 *
 * PHẢI khớp với giao diện (attendance.js: cutoffOf). Trước đây máy chủ bỏ
 * qua cutoff_time và luôn tính start_time + 30' — buổi đặt giờ chốt muộn
 * hơn (VD bắt đầu 06:00, chốt 08:00) khiến em điểm danh lúc 06:36 — vẫn
 * TRONG giờ quy định — bị ghi "đi trễ".
 */
function program_cutoff_ts(array $prog, string $date): int
{
    $cutoff = trim((string) ($prog['cutoff_time'] ?? ''));
    if ($cutoff !== '') {
        return strtotime($date . ' ' . $cutoff);
    }
    $cutoffMin = (int) app_config('cutoff_minutes');
    return strtotime($date . ' ' . $prog['start_time']) + $cutoffMin * 60;
}

/**
 * Trạng thái ĐÚNG của một bản ghi điểm danh, suy từ thời điểm bấm/quét
 * (marked_at) so với giờ chốt thật của buổi. Dựng lại đúng điều máy chủ
 * (đã vá) quyết định — dùng cho script sửa dữ liệu cũ ghi sai.
 *   $prog cần: start_time, cutoff_time (có thể null).
 */
function attendance_expected_status(string $markedAt, array $prog, string $date): string
{
    return strtotime($markedAt) >= program_cutoff_ts($prog, $date) ? 'đi trễ' : 'có mặt';
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

/**
 * "CỬA SỬA": được phép VƯỢT giờ khoá sổ của buổi (điểm danh bù buổi cũ /
 * ghi đơn phép muộn) cho MỘT lớp hay không.
 *
 * Mặc định mọi người bị "khoá cứng" sau giờ tính vắng — vắng là vắng. Chỉ
 * các vai quản lý được sửa sai, và CHỈ trong phạm vi mình phụ trách:
 *   admin / bdh (toàn đoàn), truong_khoi (khối mình), glv_chu_nhiem (lớp mình).
 * GLV thường vẫn bị khoá → báo cấp trên thay vì tự ý.
 *
 * Đây là lớp nới riêng cho RÀO THỜI GIAN; KHÔNG thay rào phạm vi: nơi gọi
 * vẫn phải qua can_access_class(...,'edit') trước. Vì thế không nới quyền
 * ghi sang lớp ngoài tầm của người dùng.
 */
function can_override_session_lock(array $me, int $classId): bool
{
    static $OVERRIDE = ['admin', 'bdh', 'truong_khoi', 'glv_chu_nhiem'];
    foreach (member_scopes($me) as $a) {
        if (!in_array($a['role_code'] ?? '', $OVERRIDE, true)) continue;
        if (assignment_covers_class($a, $classId)) return true;
    }
    return false;
}

/** Lấy danh sách ID lớp dựa trên phân công (null = toàn đoàn) */
function resolve_class_ids_from_scope(array $a): ?array
{
    switch ($a['role_scope'] ?? '') {
        case 'toàn đoàn':
            return null;
        case 'khối':
            if (!empty($a['block_id'])) {
                return array_column(
                    db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id');
            }
            return [];
        case 'lớp':
            return !empty($a['class_id']) ? [(int) $a['class_id']] : [];
    }
    return [];
}

/** Tập lớp được (need) trên module — null nghĩa là không giới hạn (toàn đoàn) */
function accessible_class_ids(array $me, string $moduleKey, string $need = 'view'): ?array
{
    $needRank = level_rank($need);
    $ids = [];
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        $scopeIds = resolve_class_ids_from_scope($a);
        if ($scopeIds === null) return null;
        $ids = array_merge($ids, $scopeIds);
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

/**
 * Hợp phạm vi cho ranh giới XEM hồ sơ mình phụ trách — chỉ cộng phạm vi của
 * phân công có quyền ≥view trên MỘT trong các module miền thiếu nhi
 * (students hoặc attendance). Vai không có quyền nào ở miền này (vd
 * thu_thu — chỉ có quyền trên rewards) không được cộng phạm vi, để tránh
 * kiêm nhiệm vai đó (thường scope toàn đoàn) mở rộng ranh giới xem hồ sơ.
 */
function responsible_class_ids(array $me): ?array
{
    $ids = [];
    foreach (member_scopes($me) as $a) {
        $roleCode = $a['role_code'] ?? '';
        $hasStudentDomainAccess =
            level_rank(permission_of_role($roleCode, 'students')) >= level_rank('view')
            || level_rank(permission_of_role($roleCode, 'attendance')) >= level_rank('view');
        if (!$hasStudentDomainAccess) continue;

        $scopeIds = resolve_class_ids_from_scope($a);
        if ($scopeIds === null) return null;
        $ids = array_merge($ids, $scopeIds);
    }
    return array_values(array_unique(array_map('intval', $ids)));
}
