<?php
/**
 * NỀN CHUNG CHO MỌI ENDPOINT
 *
 * Nạp cấu hình, mở phiên, và cung cấp các hàm trả lời JSON + kiểm tra
 * quyền. Mọi file trong api/ đều bắt đầu bằng require file này.
 */

// Lúc nào cũng dùng giờ Việt Nam, không phụ thuộc cấu hình máy chủ.
// Nếu server ở Châu Âu, strtotime() vẫn phải hiểu start_time = 07:30 là 7h30 sáng VN.
date_default_timezone_set('Asia/Ho_Chi_Minh');

// API LUÔN trả JSON. Tuyệt đối không để warning/notice/deprecation của PHP
// lọt vào thân phản hồi: trên iOS/WebKit, res.json() sẽ ném SyntaxError
// "The string did not match the expected pattern." (Chrome nói "Unexpected
// token"). Thư viện lbuchs (WebAuthn) trên PHP 8.2 hay sinh deprecation —
// đủ một dòng là hỏng cả JSON. Ẩn hiển thị, vẫn ghi vào error_log để dò.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cache.php';

// Rate Limiting - giới hạn số request
require_once __DIR__ . '/../../src/RateLimiter.php';

// Error Logging - PSR-3 compatible logger
require_once __DIR__ . '/../../src/Logger.php';
require_once __DIR__ . '/../../src/ExceptionHandler.php';
setup_exception_handler();

// Nén phản hồi khi trình duyệt hỗ trợ. data.php có thể tới vài MB (điểm danh
// cả đoàn); JSON nén gzip giảm ~10 lần → mạng di động đỡ hẳn. Bọc buffer TRƯỚC
// khi in bất kỳ thứ gì. Bỏ qua nếu server đã tự nén (zlib.output_compression).
if (extension_loaded('zlib')
    && !ini_get('zlib.output_compression')
    && stripos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    @ob_start('ob_gzhandler');
}

/* ================================================================
   BROTLI COMPRESSION — nén mạnh hơn gzip ~20%
   Apache: cần mod_brotli + .htaccess
   Nginx: cần ngx_http_brotli_filter_module
   Fallback: tự nén bằng brotli extension nếu có
   ================================================================ */
if (extension_loaded('brotli')
    && !ini_get('zlib.output_compression')
    && stripos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'br') !== false) {
    // Nén brotli level 5 (cân bằng tốc độ/nén), buffer trước ob_gzhandler
    // Dùng giá trị số 0 thay vì constant để tránh lỗi undefined constant trên một số host
    @ob_start(function($buffer) {
        return brotli_compress($buffer, 0, 5); // 0 = BROTLI_GENERIC
    });
    header('Content-Encoding: br');
}

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

/** Trả JSON thành công: {ok:true, ...$data} */
function json_success(array $data = [], int $code = 200): never
{
    json_out(array_merge(['ok' => true], $data), $code);
}

/** Đọc thân request dạng JSON, quay về $_POST nếu gửi kiểu form */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    // Bảo vệ DoS: giới hạn kích thước request body
    $maxSize = 1 * 1024 * 1024; // 1MB
    if (strlen($raw) > $maxSize) {
        json_fail('Request body too large (max 1MB).', 413);
    }
    $data = json_decode($raw, true);
    if (is_array($data)) return $data;
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

/**
 * Mức quyền của tài khoản hiện tại với một module: none | view | edit.
 *
 * ⚠️ CHỈ là CỔNG THEO MODULE — KHÔNG xét phạm vi lớp/khối. Hàm này lấy HỢP
 * mức-quyền cao nhất của vai gốc + mọi vai kiêm nhiệm, bỏ qua scope. Vì vậy
 * MỌI thao tác theo-đối-tượng (một em/một lớp/một khối cụ thể) BẮT BUỘC gọi
 * thêm can_access_class() / can_manage_class() / can_manage_block() sau khi
 * đã qua require_permission(). Bỏ bước kiểm phạm vi = leo thang chiều ngang
 * (xem lỗi F1 trong docs/BAO_CAO_PHAN_QUYEN_THANH_VIEN.md).
 */
