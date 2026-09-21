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

$action = $_GET['action'] ?? '';

// Duyệt tài khoản + quản lý nhân sự thuộc module Nhân sự (staff).
// Còn lại (khối, lớp, chủ nhiệm/trưởng khối) thuộc module Tổ chức (org).
$STAFF_ACTIONS = ['saveMember', 'deleteMember', 'approveMember', 'rejectMember', 'resetPassword'];
$me   = in_array($action, $STAFF_ACTIONS, true)
    ? require_permission('staff', 'edit')
    : require_permission('org', 'edit');

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];
$in     = json_input();

// Sử dụng OrgService để tách logic nghiệp vụ
require_once __DIR__ . '/OrgService.php';
$org = new OrgService($me, $yid, $in);

/** Legacy helpers - dùng OrgService */
function is_protected(array $m): bool {
    return in_array($m['role_code'], ['admin', 'bdh'], true);
}

function demote(int $memberId): void {
    global $org;
    $org->demoteMember($memberId);
}

switch ($action) {

    // ============================= KHỐI =============================
    case 'saveBlock':
        $result = $org->saveBlock();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    case 'deleteBlock':
        $result = $org->deleteBlock();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    // ============================= LỚP ==============================
    case 'saveClass':
        $result = $org->saveClass();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    case 'deleteClass':
        $result = $org->deleteClass();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    // ============================ NHÂN SỰ ===========================
    // TODO: Di chuyển sang StaffService riêng
    case 'saveMember':
        require_write();
        $id    = (int) ($in['id'] ?? 0);
        
        $holyName = mb_convert_case(preg_replace('/\s+/', ' ', trim((string) ($in['holyName'] ?? ''))), MB_CASE_TITLE, 'UTF-8');
        $name     = mb_convert_case(preg_replace('/\s+/', ' ', trim((string) ($in['fullName'] ?? ''))), MB_CASE_TITLE, 'UTF-8');
        
        $phone = preg_replace('/[^\d]/', '', (string) ($in['phone'] ?? ''));
        if (strpos($phone, '84') === 0 && strlen($phone) >= 11) $phone = '0' . substr($phone, 2);
        if ($phone !== '' && $phone[0] !== '0') $phone = '0' . $phone;

        $role  = (string) ($in['role'] ?? 'glv');
        if ($name === '')  json_fail('Vui lòng nhập họ và tên.');
        if ($phone === '') json_fail('Vui lòng nhập số điện thoại — đây cũng là tên đăng nhập.');

        $old = $id ? db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]) : null;
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
                    [$holyName, $name, $phone,
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
                    [$code, $holyName, $name, $phone,
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

        Cache::flush();
        json_out(['ok' => true, 'id' => $id]);

    case 'deleteMember':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if (is_protected($m)) json_fail('Không thể xóa thành viên Ban Điều Hành hoặc Quản trị từ màn này.', 403);
        if ((int) $m['id'] === (int) $me['id']) json_fail('Không thể tự xóa tài khoản của chính mình.', 403);

        db_run('DELETE FROM members WHERE id=?', [$id]);
        log_action('xoa', 'org', 'Xóa thành viên ' . $m['full_name'], $m['role_code']);
        Cache::flush();
        json_out(['ok' => true]);

    // ==================== DUYỆT TÀI KHOẢN TỰ ĐĂNG KÝ =================
    case 'approveMember':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if ($m['status'] !== 'chờ duyệt') json_fail('Tài khoản này đã được duyệt rồi.');

        // Duyệt chỉ BẬT tài khoản + đặt vai khởi tạo (GLV / Dự Bị). KHÔNG phân
        // lớp ở đây: việc phân lớp/khối làm sau ở màn Khối & Lớp — đó là ĐƯỜNG
        // DUY NHẤT ghi vào member_assignments (nguồn thật của sơ đồ tổ chức).
        // Duyệt xong, GLV ở trạng thái "chưa có lớp" cho tới khi được phân công.
        $role = (string) ($in['role'] ?? 'glv');
        // Chỉ khởi tạo vai cơ sở. Chức vụ có phạm vi (Chủ nhiệm, Trưởng khối, BĐH...)
        // do BĐH gán khi phân công, không đặt thẳng lúc duyệt.
        if (!in_array($role, ['glv', 'du_bi'], true)) {
            json_fail('Lúc duyệt chỉ đặt vai Giáo Lý Viên hoặc Dự Bị. Chức vụ cụ thể gán sau ở màn Khối & Lớp.');
        }

        $titleId = db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$role])['id'] ?? null;

        try {
            trong_giao_dich(function () use ($role, $titleId, $id) {
                // Bật hoạt động + vai cơ sở; xoá mọi lớp/khối cũ (nếu sót) và lời
                // nhắn đăng ký. Phân lớp thực hiện riêng ở màn Khối & Lớp.
                db_run("UPDATE members SET status='đang phục vụ', role_code=?, title_id=?,
                               block_id=NULL, class_id=NULL, register_note=NULL WHERE id=?",
                       [$role, $titleId, $id]);
            });
        } catch (\Throwable $e) {
            json_fail(safe_error($e, 'Không duyệt được: '), 500);
        }

        log_action('duyet', 'org', 'Duyệt tài khoản ' . $m['full_name'], $role);
        Cache::flush();
        json_out(['ok' => true]);

    case 'rejectMember':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT id, role_code, full_name, phone, status FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if ($m['status'] !== 'chờ duyệt') json_fail('Chỉ từ chối được tài khoản đang chờ duyệt.');

        db_run('DELETE FROM members WHERE id=?', [$id]);
        log_action('tuchoi', 'org', 'Từ chối đăng ký của ' . $m['full_name'], $m['phone']);
        Cache::flush();
        json_out(['ok' => true]);

    // ==================== CẤP LẠI MẬT KHẨU ===========================
    case 'resetPassword':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT id, role_code, full_name, phone FROM members WHERE id=?', [$id]);
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
        require_write();
        $isClass  = $action === 'setClassHead';
        $memberId = (int) ($in['memberId'] ?? 0);

        $target = $isClass
            ? db_one('SELECT id, block_id FROM classes WHERE name=?', [trim((string) ($in['className'] ?? ''))])
            : db_one('SELECT id FROM blocks WHERE name=?', [trim((string) ($in['block'] ?? ''))]);
        if (!$target) json_fail($isClass ? 'Không tìm thấy lớp.' : 'Không tìm thấy khối.', 404);

        // Kiểm tra phạm vi: chỉ admin/bdh hoặc người quản lý khối/lớp tương ứng được phép
        if ($isClass) {
            if (!can_manage_class($me, (int) $target['id'])) {
                json_fail('Bạn không có quyền phân công chủ nhiệm lớp "' . trim((string) ($in['className'] ?? '')) . '".', 403);
            }
        } else {
            if (!can_manage_block($me, (int) $target['id'])) {
                json_fail('Bạn không có quyền phân công trưởng khối "' . trim((string) ($in['block'] ?? '')) . '".', 403);
            }
        }

        db()->beginTransaction();
        try {
            $roleCode = $isClass ? 'glv_chu_nhiem' : 'truong_khoi';
            $scopeCol = $isClass ? 'class_id' : 'block_id';

            // Mỗi lớp 1 chủ nhiệm, mỗi khối 1 trưởng khối: kết thúc phân công
            // cũ của NGƯỜI KHÁC (giữ nếu vẫn là người này). memberId = 0 nghĩa
            // là gỡ hẳn chức, khi đó kết thúc tất cả.
            $oldAssigns = db_all(
                "SELECT member_id FROM member_assignments 
                  WHERE role_code = ? AND $scopeCol = ? AND to_date IS NULL AND member_id != ?",
                [$roleCode, $target['id'], $memberId]
            );
            db_run(
                "UPDATE member_assignments SET to_date = CURDATE()
                  WHERE role_code = ? AND $scopeCol = ? AND to_date IS NULL AND member_id != ?",
                [$roleCode, $target['id'], $memberId]
            );
            foreach ($oldAssigns as $old) {
                $oldM = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$old['member_id']]);
                if ($oldM && !is_protected($oldM)) {
                    demote((int) $old['member_id']);
                }
            }

            if ($memberId) {
                $m = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$memberId]);
                if (!$m) json_fail('Không tìm thấy thành viên.', 404);

                if ($isClass) {
                    // Thêm phân công kiêm nhiệm
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
                    db_run(
                        "UPDATE member_assignments SET to_date = CURDATE()
                          WHERE member_id = ? AND class_id = ? AND role_code = 'glv' AND to_date IS NULL",
                        [$memberId, $target['id']]
                    );
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

                // Cập nhật vai trò chính (bảng members) nếu được thăng cấp hoặc đổi lớp/khối ngang hàng
                if (!is_protected($m)) {
                    $levels = ['admin' => 50, 'bdh' => 40, 'truong_khoi' => 30, 'glv_chu_nhiem' => 20, 'glv' => 10];
                    $curLvl = $levels[$m['role_code']] ?? 0;
                    $newLvl = $levels[$roleCode] ?? 0;
                    if ($newLvl > $curLvl) {
                        $t = db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$roleCode]);
                        db_run('UPDATE members SET role_code=?, title_id=?, block_id=?, class_id=? WHERE id=?',
                               [$roleCode, $t['id'] ?? null, $target['block_id'] ?? $target['id'], $isClass ? $target['id'] : null, $memberId]);
                    } elseif ($newLvl === $curLvl) {
                        db_run('UPDATE members SET block_id=?, class_id=? WHERE id=?',
                               [$target['block_id'] ?? $target['id'], $isClass ? $target['id'] : null, $memberId]);
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
        Cache::flush();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
