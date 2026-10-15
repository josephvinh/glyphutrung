<?php
/**
 * HỒ SƠ CÁ NHÂN
 *
 * API cho frontend cá nhân (module_personal) — xem hồ sơ của chính mình
 * và sửa các trường do BĐH/Quản trị cấp quyền. Đây là đường DUY NHẤT cho các
 * thao tác này (org.php không có bản sao: hai bản trùng đã từng lệch nhau).
 *
 *   GET  api/personal.php?action=getProfile[&member_id=N]
 *   POST api/personal.php?action=updateGlvYear        { member_id, glv_year|null }   (phải có khoá glv_year; null = xoá)
 *   POST api/personal.php?action=saveServiceHistory   { member_id, year_label, role_code?, block_id?, class_id?, note? }
 *   POST api/personal.php?action=deleteServiceHistory { member_id, year_label }
 *   POST api/personal.php?action=changeMemberStatus   { member_id, status, note? }
 *
 * Phân quyền (ba lớp, AGENT_RULES.md §2):
 *   - getProfile             : org.view cho hồ sơ CỦA MÌNH; hồ sơ người khác cần
 *                              staff.edit (Nhân sự). Không có staff.edit: mọi id khác
 *                              mình đều 403 như nhau (không dò được ai có/là admin).
 *                              Có staff.edit: id admin trả 404 như id không có (F9).
 *   - updateGlvYear / saveServiceHistory / deleteServiceHistory /
 *     changeMemberStatus     : staff.edit (BĐH/Quản trị theo ma trận mặc định) +
 *                              kiểm đối tượng PersonalService::authorizeTarget
 *                              (admin → 404, BĐH khác → chỉ Quản trị, #97).
 *                              Cổng 'org' KHÔNG dùng: Trưởng khối có org.edit nên
 *                              sẽ khoá được cả tài khoản Quản trị. Chính sách hiện
 *                              không cho thành viên tự đổi năm GLV của mình.
 *
 * Dữ liệu trả ra chỉ gồm cột an toàn: phân công qua public_assignment() (không
 * assigned_by/note), lịch sử không có created_by — đều là id/ghi chú của Quản trị.
 *
 * Mọi thao tác GHI đi qua require_write() (POST + CSRF + rate-limit).
 */

require __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/PersonalService.php';

$action = $_GET['action'] ?? '';
$in     = json_input();

// getProfile là đọc — route ở mức 'view' (mọi thành viên đều xem được hồ sơ
// của chính mình). Xem người KHÁC thì authorizeTarget đòi staff.edit.
$VIEW_ACTIONS = ['getProfile'];

$me = in_array($action, $VIEW_ACTIONS, true)
    ? require_permission('org', 'view')
    : require_permission('staff', 'edit');

$canEditStaff = permission_of('staff') === 'edit';

/** Dừng với lỗi nếu người gọi không được thao tác $op trên hồ sơ $memberId. */
function personal_guard(array $me, bool $canEditStaff, int $memberId, string $op): void
{
    if ($memberId <= 0) json_fail('Thiếu member_id.', 400);
    $deny = PersonalService::authorizeTarget($me, $canEditStaff, $memberId, $op);
    if ($deny) json_fail($deny['error'], $deny['code']);
}

/** Chạy một thao tác ghi: lỗi nghiệp vụ → 400/404/409 kèm thông điệp; lỗi CSDL → 500 không lộ SQL. */
function personal_run(callable $fn): array
{
    try {
        return $fn();
    } catch (PersonalException $e) {
        json_fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 400);
    } catch (Throwable $e) {
        json_fail(safe_error($e, 'Không lưu được:'), 500);
    }
}

/** Tên thành viên để ghi nhật ký (đã qua personal_guard nên chắc chắn tồn tại). */
function personal_name(int $memberId): string
{
    return (string) (db_one('SELECT full_name FROM members WHERE id = ?', [$memberId])['full_name'] ?? ('#' . $memberId));
}