function permission_of(string $moduleKey): string
{
    $me = current_member();
    if (!$me) return 'none';

    // Quyền = HỢP của vai trò GỐC (members.role_code) + mọi vai kiêm nhiệm.
    // Kiêm nhiệm chỉ THÊM quyền, KHÔNG hạ vai gốc: một Quản trị/BĐH tự thêm
    // mình vào một lớp (thành GLV) vẫn phải giữ nguyên quyền gốc. Nếu chỉ lấy
    // theo assignments, họ bị coi là GLV và tự khoá mình khỏi màn Khối & Lớp
    // (frontend dùng role gốc nên vẫn hiện nút, backend lại chặn — lệch nhau).
    $assignments = effective_assignments((int) $me['id']);
    $activeRoles = array_column($assignments, 'role_code');
    $activeRoles[] = $me['role_code'];
    $activeRoles = array_values(array_unique(array_filter($activeRoles)));
    if (empty($activeRoles)) return 'none';

    // Lấy mọi mức quyền của các vai rồi chọn cao nhất THEO HẠNG
    // (none < view < edit). KHÔNG dùng SQL MAX(level): level là chuỗi nên so
    // sánh chữ cái ra 'view' > 'edit' (sai) — người có cả vai edit lẫn view
    // (VD Quản trị tự thêm mình vào một lớp = admin + glv) bị tụt xuống 'view'.
    $ph = implode(',', array_fill(0, count($activeRoles), '?'));
    $rows = db_all(
        "SELECT level FROM permissions WHERE module_key = ? AND role_code IN ($ph)",
        array_merge([$moduleKey], $activeRoles)
    );
    $hang = ['none' => 0, 'view' => 1, 'edit' => 2];
    $tot = 'none';
    foreach ($rows as $r) {
        if (($hang[$r['level']] ?? 0) > ($hang[$tot] ?? 0)) $tot = $r['level'];
    }
    return $tot;
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
    // Làm chậm với jitter để tránh timing attack
    usleep(200000 + random_int(0, 200000)); // 200-400ms
}

/** Đăng nhập đúng thì xoá lịch sử sai của số đó, CHỈ login thường */
function login_ok(string $phone): void
{
    db_run('DELETE FROM login_attempts WHERE phone = ? AND phone NOT LIKE ?', [$phone, 'pk:%']);
}

/* ================================================================
   CHỐNG ĐĂNG KÝ SPAM
   Giới hạn số lần đăng ký từ cùng một IP trong khoảng thời gian.
   ================================================================ */

/** Giới hạn đăng ký: tối đa 3 lần/giờ/IP */
function register_throttle(): void
{
    $ip = client_ip();
    $moc = date('Y-m-d H:i:s', time() - 3600); // 1 giờ

    // Kiểm tra trong bảng login_attempts với prefix 'reg:'
    $theoIp = (int) db_one(
        'SELECT COUNT(*) n FROM login_attempts
         WHERE phone LIKE ? AND ip = ? AND tried_at > ?',
        ['reg:%', $ip, $moc]
    )['n'];

    if ($theoIp >= 3) {
        json_fail('Bạn đã đăng ký quá nhiều lần trong giờ qua. '
                . 'Vui lòng thử lại sau hoặc liên hệ Ban Điều Hành.', 429);
    }
}

/** Ghi một lần đăng ký thành công để track */
function register_ok(): void
{
    // Xóa các lần thử đăng ký từ IP này
    db_run('DELETE FROM login_attempts WHERE phone LIKE ? AND ip = ?', ['reg:%', client_ip()]);
}

/** Ghi một lần đăng ký thất bại */
function register_failed(): void
{
    // Dùng SHA256 thay vì MD5 để có entropy tốt hơn
    $trackingId = 'reg:' . substr(hash('sha256', client_ip()), 0, 16);
    db_run('INSERT INTO login_attempts (phone, ip, tried_at) VALUES (?,?,NOW())',
           [$trackingId, client_ip()]);

    // Dọn rác
    db_run('DELETE FROM login_attempts WHERE tried_at < ?',
           [date('Y-m-d H:i:s', time() - 3600)]);
}

/** Bắt buộc dùng POST cho mọi hành động làm thay đổi dữ liệu.
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
 * Cổng chung cho MỌI hành động GHI: bắt buộc POST + CSRF token hợp lệ.
 * Gộp cặp require_post()+require_csrf() vốn lặp ở hàng chục endpoint.
 * (require_login/require_permission vẫn gọi riêng vì mỗi endpoint có
 *  mức quyền khác nhau — cố ý không nhét vào đây.)
 */
function require_write(): void
{
    require_post();
    require_csrf();
}

/**
 * Chạy một khối lệnh GHI trong giao dịch: tự beginTransaction, commit khi
 * xong, rollBack rồi NÉM LẠI lỗi nếu hỏng. Ném lại (không tự json_fail) để
 * nơi gọi giữ được thông điệp lỗi riêng của mình.
 *
 *   trong_giao_dich(function () use ($x) { ... các lệnh ghi ... });
 */
