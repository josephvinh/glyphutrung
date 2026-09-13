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
 * Các khối mà người này LÀM TRƯỞNG (phân công phạm vi 'khối', gồm kiêm
 * nhiệm nhiều khối). Fallback về block chính trong members khi chưa có
 * phân công nào (dữ liệu cũ).
 */
function my_head_block_ids(array $me): array
{
    $ids = [];
    foreach (effective_assignments((int) $me['id']) as $a) {
        if (($a['role_scope'] ?? '') === 'khối' && !empty($a['block_id'])) {
            $ids[] = (int) $a['block_id'];
        }
    }
    if (!$ids && $me['role_code'] === 'truong_khoi' && !empty($me['block_id'])) {
        $ids[] = (int) $me['block_id'];
    }
    return $ids;
}

/**
 * Trưởng khối chỉ đụng được thông báo của khối mình làm trưởng (gồm kiêm
 * nhiệm nhiều khối). BĐH và Quản trị thì toàn quyền.
 */
function can_edit_announcement(array $me, ?array $a): bool
{
    if (in_array($me['role_code'], ['admin', 'bdh'], true)) return true;
    if ($me['role_code'] !== 'truong_khoi') return false;
    if ($a === null) return true;   // đang tạo mới (khối được validate lúc lưu)
    return $a['audience_type'] === 'khối'
        && in_array((int) $a['audience_block'], my_head_block_ids($me), true);
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

        // Buổi họp: cần ngày giờ họp; địa điểm tuỳ chọn
        $isMeeting = !empty($in['isMeeting']) ? 1 : 0;
        $meetAt = null; $meetPlace = null;
        if ($isMeeting) {
            $mRaw = str_replace('T', ' ', trim((string) ($in['meetingAt'] ?? '')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $mRaw)) {
                json_fail('Buổi họp cần chọn ngày và giờ họp.');
            }
            $meetAt    = $mRaw . ':00';
            $meetPlace = trim((string) ($in['meetingPlace'] ?? ''));
            $meetPlace = $meetPlace === '' ? null : mb_substr($meetPlace, 0, 255);
        }

        if ($title === '' || $body === '') json_fail('Vui lòng nhập tiêu đề và nội dung thông báo.');
        if (!in_array($level, ['thường', 'quan trọng', 'khẩn'], true)) $level = 'thường';
        if (!in_array($aType, ['toàn đoàn', 'khối', 'lớp'], true)) $aType = 'toàn đoàn';
        if (!in_array($stt, ['nháp', 'đã phát'], true)) $stt = 'nháp';

        // Trưởng khối chỉ gửi được thông báo KHỐI (không toàn đoàn/lớp);
        // khối cụ thể do họ chọn, được validate ngay bên dưới.
        if ($me['role_code'] === 'truong_khoi') {
            $aType = 'khối';
        }

        $blockId = null; $classId = null;
        if ($aType === 'khối') {
            if ($aVal === '') json_fail('Vui lòng chọn khối nhận thông báo.');
            $b = db_one('SELECT id FROM blocks WHERE name=?', [$aVal]);
            if (!$b) json_fail('Không tìm thấy khối "' . $aVal . '".');
            $blockId = (int) $b['id'];
            if ($me['role_code'] === 'truong_khoi'
                && !in_array($blockId, my_head_block_ids($me), true)) {
                json_fail('Bạn chỉ gửi được thông báo cho khối mình phụ trách.', 403);
            }
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
            // Sửa buổi họp -> cho phép nhắc lại theo giờ mới (reminded_at về NULL)
            db_run('UPDATE announcements SET title=?, body=?, level=?, audience_type=?,
                           audience_block=?, audience_class=?, status=?, published_at=?, expires_at=?,
                           is_meeting=?, meeting_at=?, meeting_place=?, reminded_at=NULL
                     WHERE id=?',
                [$title, $body, $level, $aType, $blockId, $classId, $stt, $pub, $exp,
                 $isMeeting, $meetAt, $meetPlace, $id]);
            log_action('sua', 'announcements', 'Sửa ' . ($isMeeting ? 'buổi họp' : 'thông báo') . ' "' . $title . '"', $stt);
        } else {
            $pub = $stt === 'đã phát' ? date('Y-m-d H:i:s') : null;
            $id = db_insert('INSERT INTO announcements (year_id, title, body, level, audience_type,
                                    audience_block, audience_class, status, published_at, expires_at, created_by,
                                    is_meeting, meeting_at, meeting_place)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$yid, $title, $body, $level, $aType, $blockId, $classId, $stt, $pub, $exp, $me['id'],
                 $isMeeting, $meetAt, $meetPlace]);
            log_action('tao', 'announcements', ($stt === 'đã phát' ? 'Phát' : 'Lưu nháp')
                       . ' thông báo "' . $title . '"', $aType);
        }

        // Chỉ rung chuông khi thông báo thật sự lên sóng: tạo mới đã phát,
        // hoặc bản nháp cũ vừa được chuyển sang phát. Sửa chính tả một
        // thông báo đã phát thì không dội chuông lại.
        if ($stt === 'đã phát' && ($daPhatMoi ?? true)) {
            bao_thong_bao_moi($id, $title, $level, $aType, $blockId, $classId, (int) $me['id']);
        }

        // Bất kỳ thay đổi thông báo nào cũng cần xoá cache để mọi người thấy ngay
        Cache::flush();

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
        Cache::flush();
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
        Cache::flush();
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
    // Trả lời họp (tham gia / không) — bất kỳ thành viên nào được mời
    case 'rsvp':
        require_post();
        require_csrf();
        $me     = require_login();
        $id     = (int) ($in['id'] ?? 0);
        $status = (string) ($in['status'] ?? '');
        if (!in_array($status, ['tham gia', 'không tham gia'], true)) json_fail('Lựa chọn không hợp lệ.');
        $a = db_one("SELECT * FROM announcements WHERE id=? AND year_id=? AND is_meeting=1 AND status='đã phát'", [$id, $yid]);
        if (!$a) json_fail('Không tìm thấy buổi họp.', 404);

        db_run('INSERT INTO meeting_rsvp (announcement_id, member_id, status, responded_at)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), responded_at = VALUES(responded_at)',
               [$id, $me['id'], $status, date('Y-m-d H:i:s')]);
        // Cập nhật cache của người trả lời + người phát (để họ thấy số mới).
        // Xoá cả 3 part vì cache nay tách theo core/heavy/all.
        foreach (['_core', '_heavy', '_all'] as $p) {
            Cache::del('data_' . $yid . '_' . (int) $me['id'] . $p);
            Cache::del('data_' . $yid . '_' . (int) $a['created_by'] . $p);
        }
        json_out(['ok' => true, 'status' => $status]);

    // -------------------------------------------------------------
    // Kết quả họp — chỉ người phát (hoặc BĐH/Quản trị) mới xem
    case 'rsvpList':
        $me = require_login();
        $id = (int) ($in['id'] ?? 0);
        $a  = db_one('SELECT * FROM announcements WHERE id=? AND year_id=?', [$id, $yid]);
        if (!$a || !$a['is_meeting']) json_fail('Không tìm thấy buổi họp.', 404);
        if ((int) $a['created_by'] !== (int) $me['id'] && !in_array($me['role_code'], ['admin', 'bdh'], true)) {
            json_fail('Chỉ người phát mới xem được kết quả.', 403);
        }

        $ids = push_nguoi_nhan($a['audience_type'],
            $a['audience_block'] !== null ? (int) $a['audience_block'] : null,
            $a['audience_class'] !== null ? (int) $a['audience_class'] : null);

        $rsvp = [];
        foreach (db_all('SELECT member_id, status FROM meeting_rsvp WHERE announcement_id=?', [$id]) as $r) {
            $rsvp[(int) $r['member_id']] = $r['status'];
        }

        $rows = []; $yes = 0; $no = 0; $pending = 0;
        if ($ids) {
            $chan = implode(',', array_fill(0, count($ids), '?'));
            foreach (db_all("SELECT id, holy_name, full_name FROM members WHERE id IN ($chan) ORDER BY full_name", $ids) as $m) {
                $st = $rsvp[(int) $m['id']] ?? 'chưa trả lời';
                if ($st === 'tham gia') $yes++; elseif ($st === 'không tham gia') $no++; else $pending++;
                $rows[] = [
                    'name'   => trim(($m['holy_name'] ? $m['holy_name'] . ' ' : '') . $m['full_name']),
                    'status' => $st,
                ];
            }
        }
        json_out(['ok' => true, 'yes' => $yes, 'no' => $no, 'pending' => $pending, 'total' => count($rows), 'rows' => $rows]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
