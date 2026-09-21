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

        // Vai trò BĐH/Quản trị bị khóa
        if ($old && $this->isProtected($old)) {
            $role = $old['role_code'];
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

        if ($old) {
            // Cập nhật
            db_run(
                "UPDATE members SET holy_name=?, full_name=?, phone=?, role_code=?, title_id=?, block_id=?, class_id=? WHERE id=?",
                [$holyName, $name, $phone, $role, $titleId, $blockId, $classId, $id]
            );

            if ($old['role_code'] !== $role) {
                $this->updateAssignment($id, $role, $blockId, $classId);
            }

            log_action('sua', 'staff', 'Sửa nhân sự: ' . $name, $role);
        } else {
            // Tạo mới
            $defaultPw = config('default_password') ?: 'tntt@2026';
            db_insert(
                "INSERT INTO members (holy_name, full_name, phone, password, role_code, title_id, block_id, class_id, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,'chờ duyệt',NOW())",
                [$holyName, $name, $phone, password_hash($defaultPw, PASSWORD_DEFAULT), $role, $titleId, $blockId, $classId]
            );

            $newId = db_one('SELECT LAST_INSERT_ID() id')['id'];
            $this->createAssignment($newId, $role, $blockId, $classId);

            log_action('tao', 'staff', 'Tạo nhân sự: ' . $name, $role);
        }

        Cache::flush();
        return ['ok' => true];
    }

    private function updateAssignment(int $memberId, string $role, ?int $blockId, ?int $classId): void
    {
        // Kết thúc phân công cũ
        db_run("UPDATE member_assignments SET to_date = CURDATE() WHERE member_id = ? AND to_date IS NULL", [$memberId]);
        // Tạo phân công mới
        $this->createAssignment($memberId, $role, $blockId, $classId);
    }

    private function createAssignment(int $memberId, string $role, ?int $blockId, ?int $classId): void
    {
        db_insert(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, from_date) VALUES (?,?,?,?,CURDATE())",
            [$memberId, $role, $blockId, $classId]
        );
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

    /**
     * Duyệt tài khoản
     */
    public function approveMember(): array
    {
        $id = (int) ($this->in['id'] ?? 0);
        if (!$id) {
            return ['ok' => false, 'error' => 'Thiếu ID.'];
        }

        $m = db_one('SELECT id, full_name, status FROM members WHERE id=?', [$id]);
        if (!$m) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }

        if ($m['status'] !== 'chờ duyệt') {
            return ['ok' => false, 'error' => 'Tài khoản không ở trạng thái chờ duyệt.'];
        }

        db_run("UPDATE members SET status='hoạt động' WHERE id=?", [$id]);
        log_action('duyet', 'staff', 'Duyệt tài khoản: ' . $m['full_name'], '');
        Cache::flush();

        return ['ok' => true];
    }

    /**
     * Từ chối tài khoản
     */
    public function rejectMember(): array
    {
        $id = (int) ($this->in['id'] ?? 0);
        if (!$id) {
            return ['ok' => false, 'error' => 'Thiếu ID.'];
        }

        $m = db_one('SELECT id, full_name, status FROM members WHERE id=?', [$id]);
        if (!$m) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }

        $reason = $this->in('reason');

        db_run("UPDATE members SET status='từ chối' WHERE id=?", [$id]);
        log_action('tuchoi', 'staff', 'Từ chối: ' . $m['full_name'], $reason ?: '');
        Cache::flush();

        return ['ok' => true];
    }

    /**
     * Reset mật khẩu
     */
    public function resetPassword(): array
    {
        $id = (int) ($this->in['id'] ?? 0);
        if (!$id) {
            return ['ok' => false, 'error' => 'Thiếu ID.'];
        }

        $m = db_one('SELECT id, full_name FROM members WHERE id=?', [$id]);
        if (!$m) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }

        $defaultPw = config('default_password') ?: 'tntt@2026';
        db_run('UPDATE members SET password=?, must_change_pw=1 WHERE id=?',
               [password_hash($defaultPw, PASSWORD_DEFAULT), $id]);

        log_action('reset_pw', 'staff', 'Reset mật khẩu: ' . $m['full_name'], 'Cấp lại mật khẩu mặc định');
        Cache::flush();

        return ['ok' => true, 'default_password' => $defaultPw];
    }
}
