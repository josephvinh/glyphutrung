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

        // Có phân công đang hiệu lực? (kiêm nhiệm). Dùng cho hai việc:
        //  - Khi CHỈ sửa danh tính người đã có phân công (identityOnly) thì KHÔNG
        //    bắt buộc chọn lại khối/lớp — vai gốc + block/class là giá trị dẫn
        //    xuất từ phân công, quản ở màn Khối & Lớp. Nếu vẫn đòi, một GLV kiêm
        //    nhiệm không có class_id gốc sẽ không sửa nổi cả số điện thoại.
        //  - Chặn đổi vai gốc ở màn Nhân sự (xem nhánh cập nhật bên dưới).
        $hasAssignments = $old
            ? ((int) db_one(
                "SELECT COUNT(*) n FROM member_assignments WHERE member_id=? AND to_date IS NULL",
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
        } else {
            // Tạo mới: chỉ tạo bản ghi members (trạng thái chờ duyệt). KHÔNG tạo
            // assignment — phân lớp/khối làm sau ở màn Khối & Lớp.
            $defaultPw = app_config('default_password') ?: 'tntt@2026';
            $pwHash    = password_hash($defaultPw, PASSWORD_DEFAULT);
            // Cấp mã GLV kế tiếp (giống luồng tự đăng ký ở auth.php). MAX+1 không
            // nguyên tử nên hai lần tạo song song (kể cả cùng lúc với tự đăng ký)
            // có thể trùng mã — members.code là UNIQUE. Thử lại vài lần với mã kế
            // tiếp thay vì để lỗi trùng khoá làm hỏng cả yêu cầu (500).
            $inserted = false;
            for ($attempt = 0; $attempt < 5 && !$inserted; $attempt++) {
                $max  = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n
                                  FROM members WHERE code LIKE 'GLV%'");
                $code = 'GLV' . str_pad((string) (((int) ($max['n'] ?? 0)) + 1), 3, '0', STR_PAD_LEFT);
                try {
                    db_insert(
                        "INSERT INTO members (code, holy_name, full_name, phone, password_hash, role_code, title_id, block_id, class_id, status, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,'chờ duyệt',NOW())",
                        [$code, $holyName, $name, $phone, $pwHash, $role, $titleId, $blockId, $classId]
                    );
                    $inserted = true;
                } catch (\PDOException $e) {
                    // 23000 = vi phạm ràng buộc (khả năng trùng mã do đua). Nếu là
                    // trùng SỐ ĐIỆN THOẠI thì đã chặn ở trên; còn lại thử mã mới.
                    if ($e->getCode() !== '23000' || $attempt === 4) {
                        return ['ok' => false,
                                'error' => 'Không tạo được tài khoản (mã bị trùng do thao tác đồng thời), '
                                         . 'vui lòng thử lại.', 'code' => 409];
                    }
                }
            }

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
