<?php
/**
 * THÔNG BÁO
 *
 *   POST api/announcements.php?action=save    { id?, title, body, level, audienceType, audienceValue, status, expiresAt }
 *   POST api/announcements.php?action=toggle  { id }
 *   POST api/announcements.php?action=delete  { id }
 *   POST api/announcements.php?action=read    { id }        — đánh dấu đã đọc
 *   POST api/announcements.php?action=readall
 */

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 2) . '/config/push.php';

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();

/**
 * Trưởng khối chỉ đụng được thông báo của chính khối mình.
 * BĐH và Quản trị thì toàn quyền.
 */
function can_edit_announcement(array $me, ?array $a): bool
{
    if (in_array($me['role_code'], ['admin', 'bdh'], true)) return true;
    if ($me['role_code'] !== 'truong_khoi') return false;
    if ($a === null) return true;   // đang tạo mới
    return $a['audience_type'] === 'khối' && (int) $a['audience_block'] === (int) $me['block_id'];
}

/**
 * Báo cho những người trong tầm nhận biết có thông báo mới.
 * Chỉ gọi khi thông báo thật sự được PHÁT, không gọi khi lưu nháp.
 */
function bao_thong_bao_moi(int $id, string $title, string $level,
                           string $aType, ?int $blockId, ?int $classId, int $nguoiDang = 0): void
{
    // Người vừa bấm "phát" thì khỏi phải báo lại cho chính họ
    $nguoi = array_diff(push_nguoi_nhan($aType, $blockId, $classId), [$nguoiDang]);
    $dau   = $level === 'khẩn' ? '[KHẨN] ' : ($level === 'quan trọng' ? '[Quan trọng] ' : '');
    push_bao($nguoi, $dau . 'Thông báo mới', $title, '/#announcements', 'tntt-tb-' . $id);
}

