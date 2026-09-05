<?php
/**
 * KHỐI · LỚP · NHÂN SỰ
 *
 *   POST api/org.php?action=saveBlock    { original?, name }
 *   POST api/org.php?action=deleteBlock  { name }
 *   POST api/org.php?action=saveClass    { original?, name, block, nextClass? }
 *   POST api/org.php?action=deleteClass  { name }
 *   POST api/org.php?action=saveMember   { id?, holyName, fullName, phone, role, title, block, className, status }
 *   POST api/org.php?action=deleteMember { id }
 *   POST api/org.php?action=setClassHead { className, memberId }
 *   POST api/org.php?action=setBlockHead { block, memberId }
 *
 * Đổi tên khối/lớp ở đây KHÔNG phải lan sang bảng khác như hồi chạy
 * dữ liệu giả lập — các bảng đều tham chiếu bằng id, nên đổi tên là
 * xong. Đây chính là lý do đáng để chuyển sang khoá ngoại.
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_permission('org', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();

/** Không cho hạ vai trò của Ban Điều Hành và Quản trị từ màn này */
function is_protected(array $m): bool
{
    return in_array($m['role_code'], ['admin', 'bdh'], true);
}

/** Người đang giữ chức phải hạ xuống trước khi nhấc người mới lên */
function demote(int $memberId): void
{
    $t = db_one("SELECT id FROM titles WHERE role_code='glv' AND label='GLV Phụ Tá' LIMIT 1");
    db_run("UPDATE members SET role_code='glv', title_id=? WHERE id=?", [$t['id'] ?? null, $memberId]);
}

