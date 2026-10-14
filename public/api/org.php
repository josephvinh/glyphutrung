<?php
/**
 * KHỐI · LỚP · NHÂN SỰ
 *
 * Khối & Lớp (quyền module 'org'):
 *   POST api/org.php?action=saveBlock    { original?, name }
 *   POST api/org.php?action=deleteBlock  { name }
 *   POST api/org.php?action=saveClass    { original?, name, block, nextClass? }
 *   POST api/org.php?action=deleteClass  { name }
 *   POST api/org.php?action=setClassHead { className, memberId }
 *   POST api/org.php?action=setBlockHead { block, memberId }
 *
 * Nhân sự (quyền module 'staff'):
 *   POST api/org.php?action=saveMember    { id, holyName, fullName, phone, role, title, block, className }
 *   POST api/org.php?action=deleteMember  { id }
 *   POST api/org.php?action=approveMember / rejectMember / resetPassword
 *
 * Hồ sơ cá nhân (module 'org'):
 *   GET  api/org.php?action=getServiceHistory[&member_id=N]  lịch sử phục vụ (mặc định của $me)
 *   POST api/org.php?action=saveServiceHistory   { member_id, year_label, role_code?, block_id?, class_id?, note? }
 *   POST api/org.php?action=deleteServiceHistory { member_id, year_label }
 *   POST api/org.php?action=updateGlvYear        { member_id, glv_year|null }
 *   POST api/org.php?action=changeMemberStatus   { member_id, status, note? }
 *
 * member_assignments là nguồn thật của "ai phụ trách chỗ nào"; các cột
 * members.role_code/block_id/class_id chỉ là bản dẫn xuất, luôn tính lại bằng
 * recompute_member_primary() sau khi phân công đổi.
 *
 * Đổi tên khối/lớp ở đây KHÔNG phải lan sang bảng khác như hồi chạy
 * dữ liệu giả lập — các bảng đều tham chiếu bằng id, nên đổi tên là
 * xong. Đây chính là lý do đáng để chuyển sang khoá ngoại.
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';

// Duyệt tài khoản + quản lý nhân sự thuộc module Nhân sự (staff).
// Còn lại (khối, lớp, chủ nhiệm/trưởng khối) thuộc module Tổ chức (org).
// getServiceHistory là đọc — được mở ở mức 'view' để chính thành viên xem
// lịch sử phục vụ của mình; muốn xem của NGƯỜI KHÁC thì case tự nâng lên
// 'org.edit' (theo brief Task 2.3).
$STAFF_ACTIONS = ['saveMember', 'deleteMember', 'approveMember', 'rejectMember', 'resetPassword'];
$VIEW_ACTIONS  = ['getServiceHistory'];
$me   = in_array($action, $STAFF_ACTIONS, true)
    ? require_permission('staff', 'edit')
    : (in_array($action, $VIEW_ACTIONS, true)
        ? require_permission('org', 'view')
        : require_permission('org', 'edit'));

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];
$in     = json_input();

// Sử dụng OrgService và StaffService để tách logic nghiệp vụ
require_once __DIR__ . '/OrgService.php';
require_once __DIR__ . '/StaffService.php';
require_once __DIR__ . '/PersonalService.php';
$org = new OrgService($me, $yid, $in);
$staff = new StaffService($me, $yid, $in);

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
    // saveMember (A′): chỉ quản danh tính + vai gốc, KHÔNG đụng phân công.
    // Kiêm nhiệm do setClassHead/setBlockHead quản (non-destructive).
    case 'saveMember':
        $result = $staff->saveMember();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    case 'deleteMember':
        $result = $staff->deleteMember();
        if (!$result['ok']) {
            json_fail($result['error'], $result['code'] ?? 400);
        }
        json_out(['ok' => true]);

    // ==================== DUYỆT TÀI KHOẢN TỰ ĐĂNG KÝ =================
    case 'approveMember':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $m  = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [$id]);
        if (!$m) json_fail('Không tìm thấy thành viên.', 404);
        if ($e = $staff->guardTarget($m, 'approve')) json_fail($e['error'], $e['code']);
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
        if ($e = $staff->guardTarget($m, 'approve')) json_fail($e['error'], $e['code']);
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

        // Chỉ Quản trị mới cấp lại được cho Quản trị / Ban Điều Hành (kể cả chính mình
        // — tự đổi ở màn Cá nhân); mục tiêu admin với người khác = 404 (F9).
        if ($e = $staff->guardTarget($m, 'reset')) json_fail($e['error'], $e['code']);

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

        $m = null;
        if ($memberId) {
            $m = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$memberId]);
            if (!$m) json_fail('Không tìm thấy thành viên.', 404);
            // Không phân công chủ nhiệm/trưởng khối cho admin/bdh (#141)
            if (in_array($m['role_code'], ['admin', 'bdh'], true)) {
                json_fail('Không thể phân công chủ nhiệm/trưởng khối cho tài khoản Quản trị hoặc Ban Điều Hành.', 403);
            }
        }

        $roleCode = $isClass ? 'glv_chu_nhiem' : 'truong_khoi';
        $scopeCol = $isClass ? 'class_id' : 'block_id';

        db()->beginTransaction();
        try {
            // Mỗi lớp 1 chủ nhiệm, mỗi khối 1 trưởng khối: kết thúc phân công
            // cũ của NGƯỜI KHÁC (giữ nếu vẫn là người này). memberId = 0 nghĩa
            // là gỡ hẳn chức, khi đó kết thúc tất cả.
            $affected = array_column(db_all(
                "SELECT member_id FROM member_assignments
                  WHERE role_code = ? AND $scopeCol = ? AND to_date IS NULL AND member_id != ?",
                [$roleCode, $target['id'], $memberId]
            ), 'member_id');
            db_run(
                "UPDATE member_assignments SET to_date = CURDATE()
                  WHERE role_code = ? AND $scopeCol = ? AND to_date IS NULL AND member_id != ?",
                [$roleCode, $target['id'], $memberId]
            );

            if ($m) {
                $existing = db_one(
                    "SELECT id FROM member_assignments
                      WHERE member_id = ? AND $scopeCol = ? AND role_code = ? AND to_date IS NULL",
                    [$memberId, $target['id'], $roleCode]
                );
                if (!$existing) {
                    if ($isClass) {
                        db_insert(
                            "INSERT INTO member_assignments (member_id, role_code, class_id, block_id, is_primary, from_date, assigned_by, note)
                             VALUES (?, ?, ?, ?, 0, CURDATE(), ?, ?)",
                            [$memberId, $roleCode, $target['id'], $target['block_id'], $me['id'], 'Phân công chủ nhiệm lớp']
                        );
                    } else {
                        db_insert(
                            "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, assigned_by, note)
                             VALUES (?, ?, ?, 0, CURDATE(), ?, ?)",
                            [$memberId, $roleCode, $target['id'], $me['id'], 'Phân công trưởng khối']
                        );
                    }
                }
                if ($isClass) {
                    // Chủ nhiệm thay thế phân công GLV thường của chính lớp này
                    db_run(
                        "UPDATE member_assignments SET to_date = CURDATE()
                          WHERE member_id = ? AND class_id = ? AND role_code = 'glv' AND to_date IS NULL",
                        [$memberId, $target['id']]
                    );
                }
                $affected[] = $memberId;

                log_action('sua', 'org', 'Phân công ' . ($isClass ? 'chủ nhiệm lớp' : 'trưởng khối'),
                           $m['full_name']);
            }

            // Vai gốc + khối/lớp hiển thị là bản dẫn xuất — tính lại cho mọi
            // người có phân công vừa đổi (người bị thay lẫn người mới nhận).
            foreach (array_unique(array_map('intval', $affected)) as $mid) {
                recompute_member_primary($mid);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không phân công được: '), 500);
        }
        Cache::flush();
        json_out(['ok' => true]);

    // ================== HỒ SƠ CÁ NHÂN · LỊCH SỬ PHỤC VỤ =============
    // Task 2.3 — bọc lại PersonalService để frontend cá nhân dùng.
    // getServiceHistory: GET, mặc định lấy của chính $me; muốn xem của
    // người khác phải có 'org.edit' (BĐH/Quản trị).
    case 'getServiceHistory':
        $memberId = (int) ($_GET['member_id'] ?? $me['id']);
        if ($memberId !== (int) $me['id'] && permission_of('org') !== 'edit') {
            json_fail('Bạn chỉ được xem lịch sử phục vụ của chính mình.', 403);
        }
        json_ok(['history' => PersonalService::getServiceHistory($memberId)]);
        break;

    case 'saveServiceHistory':
        require_write();
        require_permission('org', 'edit');
        if (empty($in['member_id']) || empty($in['year_label'])) {
            json_fail('Thiếu thông tin bắt buộc', 400);
        }
        try {
            PersonalService::saveServiceHistory(
                (int) $in['member_id'],
                $in,
                (int) $me['id']
            );
            Cache::flush();
            json_ok(['ok' => true]);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    case 'deleteServiceHistory':
        require_write();
        require_permission('org', 'edit');
        try {
            PersonalService::deleteServiceHistory(
                (int) ($in['member_id'] ?? 0),
                (string) ($in['year_label'] ?? '')
            );
            Cache::flush();
            json_ok(['ok' => true]);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    // ================== HỒ SƠ CÁ NHÂN · NĂM LÊN GLV ===============
    case 'updateGlvYear':
        require_write();
        require_permission('org', 'edit');
        if (empty($in['member_id'])) {
            json_fail('Thiếu member_id', 400);
        }
        // Validate glv_year nếu được cung cấp (không phải null = xoá).
        $glvYear = null;
        if (array_key_exists('glv_year', $in) && $in['glv_year'] !== null) {
            $inputYear = (int) $in['glv_year'];
            if ($inputYear < 1900 || $inputYear > (int) date('Y')) {
                json_fail('Năm không hợp lệ', 400);
            }
            $glvYear = $inputYear;
        }
        try {
            $result = PersonalService::updateGlvYear(
                (int) $in['member_id'],
                $glvYear,
                (int) $me['id']
            );
            Cache::flush();
            json_ok($result);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    // ================== HỒ SƠ CÁ NHÂN · TRẠNG THÁI =================
    case 'changeMemberStatus':
        require_write();
        require_permission('org', 'edit');
        if (empty($in['member_id']) || empty($in['status'])) {
            json_fail('Thiếu thông tin bắt buộc', 400);
        }
        $targetId = (int) $in['member_id'];
        // Không cho BĐH đổi trạng thái tài khoản Quản trị.
        $target = db_one('SELECT id, role_code FROM members WHERE id=?', [$targetId]);
        if (!$target) json_fail('Không tìm thấy thành viên.', 404);
        if ($target['role_code'] === 'admin') {
            json_fail('Không thể thay đổi trạng thái tài khoản Quản trị.');
        }
        // Bắt buộc ghi chú khi rời trạng thái "đang phục vụ" — lý do tạm
        // nghỉ/nghỉ hẳn cần được lưu lại để truy vết sau này.
        $note = isset($in['note']) ? (string) $in['note'] : null;
        if (in_array($in['status'], ['tạm nghỉ', 'đã nghỉ'], true)
            && ($note === null || trim($note) === '')) {
            json_fail('Vui lòng nhập ghi chú khi thay đổi trạng thái', 400);
        }
        try {
            $result = PersonalService::changeMemberStatus(
                $targetId,
                (string) $in['status'],
                $note
            );
            Cache::flush();
            json_ok($result);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
