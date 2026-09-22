<?php
/**
 * STAFF SERVICE
 *
 * Tách logic nghiệp vụ nhân sự từ org.php.
 * Quản lý thành viên: tạo, sửa, xóa, duyệt, từ chối.
 */

class StaffService
{
    private array $me;
    private int $yid;
    private array $in;

    public function __construct(array $me, int $yid, array $input)
    {
        $this->me = $me;
        $this->yid = $yid;
        $this->in = $input;
    }

    private function in(string $key, string $default = ''): string
    {
        return trim((string) ($this->in[$key] ?? $default));
    }

    public function isProtected(array $member): bool
    {
        return in_array($member['role_code'], ['admin', 'bdh'], true);
    }

    /**
     * Lưu thành viên (tạo mới hoặc cập nhật)
     */
    public function saveMember(): array
    {
        require_write();

        $id = (int) ($this->in['id'] ?? 0);

        $holyName = mb_convert_case(
            preg_replace('/\s+/', ' ', $this->in('holyName')),
            MB_CASE_TITLE, 'UTF-8'
        );
        $name = mb_convert_case(
            preg_replace('/\s+/', ' ', $this->in('fullName')),
            MB_CASE_TITLE, 'UTF-8'
        );

        $phone = preg_replace('/[^\d]/', '', $this->in('phone'));
        if (strpos($phone, '84') === 0 && strlen($phone) >= 11) {
            $phone = '0' . substr($phone, 2);
        }
        if ($phone !== '' && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }

        $role = $this->in('role') ?: 'glv';

        if ($name === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập họ và tên.'];
        }
        if ($phone === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập số điện thoại.'];
        }

        $old = $id ? db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]) : null;
        if ($id && !$old) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }

        // Vai trò BĐH/Quản trị bị khóa: không cho HẠ vai người đang là admin/bdh.
        if ($old && $this->isProtected($old)) {
            $role = $old['role_code'];
        }

        // Chống leo thang (F2): chỉ kiểm khi vai THỰC SỰ thay đổi (tạo mới hoặc
        // đổi vai). Sửa danh tính của người giữ nguyên vai — kể cả admin/bdh —
        // không bị chặn ở đây.
        $callerIsAdmin = ($this->me['role_code'] ?? '') === 'admin';
        $roleChanging  = !$old || ($old['role_code'] !== $role);
        if ($roleChanging) {
            $ASSIGNABLE = ['truong_khoi', 'glv_chu_nhiem', 'glv', 'du_bi'];
            // Chỉ Quản trị mới được tạo/gán vai Quản trị hoặc Ban Điều Hành.
            if (in_array($role, ['admin', 'bdh'], true) && !$callerIsAdmin) {
                return ['ok' => false,
                        'error' => 'Chỉ Quản Trị Hệ Thống mới được gán vai Quản trị hoặc Ban Điều Hành.',
                        'code' => 403];
            }
            // Vai khác phải nằm trong danh sách hợp lệ (admin được phép mọi vai).
            if (!$callerIsAdmin && !in_array($role, $ASSIGNABLE, true)) {
                return ['ok' => false, 'error' => 'Vai trò không hợp lệ.', 'code' => 400];
            }
        }

        $r = db_one('SELECT scope FROM roles WHERE code=?', [$role]);
        if (!$r) {
            return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
        }

        $blockId = null;
        $classId = null;

        if ($r['scope'] === 'khối') {
            $b = db_one('SELECT id FROM blocks WHERE name=?', [$this->in('block')]);
            if (!$b) {
                return ['ok' => false, 'error' => 'Trưởng Khối cần chọn khối phụ trách.'];
            }
            $blockId = (int) $b['id'];
        } elseif ($r['scope'] === 'lớp') {
            $c = db_one('SELECT c.id, c.block_id FROM classes c WHERE c.name=?', [$this->in('className')]);
            if (!$c) {
                return ['ok' => false, 'error' => 'Vai trò này cần chọn lớp phụ trách.'];
            }
            $classId = (int) $c['id'];
            $blockId = (int) $c['block_id'];
        }

        $t = db_one('SELECT id FROM titles WHERE role_code=? AND label=?', [$role, $this->in('title')]);
        $titleId = $t['id'] ?? db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$role])['id'] ?? null;

        $dup = db_one('SELECT id FROM members WHERE phone=? AND id <> ?', [$phone, $id]);
        if ($dup) {
            return ['ok' => false, 'error' => 'Số điện thoại này đã có tài khoản khác dùng.'];
        }

        // A′ — KHÔNG ghi/xoá member_assignments ở đây. Kiêm nhiệm (thêm/bớt vị
        // trí) do màn Khối & Lớp quản (org.php: setClassHead/setBlockHead) một
        // cách non-destructive. saveMember chỉ quản DANH TÍNH + VAI GỐC.
        if ($old) {
            // Đang kiêm nhiệm (có ≥1 phân công hiệu lực) thì vai gốc + block/class
            // là giá trị DẪN XUẤT từ phân công (xem demoteMember) — không sửa
            // ngược từ màn Nhân sự, chỉ cập nhật danh tính để không xoá kiêm nhiệm.
            $hasAssignments = (int) db_one(
                "SELECT COUNT(*) n FROM member_assignments WHERE member_id=? AND to_date IS NULL",
                [$id]
            )['n'] > 0;

            if ($hasAssignments) {
                db_run(
                    "UPDATE members SET holy_name=?, full_name=?, phone=?, title_id=? WHERE id=?",
                    [$holyName, $name, $phone, $titleId, $id]
                );
            } else {
                // Đơn vai (chưa có phân công): cho sửa cả vai gốc + vị trí hiển thị.
                db_run(
                    "UPDATE members SET holy_name=?, full_name=?, phone=?, role_code=?, title_id=?, block_id=?, class_id=? WHERE id=?",
                    [$holyName, $name, $phone, $role, $titleId, $blockId, $classId, $id]
                );
            }

            log_action('sua', 'staff', 'Sửa nhân sự: ' . $name, $role);
        } else {
            // Tạo mới: chỉ tạo bản ghi members (trạng thái chờ duyệt). KHÔNG tạo
            // assignment — phân lớp/khối làm sau ở màn Khối & Lớp.
            $defaultPw = app_config('default_password') ?: 'tntt@2026';
            // Cấp mã GLV kế tiếp (giống luồng tự đăng ký ở auth.php).
            $max  = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n
                              FROM members WHERE code LIKE 'GLV%'");
            $code = 'GLV' . str_pad((string) (((int) ($max['n'] ?? 0)) + 1), 3, '0', STR_PAD_LEFT);

            db_insert(
                "INSERT INTO members (code, holy_name, full_name, phone, password_hash, role_code, title_id, block_id, class_id, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,'chờ duyệt',NOW())",
                [$code, $holyName, $name, $phone, password_hash($defaultPw, PASSWORD_DEFAULT), $role, $titleId, $blockId, $classId]
            );

            log_action('tao', 'staff', 'Tạo nhân sự: ' . $name, $role);
        }

        Cache::flush();
        return ['ok' => true];
    }

    /**
     * Xóa thành viên
     */
    public function deleteMember(): array
    {
        require_write();

        $id = (int) ($this->in['id'] ?? 0);
        if (!$id) {
            return ['ok' => false, 'error' => 'Thiếu ID thành viên.'];
        }

        $m = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
        if (!$m) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }

        if ($this->isProtected($m)) {
            return ['ok' => false, 'error' => 'Không thể xóa tài khoản Quản trị hoặc Ban Điều Hành.'];
        }

        // Xóa phân công
        db_run('DELETE FROM member_assignments WHERE member_id=?', [$id]);
        // Xóa thành viên
        db_run('DELETE FROM members WHERE id=?', [$id]);

        log_action('xoa', 'staff', 'Xóa nhân sự: ' . $m['full_name'], '');
        Cache::flush();

        return ['ok' => true];
    }

    // GHI CHÚ: approveMember / rejectMember / resetPassword trước đây trùng ở
    // đây và trong org.php (bản inline). Bản inline của org.php mới là đường
    // đang chạy (dùng đúng cột password_hash + ENUM status hợp lệ). Các bản
    // trùng ở StaffService đã bị xoá để tránh nhầm lẫn và loại code sai.
}
