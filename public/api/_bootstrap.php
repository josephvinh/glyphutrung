<?php
/**
 * NỀN CHUNG CHO MỌI ENDPOINT
 *
 * Nạp cấu hình, mở phiên, và cung cấp các hàm trả lời JSON + kiểm tra
 * quyền. Mọi file trong api/ đều bắt đầu bằng require file này.
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cache.php';

header('Content-Type: application/json; charset=utf-8');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

/** Trả JSON rồi dừng */
function json_out($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $message, int $code = 400): never
{
    json_out(['ok' => false, 'error' => $message], $code);
}

/** Đọc thân request dạng JSON, quay về $_POST nếu gửi kiểu form */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST;
}


/** Chặn cửa: mọi endpoint nghiệp vụ đều gọi hàm này trước tiên */
function require_login(): array
{
    $me = current_member();
    if (!$me) json_fail('Chưa đăng nhập.', 401);
    if ($me['status'] === 'đã nghỉ') json_fail('Tài khoản đã ngưng hoạt động.', 403);
    return $me;
}

/** Mức quyền của tài khoản hiện tại với một module: none | view | edit */
function permission_of(string $moduleKey): string
{
    $me = current_member();
    if (!$me) return 'none';

    // Lấy tất cả active roles (qua assignments)
    $assignments = effective_assignments((int) $me['id']);
    $activeRoles = array_column($assignments, 'role_code');

    // Fallback về role_code trong members nếu assignments rỗng (edge case migration)
    if (empty($activeRoles)) {
        $activeRoles = [$me['role_code']];
    }

    $ph = implode(',', array_fill(0, count($activeRoles), '?'));
    $row = db_one(
        "SELECT MAX(level) AS max_level FROM permissions
          WHERE module_key = ? AND role_code IN ($ph)",
        array_merge([$moduleKey], $activeRoles)
    );
    return $row['max_level'] ?? 'none';
}

function require_permission(string $moduleKey, string $need = 'view'): array
{
    $me = require_login();
    $have = permission_of($moduleKey);

    if ($have === 'none') json_fail('Bạn không có quyền truy cập chức năng này.', 403);
    if ($need === 'edit' && $have !== 'edit') json_fail('Bạn chỉ được xem, không được thay đổi.', 403);

    // Module đang bảo trì thì chặn tất cả trừ Quản trị
    $mod = db_one('SELECT is_enabled, label FROM modules WHERE module_key = ?', [$moduleKey]);
    if ($mod && !$mod['is_enabled'] && $me['role_code'] !== 'admin') {
        json_fail('Chức năng "' . $mod['label'] . '" đang tạm bảo trì.', 503);
    }
    return $me;
}

/** Niên khoá đang mở */
function current_year(): ?array
{
    static $year = null;
    if ($year !== null) return $year;
    $year = db_one('SELECT * FROM school_years WHERE is_current = 1 LIMIT 1');
    return $year;
}

/** Ghi nhật ký thao tác */
function log_action(string $action, string $module, string $what, string $detail = ''): void
{
    $me = current_member();
    db_run('INSERT INTO activity_logs (actor_id, actor_name, action, module, what, detail)
            VALUES (?,?,?,?,?,?)',
        [$me['id'] ?? null, $me['full_name'] ?? 'Hệ thống', $action, $module, $what, $detail]);
}

/* ================================================================
   CHỐNG DÒ MẬT KHẨU
   Đếm số lần sai theo số điện thoại VÀ theo địa chỉ IP.
   - theo số:  chặn kẻ nhắm vào một tài khoản cụ thể
   - theo IP:  chặn kẻ rải qua nhiều số điện thoại
   ================================================================ */

