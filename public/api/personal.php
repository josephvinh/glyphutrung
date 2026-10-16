<?php
/**
 * HỒ SƠ CÁ NHÂN
 *
 * API cho frontend cá nhân (module_personal) — xem hồ sơ của chính mình
 * và sửa các trường do BĐH/Quản trị cấp quyền. Endpoint dành cho giao
 * diện "hồ sơ của tôi"; nếu BĐH/Quản trị muốn quản lý hồ sơ của người
 * khác (gộp vào module Tổ chức) thì dùng các action tương ứng ở
 * `org.php` (getServiceHistory/saveServiceHistory/updateGlvYear/...).
 *
 * Phân quyền:
 *   - getProfile             : org.view (mặc định của $me; nếu xem người
 *                               khác thì phải có org.edit)
 *   - updateGlvYear          : org.edit (BĐH/Quản trị — chính sách hiện
 *                               không cho thành viên tự đổi năm GLV của
 *                               mình; muốn đổi phải nhờ BĐH)
 *   - saveServiceHistory     : org.edit
 *   - deleteServiceHistory   : org.edit
 *
 * Ghi chú: tất cả thao tác GHI đều đi qua require_write() (POST + CSRF +
 * rate-limit) theo AGENT_RULES.md rule 6.
 */

require __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/PersonalService.php';

$action = $_GET['action'] ?? '';
$in     = json_input();

// getProfile là đọc — route ở mức 'view' (mọi thành viên đều xem được
// hồ sơ của chính mình). Nếu muốn xem của NGƯỜI KHÁC, case tự nâng lên
// check 'edit' bên trong.
$VIEW_ACTIONS = ['getProfile'];

$me = in_array($action, $VIEW_ACTIONS, true)
    ? require_permission('org', 'view')
    : require_permission('org', 'edit');

switch ($action) {

    // ============================= HỒ SƠ =============================
    case 'getProfile':
        $targetId = (int) ($_GET['member_id'] ?? $me['id']);

        // Chính mình thì OK ở 'view'; người khác phải có 'org.edit'.
        if ($targetId !== (int) $me['id'] && permission_of('org') !== 'edit') {
            json_fail('Bạn chỉ được xem hồ sơ của chính mình.', 403);
        }

        $member = db_one(
            'SELECT m.*, t.label AS title_label
               FROM members m
               LEFT JOIN titles t ON t.id = m.title_id
              WHERE m.id = ?',
            [$targetId]
        );
        if (!$member) {
            json_fail('Không tìm thấy thành viên.', 404);
        }

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
            'assignments' => effective_assignments($targetId),
            'history'     => PersonalService::getServiceHistory($targetId),
        ]);
        break;

    // ============================ NĂM GLV ============================
    case 'updateGlvYear':
        require_write();
        // require_permission('org', 'edit') đã chạy ở top-level (vì
        // updateGlvYear không thuộc $VIEW_ACTIONS).
        if (empty($in['member_id'])) {
            json_fail('Thiếu member_id.', 400);
        }
        $glvYear = null;
        if (array_key_exists('glv_year', $in) && $in['glv_year'] !== null) {
            $year = (int) $in['glv_year'];
            if ($year < 1900 || $year > (int) date('Y')) {
                json_fail('Năm không hợp lệ.', 400);
            }
            $glvYear = $year;
        }
        try {
            $result = PersonalService::updateGlvYear(
                (int) $in['member_id'],
                $glvYear,
                (int) $me['id']
            );
            json_ok($result);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    // ====================== LỊCH SỬ PHỤC VỤ =========================
    case 'saveServiceHistory':
        require_write();
        if (empty($in['member_id']) || empty($in['year_label'])) {
            json_fail('Thiếu thông tin bắt buộc.', 400);
        }
        if (!preg_match('/^\d{4}-\d{4}$/', (string) $in['year_label'])) {
            json_fail('Năm học phải có dạng YYYY-YYYY (ví dụ 2024-2025).', 400);
        }
        try {
            PersonalService::saveServiceHistory(
                (int) $in['member_id'],
                $in,
                (int) $me['id']
            );
            json_ok(['ok' => true]);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    case 'deleteServiceHistory':
        require_write();
        if (empty($in['member_id']) || empty($in['year_label'])) {
            json_fail('Thiếu thông tin bắt buộc.', 400);
        }
        try {
            PersonalService::deleteServiceHistory(
                (int) $in['member_id'],
                (string) $in['year_label']
            );
            json_ok(['ok' => true]);
        } catch (Exception $e) {
            json_fail($e->getMessage(), 400);
        }
        break;

    default:
        json_fail('Hành động không hợp lệ.', 404);
}
