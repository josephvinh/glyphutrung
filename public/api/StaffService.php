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

    /**
     * Người "được bảo vệ": vai gốc admin/bdh HOẶC đang có phân công hiệu lực vai
     * admin/bdh (phòng thủ theo chiều sâu — assignments.php cho gán bdh qua phân công).
     */
    public function isProtected(array $member): bool
    {
        return in_array($member['role_code'], ['admin', 'bdh'], true)
            || has_active_role((int) $member['id'], 'bdh')
            || has_active_role((int) $member['id'], 'admin');
    }

    /**
     * Người gọi có được thao tác $op ('edit'|'delete'|'reset'|'approve') trên $target không (#97).
     * Trả null nếu được; ngược lại ['ok'=>false,'error'=>…,'code'=>404|403|400].
     * $target cần có id, role_code. Không đọc $this->in (kiểm thử đơn vị được).
     *
     *  - Mục tiêu là admin, người gọi không phải admin: 404 GIỐNG HỆT id không tồn tại
     *    (nhất quán F9 — không cho dò ra tài khoản Quản trị qua API).
     *  - Mục tiêu được bảo vệ (BĐH), người gọi không phải admin: 403 — trừ BĐH tự sửa
     *    danh tính của chính mình (saveMember giữ nguyên chức danh).
     *  - Không ai tự xoá chính mình (400).
     *  - Admin xoá người được bảo vệ: giữ thông điệp cũ (400).
     */
    public function guardTarget(array $target, string $op): ?array
    {
        $callerIsAdmin = ($this->me['role_code'] ?? '') === 'admin';
        $self          = (int) $target['id'] === (int) ($this->me['id'] ?? 0);

        if (!$callerIsAdmin
            && ($target['role_code'] === 'admin' || has_active_role((int) $target['id'], 'admin'))) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }
        if (!$callerIsAdmin && $this->isProtected($target)) {
            if ($op === 'edit' && $self) return null;
            $msg = [
                'edit'    => 'Chỉ Quản Trị Hệ Thống mới sửa được hồ sơ thành viên Ban Điều Hành.',
                'delete'  => 'Chỉ Quản Trị Hệ Thống mới xoá được hồ sơ thành viên Ban Điều Hành.',
                'reset'   => 'Chỉ Quản Trị Hệ Thống mới cấp lại mật khẩu cho Ban Điều Hành.',
                'approve' => 'Chỉ Quản Trị Hệ Thống mới duyệt được hồ sơ thành viên Ban Điều Hành.',
            ][$op] ?? 'Chỉ Quản Trị Hệ Thống mới thao tác được với thành viên Ban Điều Hành.';
            return ['ok' => false, 'error' => $msg, 'code' => 403];
        }
        if ($op === 'delete' && $self) {
            return ['ok' => false, 'error' => 'Không thể tự xoá tài khoản của chính mình.', 'code' => 400];
        }
        if ($op === 'delete' && $this->isProtected($target)) {
            return ['ok' => false, 'error' => 'Không thể xóa tài khoản Quản trị hoặc Ban Điều Hành.', 'code' => 400];
        }
        return null;
    }

    /**
     * Sửa thông tin thành viên đã có (không tạo mới — xem auth.php đăng ký + approveMember)
     */
    public function saveMember(): array
    {
        require_write();

        // Không tạo thành viên ở đây: thành viên tự đăng ký (auth.php) rồi BĐH
        // duyệt (approveMember). Hàm này chỉ SỬA người đã có.
        $id = (int) ($this->in['id'] ?? 0);
        if (!$id) {
            return ['ok' => false, 'error' => 'Thành viên phải tự đăng ký tài khoản, rồi BĐH duyệt ở mục chờ duyệt.', 'code' => 400];
        }

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
        // SĐT Việt Nam: 10 số, bắt đầu bằng 0 (#98)
        if (!preg_match('/^0\d{9}$/', $phone)) {
            return ['ok' => false, 'error' => 'Số điện thoại không hợp lệ (cần 10 số bắt đầu bằng 0).'];
        }

        $old = $id ? db_one('SELECT id, role_code, full_name, title_id FROM members WHERE id=?', [$id]) : null;
        if ($id && !$old) {
            return ['ok' => false, 'error' => 'Không tìm thấy thành viên.', 'code' => 404];
        }
        if ($old && ($deny = $this->guardTarget($old, 'edit'))) {
            return $deny;
        }

        // Có phân công đang hiệu lực? (kiêm nhiệm). Dùng cho hai việc:
        //  - Khi CHỈ sửa danh tính người đã có phân công (identityOnly) thì KHÔNG
        //    bắt buộc chọn lại khối/lớp — vai gốc + block/class là giá trị dẫn
        //    xuất từ phân công, quản ở màn Khối & Lớp. Nếu vẫn đòi, một GLV kiêm
        //    nhiệm không có class_id gốc sẽ không sửa nổi cả số điện thoại.
        //  - Chặn đổi vai gốc ở màn Nhân sự (xem nhánh cập nhật bên dưới).
        $hasAssignments = $old
            ? ((int) db_one(
                "SELECT COUNT(*) n FROM member_assignments WHERE member_id=? AND to_date IS NULL AND role_code <> 'thu_thu'",
                [$id]
              )['n'] > 0)
            : false;
        $identityOnly = $old && $hasAssignments;

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

        // Chỉ đòi chọn khối/lớp khi THỰC SỰ cần dùng (tạo mới / đổi vai đơn-vai).
        // Khi chỉ sửa danh tính người đã kiêm nhiệm ($identityOnly) thì bỏ qua —
        // block/class không được ghi ở nhánh đó nên không bắt buộc.
        if ($r['scope'] === 'khối') {
            $b = db_one('SELECT id FROM blocks WHERE name=?', [$this->in('block')]);
            if ($b) {
                $blockId = (int) $b['id'];
            } elseif (!$identityOnly) {
                return ['ok' => false, 'error' => 'Trưởng Khối cần chọn khối phụ trách.'];
            }
        } elseif ($r['scope'] === 'lớp' && $role !== 'du_bi') {
            // Dự Bị là vai hỗ trợ, phân lớp làm SAU ở màn Khối & Lớp → không bắt
            // buộc chọn lớp lúc tạo/sửa nhân sự (giữ hành vi trước khi F6 đổi
            // scope du_bi từ '' sang 'lớp'; tránh regression).
            $c = db_one('SELECT c.id, c.block_id FROM classes c WHERE c.name=?', [$this->in('className')]);
            if ($c) {
                $classId = (int) $c['id'];
                $blockId = (int) $c['block_id'];
            } elseif (!$identityOnly) {
                return ['ok' => false, 'error' => 'Vai trò này cần chọn lớp phụ trách.'];
            }
        }

        // Phòng thủ theo chiều sâu (F6-review): người tạo/sửa KHÔNG phải toàn
        // đoàn (admin/BĐH) chỉ được gán khối/lớp thuộc phạm vi mình quản — chặn
        // việc "cấy" một người có phạm vi ngoài quyền mình. Hiện chỉ admin/BĐH có
        // staff=edit nên nhánh này thường không chạm tới, nhưng chặn sẵn phòng khi
        // phân quyền được chỉnh trong app.
        if (!$callerIsAdmin && ($this->me['role_code'] ?? '') !== 'bdh') {
            if ($classId !== null && !can_manage_class($this->me, $classId)) {
                return ['ok' => false, 'error' => 'Bạn không quản lý lớp đã chọn.', 'code' => 403];
            }
            if ($classId === null && $blockId !== null && !can_manage_block($this->me, $blockId)) {
                return ['ok' => false, 'error' => 'Bạn không quản lý khối đã chọn.', 'code' => 403];
            }
        }

        $t = db_one('SELECT id FROM titles WHERE role_code=? AND label=?', [$role, $this->in('title')]);
        $titleId = $t['id'] ?? db_one('SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1', [$role])['id'] ?? null;
        // Người không phải admin sửa hồ sơ được bảo vệ (chỉ còn trường hợp BĐH tự sửa
        // mình — guardTarget đã chặn phần còn lại): chỉ danh tính, giữ nguyên chức danh.
        if ($old && !$callerIsAdmin && $this->isProtected($old)) {
            $titleId = $old['title_id'];
        }

        $dup = db_one('SELECT id FROM members WHERE phone=? AND id <> ?', [$phone, $id]);
        if ($dup) {
            return ['ok' => false, 'error' => 'Số điện thoại này đã có tài khoản khác dùng.'];
        }

        // A′ — KHÔNG ghi/xoá member_assignments ở đây. Kiêm nhiệm (thêm/bớt vị
        // trí) do màn Khối & Lớp quản (org.php: setClassHead/setBlockHead) một
        // cách non-destructive. saveMember chỉ quản DANH TÍNH + VAI GỐC.
        if ($old) {
            // Đang kiêm nhiệm (có ≥1 phân công hiệu lực) thì vai gốc + block/class
            // là giá trị DẪN XUẤT từ phân công (xem recompute_member_primary) — không sửa
            // ngược từ màn Nhân sự, chỉ cập nhật danh tính để không xoá kiêm nhiệm.
            if ($hasAssignments) {
                // Không âm thầm nuốt thay đổi vai: nếu người dùng cố đổi vai gốc
                // của người đang kiêm nhiệm ở màn Nhân sự, báo rõ để họ làm đúng
                // chỗ (Khối & Lớp) thay vì tưởng đã lưu.
                if ($old['role_code'] !== $role) {
                    return ['ok' => false,
                            'error' => 'Người này đã được phân công (kiêm nhiệm). '
                                     . 'Đổi vai trò/chức vụ phải thực hiện ở màn Khối & Lớp '
                                     . '(phân công), không đổi ở màn Nhân sự.',
                            'code' => 409];
                }
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

        if ($deny = $this->guardTarget($m, 'delete')) {
            return $deny;
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