const DN_CUA_SO_PHUT = 15;   // khoảng thời gian xét
const DN_TOI_DA_SO   = 5;    // số lần sai tối đa cho một số điện thoại
const DN_TOI_DA_IP   = 20;   // số lần sai tối đa cho một IP

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Chặn trước khi kiểm mật khẩu. Hết lượt thì dừng luôn tại đây. */
function login_throttle(string $phone): void
{
    $moc = date('Y-m-d H:i:s', time() - DN_CUA_SO_PHUT * 60);

    $theoSo = (int) db_one('SELECT COUNT(*) n FROM login_attempts
                             WHERE phone = ? AND tried_at > ?', [$phone, $moc])['n'];
    $theoIp = (int) db_one('SELECT COUNT(*) n FROM login_attempts
                             WHERE ip = ? AND tried_at > ?', [client_ip(), $moc])['n'];

    if ($theoSo >= DN_TOI_DA_SO || $theoIp >= DN_TOI_DA_IP) {
        json_fail('Bạn đã nhập sai quá nhiều lần. '
                . 'Vui lòng đợi ' . DN_CUA_SO_PHUT . ' phút rồi thử lại, '
                . 'hoặc nhờ Ban Điều Hành cấp lại mật khẩu.', 429);
    }
}

/** Ghi một lần sai. Nhân tiện dọn các bản ghi đã quá cũ. */
function login_failed(string $phone): void
{
    db_run('INSERT INTO login_attempts (phone, ip, tried_at) VALUES (?,?,NOW())',
           [$phone, client_ip()]);
    // Dọn rác: chỉ giữ lại phần còn trong cửa sổ xét
    db_run('DELETE FROM login_attempts WHERE tried_at < ?',
           [date('Y-m-d H:i:s', time() - DN_CUA_SO_PHUT * 60)]);
    // Làm chậm mọi lần thử, kể cả khi chưa chạm ngưỡng
    usleep(300000);
}

/** Đăng nhập đúng thì xoá lịch sử sai của số đó. */
function login_ok(string $phone): void
{
    db_run('DELETE FROM login_attempts WHERE phone = ?', [$phone]);
}

/**
 * Bắt buộc dùng POST cho mọi hành động làm thay đổi dữ liệu.
 *
 * $action lấy từ query string, nên nếu không chặn thì một đường link
 * bình thường cũng chạy được hành động ghi: người quản trị đang đăng
 * nhập chỉ cần bấm vào là dữ liệu đổi (SameSite=Lax vẫn gửi cookie khi
 * điều hướng bằng GET). Ép POST là chặn đứng đường đó.
 */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_fail('Hành động này phải gửi bằng POST.', 405);
    }
}

/**
 * Thông điệp lỗi cho client.
 * Trên máy chủ thật, không đưa nội dung lỗi gốc của CSDL ra ngoài —
 * nó lộ tên bảng, tên cột và cấu trúc ràng buộc.
 */
function safe_error(Throwable $e, string $chung): string
{
    if (app_config('production')) {
        error_log('[TNTT] ' . $e->getMessage());   // vẫn ghi lại để còn dò
        return $chung;
    }
    return $chung . ' ' . $e->getMessage();
}

/* ================================================================
   CSRF PROTECTION
   ================================================================ */

/** Bắt buộc CSRF token cho mọi POST request (chỉ khi session đã có token) */
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    // Chỉ verify nếu session đã có CSRF token (đã đăng nhập)
    if (!isset($_SESSION['csrf_token'])) return;
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        json_fail('Invalid CSRF token.', 403);
    }
}

/* ================================================================
   PHẠM VI DỮ LIỆU

   Có HAI phạm vi khác nhau, cố ý không gộp:

     allowed_class_ids()  — XEM và SỬA hồ sơ thiếu nhi.
                            GLV chỉ lớp mình. Đây là ranh giới riêng tư:
                            hồ sơ có ngày sinh, địa chỉ, số điện thoại
                            cha mẹ, không có lý do để GLV lớp này đọc
                            hồ sơ em lớp khác.

     scan_class_ids()     — QUÉT ĐIỂM DANH. Cả khối.
                            Lúc điểm danh các em xếp hàng theo khối, ai
                            trong khối cũng phải quét được mọi em ở đó.
                            Dữ liệu dùng cho việc này chỉ gồm mã số, tên
                            và lớp — không kèm thông tin riêng tư nào.

   Cả hai trả null nghĩa là không giới hạn.
   ================================================================ */
function allowed_class_ids(array $me): ?array
{
    $scope = $me['role_scope'] ?? 'lớp';

    if ($scope === 'toàn đoàn') return null;             // không giới hạn

    if ($scope === 'khối') {
        if (empty($me['block_id'])) return [];            // chưa phân khối thì không ghi được gì
        return array_map('intval', array_column(
            db_all('SELECT id FROM classes WHERE block_id = ?', [$me['block_id']]), 'id'));
    }

    // phạm vi lớp
    return empty($me['class_id']) ? [] : [(int) $me['class_id']];
}

/** Tên các lớp được phép, để ghi vào thông báo lỗi cho dễ hiểu */
function allowed_class_names(?array $ids): string
{
    if ($ids === null) return 'mọi lớp';
    if (!$ids)         return '(chưa được phân công lớp nào)';
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return implode(', ', array_column(
        db_all("SELECT name FROM classes WHERE id IN ($ph) ORDER BY name", $ids), 'name'));
}

/**
 * Các lớp mà người này được QUÉT điểm danh — tính theo KHỐI.
 * Chưa gắn khối thì suy từ lớp được phân công.
 */
function scan_class_ids(array $me): ?array
{
    if (in_array($me['role_code'] ?? '', ['admin', 'bdh'], true)) return null;

    $blockId = (int) ($me['block_id'] ?? 0);
    if (!$blockId && !empty($me['class_id'])) {
        $c = db_one('SELECT block_id FROM classes WHERE id = ?', [(int) $me['class_id']]);
        $blockId = (int) ($c['block_id'] ?? 0);
    }
    if (!$blockId) return [];

    return array_map('intval', array_column(
        db_all('SELECT id FROM classes WHERE block_id = ?', [$blockId]), 'id'));
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