switch ($action) {

    // ============================= KHỐI =============================
    case 'saveBlock':
        require_post();
        require_csrf();
        $name = trim((string) ($in['name'] ?? ''));
        $old  = trim((string) ($in['original'] ?? ''));
        if ($name === '') json_fail('Vui lòng nhập tên khối.');

        $dup = db_one('SELECT id FROM blocks WHERE name = ?', [$name]);
        if ($dup && $old !== $name) json_fail('Tên khối này đã tồn tại.');

        if ($old === '') {
            $max = db_one('SELECT COALESCE(MAX(sort_order),0) n FROM blocks');
            db_insert('INSERT INTO blocks (name, sort_order) VALUES (?,?)', [$name, $max['n'] + 1]);
            log_action('tao', 'org', 'Thêm khối ' . $name, '');
        } elseif ($old !== $name) {
            db_run('UPDATE blocks SET name=? WHERE name=?', [$name, $old]);
            log_action('sua', 'org', 'Đổi tên khối ' . $old . ' thành ' . $name, '');
        }
        json_out(['ok' => true]);

    case 'deleteBlock':
        require_post();
        require_csrf();
        $name = trim((string) ($in['name'] ?? ''));
        $b = db_one('SELECT id FROM blocks WHERE name=?', [$name]);
        if (!$b) json_fail('Không tìm thấy khối.', 404);

        $n = db_one('SELECT COUNT(*) n FROM classes WHERE block_id=?', [$b['id']])['n'];
        if ($n > 0) json_fail('Khối "' . $name . '" còn ' . $n . ' lớp. Hãy chuyển hoặc xóa hết lớp trước.');

        db_run('DELETE FROM blocks WHERE id=?', [$b['id']]);
        log_action('xoa', 'org', 'Xóa khối ' . $name, '');
        json_out(['ok' => true]);

    // ============================= LỚP ==============================
    case 'saveClass':
        require_post();
        require_csrf();
        $name  = trim((string) ($in['name'] ?? ''));
        $old   = trim((string) ($in['original'] ?? ''));
        $block = trim((string) ($in['block'] ?? ''));
        if ($name === '')  json_fail('Vui lòng nhập tên lớp.');
        if ($block === '') json_fail('Vui lòng chọn khối cho lớp.');

        $b = db_one('SELECT id FROM blocks WHERE name=?', [$block]);
        if (!$b) json_fail('Không tìm thấy khối "' . $block . '".');

        $dup = db_one('SELECT id FROM classes WHERE name=?', [$name]);
        if ($dup && $old !== $name) json_fail('Tên lớp này đã tồn tại.');

        if ($old === '') {
            db_insert('INSERT INTO classes (name, block_id, sort_order) VALUES (?,?,?)',
                      [$name, $b['id'], 1]);
            log_action('tao', 'org', 'Thêm lớp ' . $name, 'khối ' . $block);
        } else {
            db_run('UPDATE classes SET name=?, block_id=? WHERE name=?', [$name, $b['id'], $old]);
            log_action('sua', 'org', 'Sửa lớp ' . $old, 'thành ' . $name . ' · khối ' . $block);
        }

        // Sơ đồ lên lớp
        if (array_key_exists('nextClass', $in)) {
            $target = trim((string) $in['nextClass']);
            if ($target === 'RA_TRUONG') {
                db_run('UPDATE classes SET next_class_id=NULL, is_final=1 WHERE name=?', [$name]);
            } elseif ($target === '') {
                db_run('UPDATE classes SET next_class_id=NULL, is_final=0 WHERE name=?', [$name]);
            } else {
                $t = db_one('SELECT id FROM classes WHERE name=?', [$target]);
                if (!$t) json_fail('Không tìm thấy lớp kế tiếp "' . $target . '".');
                db_run('UPDATE classes SET next_class_id=?, is_final=0 WHERE name=?', [$t['id'], $name]);
            }
        }
        json_out(['ok' => true]);

    case 'deleteClass':
        require_post();
        require_csrf();
        $name = trim((string) ($in['name'] ?? ''));
        $c = db_one('SELECT id FROM classes WHERE name=?', [$name]);
        if (!$c) json_fail('Không tìm thấy lớp.', 404);

        $n = db_one('SELECT COUNT(*) n FROM enrollments WHERE class_id=?', [$c['id']])['n'];
        if ($n > 0) json_fail('Lớp "' . $name . '" còn ' . $n . ' em ghi danh. Hãy chuyển các em sang lớp khác trước.');

        $g = db_one('SELECT COUNT(*) n FROM members WHERE class_id=?', [$c['id']])['n'];
        if ($g > 0) json_fail('Lớp "' . $name . '" còn ' . $g . ' GLV đang phụ trách. Hãy chuyển họ trước.');

        db_run('DELETE FROM classes WHERE id=?', [$c['id']]);
        log_action('xoa', 'org', 'Xóa lớp ' . $name, '');
        json_out(['ok' => true]);

    // ============================ NHÂN SỰ ===========================
    case 'saveMember':
        require_post();
        require_csrf();
        $id    = (int) ($in['id'] ?? 0);
        $name  = trim((string) ($in['fullName'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? ''));
        $role  = (string) ($in['role'] ?? 'glv');
        if ($name === '')  json_fail('Vui lòng nhập họ và tên.');
        if ($phone === '') json_fail('Vui lòng nhập số điện thoại — đây cũng là tên đăng nhập.');

        $old = $id ? db_one('SELECT * FROM members WHERE id=?', [$id]) : null;
        if ($id && !$old) json_fail('Không tìm thấy thành viên.', 404);

        // Vai trò của BĐH/Quản trị bị khoá
        if ($old && is_protected($old)) $role = $old['role_code'];

        $r = db_one('SELECT scope FROM roles WHERE code=?', [$role]);
        if (!$r) json_fail('Vai trò không hợp lệ.');

        $blockId = null; $classId = null;
        if ($r['scope'] === 'khối') {
            $b = db_one('SELECT id FROM blocks WHERE name=?', [trim((string) ($in['block'] ?? ''))]);
            if (!$b) json_fail('Vai trò Trưởng Khối cần chọn khối phụ trách.');
            $blockId = (int) $b['id'];
        } elseif ($r['scope'] === 'lớp') {
            $c = db_one('SELECT c.id, c.block_id FROM classes c WHERE c.name=?',
                        [trim((string) ($in['className'] ?? ''))]);
            if (!$c) json_fail('Vai trò này cần chọn lớp phụ trách.');
            $classId = (int) $c['id'];
            $blockId = (int) $c['block_id'];
        }

        $t = db_one('SELECT id FROM titles WHERE role_code=? AND label=?',
                    [$role, trim((string) ($in['title'] ?? ''))]);
        $titleId = $t['id'] ?? db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$role])['id'] ?? null;

        $dup = db_one('SELECT id FROM members WHERE phone=? AND id <> ?', [$phone, $id]);
        if ($dup) json_fail('Số điện thoại này đã có tài khoản khác dùng.');

        $status = (string) ($in['status'] ?? 'đang phục vụ');

        db()->beginTransaction();
        try {
            // Mỗi lớp một chủ nhiệm, mỗi khối một trưởng khối
            if ($role === 'glv_chu_nhiem' && $classId) {
                foreach (db_all("SELECT id FROM members WHERE role_code='glv_chu_nhiem' AND class_id=? AND id<>?",
                                [$classId, $id]) as $cur) demote((int) $cur['id']);
            }
            if ($role === 'truong_khoi' && $blockId) {
                foreach (db_all("SELECT id FROM members WHERE role_code='truong_khoi' AND block_id=? AND id<>?",
                                [$blockId, $id]) as $cur) demote((int) $cur['id']);
            }

            if ($id) {
                db_run('UPDATE members SET holy_name=?, full_name=?, phone=?, birth_date=?,
                               role_code=?, title_id=?, block_id=?, class_id=?, status=? WHERE id=?',
                    [trim((string) ($in['holyName'] ?? '')), $name, $phone,
                     ($in['birthDate'] ?? '') ?: null, $role,
                     $titleId, $blockId, $classId, $status, $id]);
                log_action('sua', 'org', 'Sửa thành viên ' . $name, $role);
            } else {
                $max = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n
                                 FROM members WHERE code LIKE 'GLV%'");
                $code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);
                $id = db_insert('INSERT INTO members (code, holy_name, full_name, phone, birth_date,
                                        password_hash, role_code, title_id, block_id, class_id,
                                        status, must_change_pw)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,1)',
                    [$code, trim((string) ($in['holyName'] ?? '')), $name, $phone,
                     ($in['birthDate'] ?? '') ?: null,
                     password_hash(app_config('default_password'), PASSWORD_DEFAULT),
                     $role, $titleId, $blockId, $classId, $status]);
                log_action('tao', 'org', 'Thêm thành viên ' . $name,
                           $role . ' · mật khẩu mặc định ' . app_config('default_password'));
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không lưu được: '), 500);
        }

        json_out(['ok' => true, 'id' => $id]);

    case 'deleteMember':
        require_post();
        require_csrf();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT * FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if (is_protected($m)) json_fail('Không thể xóa thành viên Ban Điều Hành hoặc Quản trị từ màn này.', 403);
        if ((int) $m['id'] === (int) $me['id']) json_fail('Không thể tự xóa tài khoản của chính mình.', 403);

        db_run('DELETE FROM members WHERE id=?', [$id]);
        log_action('xoa', 'org', 'Xóa thành viên ' . $m['full_name'], $m['role_code']);
        json_out(['ok' => true]);

    // ==================== DUYỆT TÀI KHOẢN TỰ ĐĂNG KÝ =================
    case 'approveMember':
        require_post();
        require_csrf();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT * FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if ($m['status'] !== 'chờ duyệt') json_fail('Tài khoản này đã được duyệt rồi.');

        // Duyệt là phải phân công luôn, không để tài khoản lơ lửng
        $role = (string) ($in['role'] ?? 'glv');
        $r = db_one('SELECT scope FROM roles WHERE code=?', [$role]);
        if (!$r) json_fail('Vai trò không hợp lệ.');
        if (in_array($role, ['admin', 'bdh'], true)) {
            json_fail('Không thể duyệt thẳng lên Ban Điều Hành. Hãy duyệt làm GLV trước, sau đó nâng quyền dưới cơ sở dữ liệu.');
        }

        $blockId = null; $classId = null;
        if ($r['scope'] === 'khối') {
            $b = db_one('SELECT id FROM blocks WHERE name=?', [trim((string) ($in['block'] ?? ''))]);
            if (!$b) json_fail('Vui lòng chọn khối phụ trách khi duyệt.');
            $blockId = (int) $b['id'];
        } elseif ($r['scope'] === 'lớp') {
            $c = db_one('SELECT id, block_id FROM classes WHERE name=?', [trim((string) ($in['className'] ?? ''))]);
            if (!$c) json_fail('Vui lòng chọn lớp phụ trách khi duyệt.');
            $classId = (int) $c['id'];
            $blockId = (int) $c['block_id'];
        }

        $titleId = db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$role])['id'] ?? null;

        db()->beginTransaction();
        try {
            if ($role === 'glv_chu_nhiem' && $classId) {
                foreach (db_all("SELECT id FROM members WHERE role_code='glv_chu_nhiem' AND class_id=? AND id<>?",
                                [$classId, $id]) as $cur) demote((int) $cur['id']);
            }
            db_run("UPDATE members SET status='đang phục vụ', role_code=?, title_id=?,
                           block_id=?, class_id=?, register_note=NULL WHERE id=?",
                   [$role, $titleId, $blockId, $classId, $id]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không duyệt được: '), 500);
        }

        log_action('duyet', 'org', 'Duyệt tài khoản ' . $m['full_name'],
                   $role . ' · ' . ($in['className'] ?? $in['block'] ?? 'toàn đoàn'));
        json_out(['ok' => true]);

    case 'rejectMember':
        require_post();
        require_csrf();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT * FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if ($m['status'] !== 'chờ duyệt') json_fail('Chỉ từ chối được tài khoản đang chờ duyệt.');

        db_run('DELETE FROM members WHERE id=?', [$id]);
        log_action('tuchoi', 'org', 'Từ chối đăng ký của ' . $m['full_name'], $m['phone']);
        json_out(['ok' => true]);

    // ==================== CẤP LẠI MẬT KHẨU ===========================
    case 'resetPassword':
        require_post();
        require_csrf();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT * FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);

        // Chỉ Quản trị mới cấp lại được cho Ban Điều Hành
        if (is_protected($m) && $me['role_code'] !== 'admin') {
            json_fail('Chỉ Quản Trị Hệ Thống mới cấp lại mật khẩu cho Ban Điều Hành.', 403);
        }

        // Mật khẩu tạm sinh ngẫu nhiên, không dùng chung một chuỗi cho mọi
        // người — nếu không thì ai cũng đoán được mật khẩu của người mới.
        $temp = 'tntt' . random_int(1000, 9999);
        db_run('UPDATE members SET password_hash=?, must_change_pw=1 WHERE id=?',
               [password_hash($temp, PASSWORD_DEFAULT), $id]);

        log_action('sua', 'org', 'Cấp lại mật khẩu cho ' . $m['full_name'],
                   'lần đăng nhập sau buộc đổi');
        json_out(['ok' => true, 'password' => $temp, 'phone' => $m['phone'],
                  'name' => $m['full_name']]);

    // ======================= CHỦ NHIỆM / TRƯỞNG KHỐI =================
    case 'setClassHead':
    case 'setBlockHead':
        require_post();
        require_csrf();
        $isClass  = $action === 'setClassHead';
        $memberId = (int) ($in['memberId'] ?? 0);

        $target = $isClass
            ? db_one('SELECT id, block_id FROM classes WHERE name=?', [trim((string) ($in['className'] ?? ''))])
            : db_one('SELECT id FROM blocks WHERE name=?', [trim((string) ($in['block'] ?? ''))]);
        if (!$target) json_fail($isClass ? 'Không tìm thấy lớp.' : 'Không tìm thấy khối.', 404);

        db()->beginTransaction();
        try {
            $roleCode = $isClass ? 'glv_chu_nhiem' : 'truong_khoi';

            // Bỏ hạ người cũ - thay vào đó chỉ kết thúc phân công cũ
            $oldAssignments = db_all(
                "SELECT a.id FROM member_assignments a
                 JOIN roles r ON r.code = a.role_code
                 WHERE r.role_code = ? AND " . ($isClass ? "a.class_id" : "a.block_id") . " = ?",
                [$roleCode, $target['id']]
            );
            foreach ($oldAssignments as $old) {
                if ($isClass) {
                    db_run("UPDATE member_assignments SET to_date = CURDATE() WHERE id = ? AND member_id != ?",
                           [$old['id'], $memberId]);
                }
            }

            if ($memberId) {
                $m = db_one('SELECT * FROM members WHERE id=?', [$memberId]);
                if (!$m) json_fail('Không tìm thấy thành viên.', 404);

                if ($isClass) {
                    // Thêm phân công kiêm nhiệm, KHÔNG thay đổi vai trò chính
                    // Kiểm tra đã có phân công chưa
                    $existing = db_one(
                        "SELECT id FROM member_assignments WHERE member_id = ? AND class_id = ? AND role_code = ? AND to_date IS NULL",
                        [$memberId, $target['id'], $roleCode]
                    );
                    if (!$existing) {
                        db_insert(
                            "INSERT INTO member_assignments (member_id, role_code, class_id, block_id, is_primary, from_date, assigned_by, note)
                             VALUES (?, ?, ?, ?, 0, CURDATE(), ?, ?)",
                            [$memberId, $roleCode, $target['id'], $target['block_id'], $me['id'], 'Phân công chủ nhiệm lớp']
                        );
                    }
                } else {
                    // Trưởng khối - thêm vào block
                    $existing = db_one(
                        "SELECT id FROM member_assignments WHERE member_id = ? AND block_id = ? AND role_code = ? AND to_date IS NULL",
                        [$memberId, $target['id'], $roleCode]
                    );
                    if (!$existing) {
                        db_insert(
                            "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, assigned_by, note)
                             VALUES (?, ?, ?, 0, CURDATE(), ?, ?)",
                            [$memberId, $roleCode, $target['id'], $me['id'], 'Phân công trưởng khối']
                        );
                    }
                }
                log_action('sua', 'org', 'Phân công ' . ($isClass ? 'chủ nhiệm lớp' : 'trưởng khối'),
                           $m['full_name']);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không phân công được: '), 500);
        }
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