switch ($action) {

    // ============================= HỒ SƠ =============================
    case 'getProfile':
        $targetId = (int) ($_GET['member_id'] ?? $me['id']);
        personal_guard($me, $canEditStaff, $targetId, 'view');

        $member = db_one(
            'SELECT m.*, t.label AS title_label
               FROM members m
               LEFT JOIN titles t ON t.id = m.title_id
              WHERE m.id = ?',
            [$targetId]
        );

        json_ok([
            'member' => [
                'id'            => (int) $member['id'],
                'holyName'      => $member['holy_name'],
                'fullName'      => $member['full_name'],
                'phone'         => $member['phone'],
                'birthDate'     => $member['birth_date'],
                'role'          => $member['role_code'],
                'title'         => $member['title_label'],
                'status'        => $member['status'],
                'glvYear'       => $member['glv_year'] !== null
                    ? (int) $member['glv_year']
                    : null,
                'yearsOfService' => PersonalService::calculateYearsOfService(
                    $member['glv_year'] !== null ? (int) $member['glv_year'] : null
                ),
            ],
            'assignments' => array_map('public_assignment', effective_assignments($targetId)),
            'history'     => PersonalService::getServiceHistory($targetId),
        ]);
        break;

    // ============================ NĂM GLV ============================
    case 'updateGlvYear':
        require_write();
        $memberId = (int) ($in['member_id'] ?? 0);
        personal_guard($me, $canEditStaff, $memberId, 'edit');

        // Bắt buộc có khoá glv_year: null = xoá năm lên GLV (và mọi dòng tự sinh); có giá trị thì
        // service kiểm 1900..năm bắt đầu niên khoá hiện tại. Thiếu khoá KHÔNG được hiểu là xoá.
        if (!array_key_exists('glv_year', $in)) json_fail('Thiếu glv_year (gửi null để xoá năm lên GLV).', 400);
        $glvYear = $in['glv_year'] !== null ? (int) $in['glv_year'] : null;

        $result = personal_run(fn() => PersonalService::updateGlvYear($memberId, $glvYear, (int) $me['id']));

        log_action('sua', 'staff', 'Đặt năm lên GLV: ' . personal_name($memberId),
                   $glvYear === null ? 'xoá' : (string) $glvYear);
        Cache::flush();   // data.php (members[].glv_year) đang được cache theo phạm vi
        json_ok($result);
        break;

    // ====================== LỊCH SỬ PHỤC VỤ =========================
    case 'saveServiceHistory':
        require_write();
        $memberId = (int) ($in['member_id'] ?? 0);
        personal_guard($me, $canEditStaff, $memberId, 'edit');
        if (empty($in['year_label'])) json_fail('Thiếu thông tin bắt buộc.', 400);

        personal_run(fn() => PersonalService::saveServiceHistory($memberId, $in, (int) $me['id']));

        log_action('sua', 'staff', 'Ghi lịch sử phục vụ: ' . personal_name($memberId), (string) $in['year_label']);
        json_ok(['ok' => true]);
        break;

    case 'deleteServiceHistory':
        require_write();
        $memberId = (int) ($in['member_id'] ?? 0);
        personal_guard($me, $canEditStaff, $memberId, 'edit');
        if (empty($in['year_label'])) json_fail('Thiếu thông tin bắt buộc.', 400);

        personal_run(fn() => PersonalService::deleteServiceHistory($memberId, (string) $in['year_label']));

        log_action('xoa', 'staff', 'Xoá lịch sử phục vụ: ' . personal_name($memberId), (string) $in['year_label']);
        json_ok(['ok' => true]);
        break;

    // ========================== TRẠNG THÁI ===========================
    case 'changeMemberStatus':
        require_write();
        $memberId = (int) ($in['member_id'] ?? 0);
        personal_guard($me, $canEditStaff, $memberId, 'edit');
        if (empty($in['status'])) json_fail('Thiếu thông tin bắt buộc.', 400);

        // Ghi chú bắt buộc khi rời 'đang phục vụ' (kiểm trong service).
        $note   = isset($in['note']) ? (string) $in['note'] : null;
        $status = (string) $in['status'];
        $result = personal_run(fn() => PersonalService::changeMemberStatus($memberId, $status, $note, (int) $me['id']));

        // Chỉ ghi trạng thái mới — lý do (có thể nhạy cảm) nằm ở hồ sơ, không chép vào nhật ký.
        log_action('sua', 'staff', 'Đổi trạng thái: ' . personal_name($memberId), $status);
        Cache::flush();   // data.php (members[].status) đang được cache theo phạm vi
        json_ok($result);
        break;

    default:
        json_fail('Unknown action', 400);
}