function trong_giao_dich(callable $fn)
{
    db()->beginTransaction();
    try {
        $kq = $fn();
        db()->commit();
        return $kq;
    } catch (\Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
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

/** Bắt buộc CSRF token cho mọi POST request. */
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    // Sau khi require_login() chạy, session đã có csrf_token.
    // Nếu vẫn chưa có → không hợp lệ, từ chối.
    if (empty($_SESSION['csrf_token'])) {
        json_fail('CSRF token not found. Please reload the page.', 403);
    }
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

/**
 * Các khối mà người này được QUẢN LÝ tổ chức (tạo/sửa/xóa khối-lớp,
 * phân công trưởng khối/chủ nhiệm).
 *
 * Phân biệt với quyền XEM (được thấy mọi khối) — ở đây là quyền SỬA.
 * - Quản Trị / BĐH: toàn đoàn  -> trả null (không giới hạn).
 * - Trưởng Khối (scope='khối'): chỉ khối mình được phân công trưởng khối.
 * - GLV / Dự Bị / Chủ Nhiệm: không có quyền quản lý khối-lớp -> trả [].
 */
function responsible_blocks(array $me): ?array
{
    if (in_array($me['role_code'] ?? '', ['admin', 'bdh'], true)) return null;

    $blockIds = [];
    foreach (effective_assignments((int) ($me['id'])) as $a) {
        $scope = $a['role_scope'] ?? '';
        if ($scope === 'toàn đoàn') return null;
        if (!empty($a['block_id'])) {
            $blockIds[] = (int) $a['block_id'];
        } elseif (!empty($a['class_id'])) {
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [(int) $a['class_id']]);
            if ($c && $c['block_id']) $blockIds[] = (int) $c['block_id'];
        }
    }

    // Fallback: nếu không có phân công kiêm nhiệm nào,
    // dùng block_id gốc của tài khoản (bản ghi members).
    // Cần cho trường hợp tài khoản được tạo trước khi có bảng assignments.
    if (!$blockIds && !empty($me['block_id'])) {
        $blockIds[] = (int) $me['block_id'];
    }

    if (!$blockIds) return [];
    return array_values(array_unique($blockIds));
}

/**
 * Kiểm tra xem người dùng có được quản lý (tạo/sửa/xóa) một khối cụ thể không.
 * Chỉ cần gọi khi người dùng KHÔNG phải admin/bdh.
 */
function can_manage_block(array $me, int $blockId): bool
{
    $blocks = responsible_blocks($me);
    if ($blocks === null) return true;  // admin/bdh: được tất
    if ($blocks === [])  return false; // không có quyền quản lý khối
    return in_array($blockId, $blocks, true);
}

/**
 * Kiểm tra xem người dùng có được quản lý một lớp cụ thể không
 * (dựa trên khối chứa lớp đó).
 */
function can_manage_class(array $me, int $classId): bool
{
    $blocks = responsible_blocks($me);
    if ($blocks === null) return true;  // admin/bdh
    if ($blocks === [])  return false;

    $c = db_one('SELECT block_id FROM classes WHERE id = ?', [$classId]);
    return $c && in_array((int) $c['block_id'], $blocks, true);
}
function allowed_class_ids(array $me): ?array
{
    // Ranh giới XEM hồ sơ: mọi lớp/khối mình được phân công (kể cả kiêm nhiệm).
    // Chỉ xét phạm vi, không xét module — GLV vẫn xem được hồ sơ lớp mình dù
    // không có quyền quản trị bảng thiếu nhi.
    return responsible_class_ids($me);
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

    $blockIds = [];
    foreach (member_scopes($me) as $a) {
        if (($a['role_scope'] ?? '') === 'toàn đoàn') return null;
        if (!empty($a['block_id'])) {
            $blockIds[] = (int) $a['block_id'];
        } elseif (!empty($a['class_id'])) {
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [(int) $a['class_id']]);
            if ($c && $c['block_id']) $blockIds[] = (int) $c['block_id'];
        }
    }
    if (!$blockIds) return [];

    $ph = implode(',', array_fill(0, count($blockIds), '?'));
    return array_map('intval', array_column(
        db_all("SELECT id FROM classes WHERE block_id IN ($ph)", $blockIds), 'id'));
}

/* ============================================================================
   KIÊM NHIỆM — truy vấn bảng member_assignments

   Các hàm effective_assignments(), primary_assignment(), has_active_role(),
   enforce_single_primary() được định nghĩa trong _common.php để dùng chung
   cho cả endpoint JSON (_bootstrap.php) lẫn trang HTML (_bootstrap_page.php).
   ========================================================================== */