switch ($action) {

    // -------------------------------------------------------------
    case 'save':
        require_post();
        require_csrf();
        $me = require_permission('announcements', 'edit');
        if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ.', 409);

        $id    = (int) ($in['id'] ?? 0);
        $title = trim((string) ($in['title'] ?? ''));
        $body  = trim((string) ($in['body'] ?? ''));
        $level = (string) ($in['level'] ?? 'thường');
        $aType = (string) ($in['audienceType'] ?? 'toàn đoàn');
        $aVal  = trim((string) ($in['audienceValue'] ?? ''));
        $stt   = (string) ($in['status'] ?? 'đã phát');
        $exp   = ($in['expiresAt'] ?? '') ?: null;

        if ($title === '' || $body === '') json_fail('Vui lòng nhập tiêu đề và nội dung thông báo.');
        if (!in_array($level, ['thường', 'quan trọng', 'khẩn'], true)) $level = 'thường';
        if (!in_array($aType, ['toàn đoàn', 'khối', 'lớp'], true)) $aType = 'toàn đoàn';
        if (!in_array($stt, ['nháp', 'đã phát'], true)) $stt = 'nháp';

        // Trưởng khối bị khoá cứng vào khối mình
        if ($me['role_code'] === 'truong_khoi') {
            $aType = 'khối';
            $aVal  = $me['block_name'] ?? '';
        }

        $blockId = null; $classId = null;
        if ($aType === 'khối') {
            if ($aVal === '') json_fail('Vui lòng chọn khối nhận thông báo.');
            $b = db_one('SELECT id FROM blocks WHERE name=?', [$aVal]);
            if (!$b) json_fail('Không tìm thấy khối "' . $aVal . '".');
            $blockId = (int) $b['id'];
        } elseif ($aType === 'lớp') {
            if ($aVal === '') json_fail('Vui lòng chọn lớp nhận thông báo.');
            $c = db_one('SELECT id FROM classes WHERE name=?', [$aVal]);
            if (!$c) json_fail('Không tìm thấy lớp "' . $aVal . '".');
            $classId = (int) $c['id'];
        }

        if ($id) {
            $old = db_one('SELECT * FROM announcements WHERE id=? AND year_id=?', [$id, $yid]);
            if (!$old) json_fail('Không tìm thấy thông báo.', 404);
            if (!can_edit_announcement($me, $old)) json_fail('Bạn chỉ sửa được thông báo của khối mình.', 403);

            // Chuyển từ nháp sang phát thì mới đóng dấu thời gian
            $daPhatMoi = $old['status'] !== 'đã phát' && $stt === 'đã phát';
            $pub = $stt === 'đã phát' ? ($old['published_at'] ?: date('Y-m-d H:i:s')) : null;
            db_run('UPDATE announcements SET title=?, body=?, level=?, audience_type=?,
                           audience_block=?, audience_class=?, status=?, published_at=?, expires_at=?
                     WHERE id=?',
                [$title, $body, $level, $aType, $blockId, $classId, $stt, $pub, $exp, $id]);
            log_action('sua', 'announcements', 'Sửa thông báo "' . $title . '"', $stt);
        } else {
            $pub = $stt === 'đã phát' ? date('Y-m-d H:i:s') : null;
            $id = db_insert('INSERT INTO announcements (year_id, title, body, level, audience_type,
                                    audience_block, audience_class, status, published_at, expires_at, created_by)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$yid, $title, $body, $level, $aType, $blockId, $classId, $stt, $pub, $exp, $me['id']]);
            log_action('tao', 'announcements', ($stt === 'đã phát' ? 'Phát' : 'Lưu nháp')
                       . ' thông báo "' . $title . '"', $aType);
        }

        // Chỉ rung chuông khi thông báo thật sự lên sóng: tạo mới đã phát,
        // hoặc bản nháp cũ vừa được chuyển sang phát. Sửa chính tả một
        // thông báo đã phát thì không dội chuông lại.
        if ($stt === 'đã phát' && ($daPhatMoi ?? true)) {
            bao_thong_bao_moi($id, $title, $level, $aType, $blockId, $classId, (int) $me['id']);
        }

        json_out(['ok' => true, 'id' => $id, 'createdBy' => $me['full_name'],
                  'publishedAt' => $stt === 'đã phát' ? date('Y-m-d H:i') : '']);

    // -------------------------------------------------------------
    case 'toggle':
        require_post();
        require_csrf();
        $me = require_permission('announcements', 'edit');
        $a  = db_one('SELECT * FROM announcements WHERE id=? AND year_id=?', [(int) ($in['id'] ?? 0), $yid]);
        if (!$a) json_fail('Không tìm thấy thông báo.', 404);
        if (!can_edit_announcement($me, $a)) json_fail('Bạn chỉ sửa được thông báo của khối mình.', 403);

        if ($a['status'] === 'đã phát') {
            db_run("UPDATE announcements SET status='nháp', published_at=NULL WHERE id=?", [$a['id']]);
            log_action('sua', 'announcements', 'Thu hồi thông báo "' . $a['title'] . '"', 'về bản nháp');
            json_out(['ok' => true, 'status' => 'nháp', 'publishedAt' => '']);
        }
        db_run("UPDATE announcements SET status='đã phát', published_at=NOW() WHERE id=?", [$a['id']]);
        log_action('tao', 'announcements', 'Phát thông báo "' . $a['title'] . '"', $a['audience_type']);
        bao_thong_bao_moi((int) $a['id'], $a['title'], $a['level'], $a['audience_type'],
                          $a['audience_block'] !== null ? (int) $a['audience_block'] : null,
                          $a['audience_class'] !== null ? (int) $a['audience_class'] : null,
                          (int) $me['id']);
        json_out(['ok' => true, 'status' => 'đã phát', 'publishedAt' => date('Y-m-d H:i')]);

    // -------------------------------------------------------------
    case 'delete':
        require_post();
        require_csrf();
        $me = require_permission('announcements', 'edit');
        $a  = db_one('SELECT * FROM announcements WHERE id=? AND year_id=?', [(int) ($in['id'] ?? 0), $yid]);
        if (!$a) json_fail('Không tìm thấy thông báo.', 404);
        if (!can_edit_announcement($me, $a)) json_fail('Bạn chỉ xóa được thông báo của khối mình.', 403);

        db_run('DELETE FROM announcements WHERE id=?', [$a['id']]);
        log_action('xoa', 'announcements', 'Xóa thông báo "' . $a['title'] . '"', '');
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'read':
        require_post();
        require_csrf();
        $me = require_permission('announcements', 'view');
        db_run('INSERT IGNORE INTO announcement_reads (member_id, announcement_id) VALUES (?,?)',
               [$me['id'], (int) ($in['id'] ?? 0)]);
        json_out(['ok' => true]);

    case 'readall':
        require_post();
        require_csrf();
        $me = require_permission('announcements', 'view');
        db_run('INSERT IGNORE INTO announcement_reads (member_id, announcement_id)
                SELECT ?, id FROM announcements WHERE year_id = ? AND status = ?',
               [$me['id'], $yid, 'đã phát']);
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
